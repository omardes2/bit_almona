<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'whatsapp', 'notes'])]
class Customer extends Model
{
    use HasFactory;

    protected function whatsapp(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => filled($value) ? PhoneNumber::normalize($value) : null);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
