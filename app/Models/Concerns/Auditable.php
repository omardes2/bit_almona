<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Records created / updated / deleted / restored events in audit_logs.
 * Hidden attributes and those listed in $auditExclude are never stored.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => $model->writeAudit('created', [], $model->auditableValues(array_keys($model->getAttributes()))));

        static::updated(function (Model $model) {
            $keys = array_keys($model->getChanges());
            $new = $model->auditableValues($keys);

            if ($new === []) {
                return;
            }

            $model->writeAudit('updated', $model->auditableValues(array_keys($new), original: true), $new);
        });

        static::deleted(function (Model $model) {
            $event = method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting() ? 'deleted' : 'force_deleted';
            $model->writeAudit($event, $model->auditableValues(array_keys($model->getAttributes()), original: true), []);
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(fn (Model $model) => $model->writeAudit('restored', [], ['deleted_at' => null]));
        }
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('id');
    }

    /**
     * Cast values (e.g. "5.00" for decimals, enum values, ISO dates) so the
     * log looks the same on every database driver.
     *
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    protected function auditableValues(array $keys, bool $original = false): array
    {
        $excluded = array_merge(
            ['created_at', 'updated_at', 'password', 'remember_token'],
            $this->getHidden(),
            property_exists($this, 'auditExclude') ? $this->auditExclude : [],
        );

        $values = [];

        foreach (array_diff($keys, $excluded) as $key) {
            $value = $original ? $this->getOriginal($key) : $this->getAttribute($key);

            $values[$key] = match (true) {
                $value instanceof \BackedEnum => $value->value,
                $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
                default => $value,
            };
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    protected function writeAudit(string $event, array $old, array $new): void
    {
        AuditLog::record($this, $event, $old, $new);
    }
}
