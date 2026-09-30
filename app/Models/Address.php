<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'delivery_zone_id', 'label', 'recipient_name', 'recipient_phone',
    'area', 'street', 'building', 'floor', 'details', 'latitude', 'longitude', 'is_default',
])]
class Address extends Model
{
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_default' => 'boolean',
        ];
    }

    protected function recipientPhone(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => filled($value) ? PhoneNumber::normalize($value) : null);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    /**
     * Human readable one-line address, used for the order snapshot.
     */
    public function toSingleLine(): string
    {
        return collect([
            $this->area,
            $this->street,
            $this->building ? 'بناية '.$this->building : null,
            $this->floor ? 'طابق '.$this->floor : null,
            $this->details,
        ])->filter()->implode('، ');
    }
}
