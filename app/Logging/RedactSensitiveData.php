<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;

/**
 * Log channel tap: masks secrets before anything is written. Context keys
 * that look sensitive are replaced, and "key=value" / bearer-token patterns
 * inside messages are masked. Applied to every file/stream channel in
 * config/logging.php.
 */
class RedactSensitiveData
{
    public const MASK = '[REDACTED]';

    /** Context keys never written in clear text. */
    public const SENSITIVE_KEYS = '/(pass(word)?|secret|token|api[_-]?key|otp|code_hash|^code$|authorization|cookie|session|card|cvv|pin$)/i';

    public function __invoke(Logger $logger): void
    {
        $logger->getLogger()->pushProcessor(fn (LogRecord $record) => $record->with(
            message: self::redactString($record->message),
            context: self::redactArray($record->context),
            extra: self::redactArray($record->extra),
        ));
    }

    /**
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    public static function redactArray(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEYS, $key)) {
                $values[$key] = self::MASK;
            } elseif (is_array($value)) {
                $values[$key] = self::redactArray($value);
            } elseif (is_string($value)) {
                $values[$key] = self::redactString($value);
            }
        }

        return $values;
    }

    public static function redactString(string $value): string
    {
        return (string) preg_replace(
            [
                '/\b(password|passwd|secret|token|api[_-]?key|otp|code)\b(["\']?\s*[:=]\s*["\']?)[^\s"\'&,}]+/i',
                '/\bBearer\s+[A-Za-z0-9\-._~+\/]+=*/i',
            ],
            ['$1$2'.self::MASK, 'Bearer '.self::MASK],
            $value,
        );
    }
}
