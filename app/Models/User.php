<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\AdminRole;
use App\Enums\UserType;
use App\Support\PhoneNumber;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'phone', 'email', 'password', 'type', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'type' => UserType::class,
            'status' => AccountStatus::class,
        ];
    }

    /**
     * Phone numbers are always stored in the normalised 05XXXXXXXX format.
     */
    protected function phone(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => PhoneNumber::normalize($value));
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    public function admin(): HasOne
    {
        return $this->hasOne(Admin::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isAdmin(): bool
    {
        return $this->type === UserType::Admin;
    }

    public function isCustomer(): bool
    {
        return $this->type === UserType::Customer;
    }

    public function isActive(): bool
    {
        return $this->status === AccountStatus::Active;
    }

    /** Safe on freshly created models that never loaded the column. */
    public function hasVerifiedPhone(): bool
    {
        return filled($this->attributes['phone_verified_at'] ?? null);
    }

    public function hasAdminRole(AdminRole ...$roles): bool
    {
        return $this->isAdmin()
            && $this->admin !== null
            && in_array($this->admin->role, $roles, true);
    }

    public function scopeCustomers(Builder $query): void
    {
        $query->where('type', UserType::Customer);
    }

    public function scopeAdmins(Builder $query): void
    {
        $query->where('type', UserType::Admin);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', AccountStatus::Active);
    }
}
