# بيت المونة — بنية الدفع

## الوضع الحالي
- المتاح فقط: **الدفع عند الاستلام** (`cash_on_delivery`).
- لا توجد بوابة دفع إلكتروني مربوطة، ولا يوجد أي Endpoint للـ webhooks حاليًا.

## كيف تعمل طرق الدفع
| القطعة | الدور |
|---|---|
| `config/payments.php` | قائمة الطرق: `enabled`، كلاس المزود، أيقونة. تظهر في صفحة الدفع فقط الطرق المفعّلة التي لها كلاس موجود. |
| `App\Enums\PaymentMethod` | القيم المسموحة + الاسم والوصف بالعربية. |
| `App\Payments\Contracts\PaymentProvider` | العقد: `method()`, `isOnline()`, `createPayment()`, `redirectUrl()`, `verifyPayment()`, `refund()`, `cancel()`. |
| `App\Payments\PaymentManager` | `enabledMethods()`, `checkoutOptions()`, `provider()` (للطلبات الجديدة — يجب أن تكون الطريقة مفعّلة)، `providerForExisting()` (لإلغاء/استرجاع دفعات قديمة حتى لو عُطّلت الطريقة لاحقًا)، `redirectUrlFor()`. |

**قواعد أمان ثابتة:**
- المتصفح يرسل **مفتاح الطريقة فقط**. أي قيمة غير موجودة في الطرق المفعّلة تُرفض بالتحقق (لا يمكن اختراع مزود).
- المبالغ تُحسب في الخادم (`CheckoutCalculator` / `PlaceOrder`)، ولا يستطيع المتصفح تغيير حالة الدفع.
- الدفع عند الاستلام يبقى `pending` حتى يؤكد المدير الاستلام (`MarkPaymentPaid`)؛ التوصيل لا يجعله مدفوعًا تلقائيًا.

## تدفق الدفع الإلكتروني المستقبلي (غير مفعّل)
1. الزبون يختار طريقة `isOnline() = true` ويؤكد الطلب.
2. `PlaceOrder` ينشئ الطلب + سجل دفعة `pending` داخل نفس الـ transaction (`createPayment()` — بدون اتصال شبكي).
3. بعد الـ commit يستدعي Checkout `PaymentManager::redirectUrlFor($order)` ← `redirectUrl()` من المزود (رابط https فقط) ← تحويل الزبون لصفحة البوابة.
4. البوابة ترسل **webhook موقّع** إلى:
   `POST /webhooks/payments/{provider}` ← **هذا المسار غير موجود حاليًا عن قصد**، ويُضاف مع أول مزود حقيقي بالشروط التالية:
   - التحقق من التوقيع (HMAC) بمفتاح من `.env` فقط، ورفض أي طلب غير موقّع.
   - مستثنى من CSRF فقط لهذا المسار، مع rate limit.
   - Idempotent حسب رقم العملية لدى البوابة (`payments.transaction_reference`).
   - يستدعي `verifyPayment()` لدى البوابة قبل تغيير الحالة (لا يثق بمحتوى الـ webhook وحده).
   - يسجّل الفشل في السجل مع سياق آمن فقط (رقم الطلب، رقم الدفعة) — بدون أسرار.
5. صفحة العودة من البوابة تعرض الحالة فقط ولا تغيّرها.

### حالة `awaiting_payment` (توثيق فقط)
عند إضافة الدفع الإلكتروني يُقترح أن يبقى الطلب في حالة «بانتظار الدفع» حتى وصول webhook ناجح، ثم ينتقل إلى `pending` (طلب جديد) ويظهر للإدارة.
هذا **غير مطبّق** حاليًا ولا توجد هذه الحالة في `OrderStatus`؛ إضافتها تحتاج: قيمة جديدة في الـ enum، انتقالات في `Order::transitionTo()`، مهمة مجدولة تلغي الطلبات غير المدفوعة بعد مهلة وتعيد المخزون عبر `ChangeOrderStatus`.

## إضافة مزود جديد (عند توفر حساب حقيقي)
1. حالة جديدة في `PaymentMethod` (label + description).
2. كلاس ينفّذ `PaymentProvider` (`isOnline()` = true).
3. مدخل في `config/payments.php` مع `'enabled' => env('PAYMENT_XXX_ENABLED', false)`.
4. المفاتيح في `.env` فقط — **لا تُدخل من لوحة التحكم** (صفحة `/admin/settings/integrations` للعرض فقط).
5. مسار الـ webhook بالشروط أعلاه + اختبارات (توقيع خاطئ، تكرار، مبلغ مختلف).
