<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A full http(s) URL, or an internal path that starts with a single "/".
 * Blocks javascript:, data: and protocol-relative (//evil.com) links.
 */
class SafeLink implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $value = (string) $value;

        $isInternalPath = preg_match('#^/(?!/)[^\s<>"\']*$#u', $value) === 1;
        $isHttpUrl = filter_var($value, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true);

        if (! $isInternalPath && ! $isHttpUrl) {
            $fail('الرابط يجب أن يبدأ بـ https:// أو يكون مسارًا داخليًا يبدأ بـ / مثل /offers');
        }
    }
}
