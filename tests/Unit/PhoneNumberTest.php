<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    public static function phones(): array
    {
        return [
            'local' => ['0599123456', '0599123456'],
            'spaces and dashes' => ['059-912 3456', '0599123456'],
            '+970' => ['+970599123456', '0599123456'],
            '00970' => ['00970599123456', '0599123456'],
            '+972' => ['+972 56 912 3456', '0569123456'],
            'without leading zero' => ['599123456', '0599123456'],
            'arabic digits' => ['٠٥٩٩١٢٣٤٥٦', '0599123456'],
        ];
    }

    #[DataProvider('phones')]
    public function test_it_normalizes_palestinian_mobile_numbers(string $input, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($input));
        $this->assertTrue(PhoneNumber::isValid($input));
    }

    public function test_it_rejects_invalid_numbers(): void
    {
        $this->assertFalse(PhoneNumber::isValid('12345'));
        $this->assertFalse(PhoneNumber::isValid('0229123456')); // landline
        $this->assertFalse(PhoneNumber::isValid(null));
    }
}
