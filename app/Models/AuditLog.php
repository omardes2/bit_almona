<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['user_id', 'event', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'ip_address', 'user_agent'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     * Record an explicit business event (e.g. an order status change) that is
     * not a plain model create/update/delete.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public static function record(Model $model, string $event, array $old = [], array $new = []): self
    {
        $request = app()->runningInConsole() ? null : request();

        return static::create([
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'old_values' => $old ? self::redact($old) : null,
            'new_values' => $new ? self::redact($new) : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }

    /** Keys never shown (or stored) in clear text. */
    public const SENSITIVE = '/(password|token|secret|api[_-]?key|otp|code_hash|remember|session|card|cvv)/i';

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>
     */
    public static function redact(?array $values): array
    {
        $clean = [];

        foreach ($values ?? [] as $key => $value) {
            $clean[$key] = preg_match(self::SENSITIVE, (string) $key)
                ? '••••••'
                : (is_array($value) ? self::redact($value) : $value);
        }

        return $clean;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
