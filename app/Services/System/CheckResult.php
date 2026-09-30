<?php

namespace App\Services\System;

/**
 * One production-readiness check. Messages are written for the store owner
 * and never contain secrets (no passwords, keys, tokens or DSNs).
 */
final readonly class CheckResult
{
    public const OK = 'ok';

    public const WARNING = 'warning';

    public const CRITICAL = 'critical';

    public function __construct(
        public string $key,
        public string $label,
        public string $status,
        public string $message,
    ) {}

    public static function ok(string $key, string $label, string $message = 'سليم'): self
    {
        return new self($key, $label, self::OK, $message);
    }

    public static function warning(string $key, string $label, string $message): self
    {
        return new self($key, $label, self::WARNING, $message);
    }

    public static function critical(string $key, string $label, string $message): self
    {
        return new self($key, $label, self::CRITICAL, $message);
    }

    public function passed(): bool
    {
        return $this->status === self::OK;
    }

    public function isCritical(): bool
    {
        return $this->status === self::CRITICAL;
    }
}
