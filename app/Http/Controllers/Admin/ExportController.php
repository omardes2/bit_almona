<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Livewire\Admin\Customers\CustomerIndex;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Support\Csv;
use App\Support\Decimal;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV exports. Rows are streamed with lazy() so large tables do not load
 * into memory. Every export is written to the audit log (who / what).
 */
class ExportController extends Controller
{
    public function __invoke(Request $request, string $type): StreamedResponse
    {
        $ability = match ($type) {
            'orders' => 'view-reports',
            'customers' => 'manage-customers',
            'products' => 'manage-catalog',
            default => abort(404),
        };

        Gate::authorize($ability);

        $stamp = now()->format('Y-m-d_His');
        AuditLog::record($request->user(), 'export', [], ['type' => $type] + $request->only('from', 'to', 'status'));

        return match ($type) {
            'orders' => $this->orders($request, $stamp),
            'customers' => $this->customers($stamp),
            'products' => $this->products($stamp),
        };
    }

    private function orders(Request $request, string $stamp): StreamedResponse
    {
        $date = fn (string $key) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query($key))
            ? Carbon::createFromFormat('Y-m-d', $request->query($key)) : null;

        $query = Order::query()
            ->with('payment')
            ->when($date('from'), fn ($q, $from) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($date('to'), fn ($q, $to) => $q->where('created_at', '<=', $to->endOfDay()))
            ->when(OrderStatus::tryFrom((string) $request->query('status')), fn ($q, $s) => $q->where('status', $s))
            ->orderBy('id');

        $rows = (function () use ($query) {
            foreach ($query->lazy(500) as $order) {
                yield [
                    $order->order_number,
                    $order->created_at->format('Y-m-d H:i'),
                    $order->customer_name,
                    $order->customer_phone,
                    $order->status->label(),
                    $order->total,
                    $order->delivery_fee,
                    $order->payment_method->label(),
                    $order->payment?->status->label(),
                ];
            }
        })();

        return Csv::download("orders_{$stamp}.csv", ['رقم الطلب', 'التاريخ', 'العميل', 'الجوال', 'الحالة', 'المبلغ', 'التوصيل', 'طريقة الدفع', 'حالة الدفع'], $rows);
    }

    private function customers(string $stamp): StreamedResponse
    {
        $rows = (function () {
            foreach (CustomerIndex::query()->reorder()->orderBy('id')->lazy(500) as $customer) {
                yield [
                    $customer->name,
                    $customer->phone,
                    $customer->customer?->whatsapp,
                    $customer->created_at->format('Y-m-d'),
                    $customer->orders_count,
                    number_format((float) $customer->purchases_total, 2, '.', ''),
                    $customer->status->label(),
                ];
            }
        })();

        return Csv::download("customers_{$stamp}.csv", ['الاسم', 'الجوال', 'واتساب', 'تاريخ التسجيل', 'عدد الطلبات', 'إجمالي المشتريات (مسلّمة)', 'الحالة'], $rows);
    }

    private function products(string $stamp): StreamedResponse
    {
        $rows = (function () {
            foreach (Product::query()->with('category:id,name')->orderBy('id')->lazy(500) as $product) {
                yield [
                    $product->sku,
                    $product->name,
                    $product->category?->name,
                    $product->sale_price,
                    Decimal::trim((string) $product->stock_quantity),
                    $product->unit->label(),
                    $product->status->label(),
                ];
            }
        })();

        return Csv::download("products_{$stamp}.csv", ['SKU', 'الاسم', 'القسم', 'السعر', 'المخزون', 'الوحدة', 'الحالة'], $rows);
    }
}
