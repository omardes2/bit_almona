<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'delivery_zone_id', 'label', 'recipient_name', 'recipient_phone',
    'address_line', 'city', 'area', 'notes', 'latitude', 'longitude', 'is_default',
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
        return collect([$this->city, $this->area, $this->address_line])->filter()->implode('، ');
    }

    /**
     * Make this the only default address of its owner.
     */
    public function makeDefault(): void
    {
        static::query()->where('user_id', $this->user_id)->whereKeyNot($this->id)->update(['is_default' => false]);
        $this->update(['is_default' => true]);
    }
}
