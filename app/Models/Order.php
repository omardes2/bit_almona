<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

#[Fillable([
    'order_number', 'user_id', 'status',
    'customer_name', 'customer_phone', 'customer_whatsapp',
    'delivery_zone_id', 'delivery_zone_name', 'address_id', 'delivery_address', 'customer_notes',
    'currency', 'subtotal', 'discount_total', 'delivery_fee', 'total',
    'admin_notes', 'cancellation_reason',
])]
class Order extends Model
{
    use HasFactory;

    protected $attributes = [
        'status' => 'new',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'preparing_at' => 'datetime',
            'out_for_delivery_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->order_number ??= static::generateOrderNumber();
        });
    }

    /**
     * e.g. BM-260930-7K3QX
     */
    public static function generateOrderNumber(): string
    {
        do {
            $number = 'BM-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        } while (static::where('order_number', $number)->exists());

        return $number;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }

    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    /**
     * Move the order to a new status, enforcing the allowed workflow and
     * recording the change in the status history.
     */
    public function transitionTo(OrderStatus $status, ?User $by = null, ?string $note = null): void
    {
        if (! $this->status->canTransitionTo($status)) {
            throw new InvalidArgumentException(
                "لا يمكن نقل الطلب من [{$this->status->label()}] إلى [{$status->label()}]."
            );
        }

        DB::transaction(function () use ($status, $by, $note) {
            $from = $this->status;

            $this->status = $status;

            if ($column = $status->timestampColumn()) {
                $this->{$column} = now();
            }

            if ($status === OrderStatus::Cancelled && $note !== null) {
                $this->cancellation_reason = $note;
            }

            $this->save();

            $this->statusHistory()->create([
                'from_status' => $from,
                'to_status' => $status,
                'changed_by' => $by?->id,
                'note' => $note,
            ]);
        });
    }
}
