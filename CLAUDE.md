# بيت المونة — ملاحظات للمطورين

- Arabic-only, RTL, mobile-first storefront for a Hebron supermarket. UI text is Arabic; code/comments English.
- Auth: single `users` table, login by **phone + password** (`App\Actions\Auth\AttemptLogin`). `users.type` = customer|admin;
  profile data lives in `customers` / `admins`. Phones are normalised to `05XXXXXXXX` (`App\Support\PhoneNumber`).
- Route protection: `auth` + `customer` for the account area, `auth` + `admin[:role,...]` for `/admin`.
- Prices: never trust the client. Always use `App\Services\Pricing\ProductPriceResolver`. Order lines are
  snapshots (`OrderItem::snapshotFrom`). Order status changes go through `Order::transitionTo()`.
- Nothing store-specific is hard-coded: categories, delivery zones and store settings (`StoreSetting::get()`) live in the DB.
- Livewire components are class-based (`app/Livewire`, views in `resources/views/livewire`).
- Run `vendor/bin/pint` and `php artisan test` before committing. Tests use in-memory SQLite; migrations must stay MySQL + SQLite compatible.
- Admin catalog (Phase 2): `app/Livewire/Admin/{Products,Categories,Offers,Banners}` (Index + Form each). Every catalog
  component uses `AuthorizesCatalog` (gate `manage-catalog` = super_admin|manager) — re-checked on every Livewire request.
- Images: always go through `App\Services\Media\ImageStorage` (random ULID names, `{dir}/thumbs/{name}.webp` thumbnails,
  GD re-encode) and validate with `App\Support\ImageRules`. Swap `ImageProcessor` binding to change the engine.
- Offer/banner state: `HasSchedule` trait (`scheduleStatus()`, `withScheduleStatus()`), never `is_active` alone.
- Products are soft deleted (`DeleteProduct` also disables their offers); categories with children/products can't be deleted.
- Admin model changes are logged to `audit_logs` via the `Auditable` trait.
- Storefront (Phase 3): controllers in `app/Http/Controllers/Store` render `resources/views/store/*` with
  `<x-layouts::app>` (same `layouts/app.blade.php` Livewire uses). Products shown to customers must use
  `Product::storefront()` (not hidden, not trashed, active category); prices via `$product->price()` (memoised resolver).
- Cart: `App\Services\Cart\CartService` only — guest carts by session token (`carts.session_id`), users by `user_id`,
  merged on the `Login` event. Quantities are integer thousandths via `QuantityRules` (min/step/stock).
  The browser only ever sends a product id + quantity (`add-to-cart` event handled by `Livewire\Store\CartDrawer`).
- `StorefrontCache` caches plain arrays (the cache refuses to unserialize objects) and is flushed on
  Category/Banner save/delete and on reorder.
- Visibility: `App\Support\StorefrontVisibility` (category + every ancestor active) backs `Product::storefront()`,
  `Category::storefront()` and `Product::isVisibleInStore()`. Never check `category.is_active` directly in store code.
- Checkout (Phase 4): `App\Actions\Checkout\PlaceOrder` is the only way to create an order. It locks cart items and
  product rows (`lockForUpdate`, id order), validates with `CartService::summary($cart, $lockedProducts)` — never a plain
  read inside that transaction (MySQL REPEATABLE READ would return stale stock) — and is idempotent per `checkout_token`.
  Totals come from `App\Services\Checkout\CheckoutCalculator` (subtotal at original prices − discount + zone fee;
  minimum = max(store, zone)).
- Payments: `App\Payments\Contracts\PaymentProvider` + `PaymentManager` (config/payments.php). COD stays `pending`
  until an admin runs `MarkPaymentPaid`; delivery does not mark it paid.
- Order status changes: `App\Actions\Orders\ChangeOrderStatus` (wraps `Order::transitionTo()`; cancellation restores
  stock once via `orders.stock_restored_at`). Gates: `manage-orders` (all admins), `manage-delivery`, `manage-settings`.
- Phase 5: order lifecycle events `App\Events\Orders\*` (ShouldDispatchAfterCommit) feed the admin bell
  (`Notifications\Admin\*`, database channel) and future customer messages (`OrderStatusMessage`, queued; channels
  from `App\Messaging\MessagingManager` + config/messaging.php — no real provider yet). Stock alerts:
  `Support\Notifications\StockAlerts::check()` (de-duplicated via products.*_notified_at, sent after commit).
- OTP: `App\Otp\OtpService` (hashed codes, expiry, attempts, one-time use, throttling, no enumeration); no user flow
  until an `OtpSender` driver is configured (config/otp.php).
- Reports: `App\Services\Reports\SalesReport` — revenue = DELIVERED orders by delivered_at; orders value = non-cancelled
  by created_at. CSV via `App\Support\Csv` (BOM + formula-injection escaping). Gates: manage-customers, view-reports
  (super_admin|manager), view-audit-logs (super_admin). Audit values pass through `AuditLog::redact()`.
- Phase 6: `App\Services\System\SystemHealth` backs `store:check-production` (exit 1 on critical) and `/admin/system`
  (gate `manage-system` = super_admin, also failed jobs + `/admin/settings/integrations`, status only — never secrets).
  Scheduler heartbeat: `system:heartbeat` every minute (cache key). Log channels use the `RedactSensitiveData` tap.
- Settings/storefront cache reads go through `Cache::memo()` (one round trip per request) — keep it that way.
- OTP UIs: `/forgot-password`, `/account/verify-phone`, `/account/phone`; verified state lives in the session, never
  in the browser. They show "غير مفعلة" until `OTP_DRIVER` is a real sender. Tests use `Tests\Support\FakesOtp`.
  Password reset/phone change revoke sessions via `Actions\Auth\RevokeUserSessions`. OTP reset is customers only.
- Payments: `config/payments.php` `methods.*` (enabled + provider class). New orders use `PaymentManager::provider()`,
  existing payments `providerForExisting()`. Online providers redirect via `redirectUrl()` (https only). No webhook route yet.
- Staging: robots.txt `Disallow: /` + `X-Robots-Tag` when APP_ENV=staging. Docs: STAGING, ROLLBACK, PAYMENTS.
- Never leave `php artisan optimize`/`config:cache` in the working tree: tests would then use the cached config.
- Production steps live in docs/PRODUCTION_CHECKLIST.md.
