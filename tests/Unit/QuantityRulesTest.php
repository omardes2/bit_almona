<?php

namespace Tests\Unit;

use App\Services\Cart\QuantityRules;
use PHPUnit\Framework\TestCase;

class QuantityRulesTest extends TestCase
{
    public function test_parsing_is_strict_and_exact(): void
    {
        $this->assertSame(1500, QuantityRules::toMilli('1.5'));
        $this->assertSame(250, QuantityRules::toMilli('0.25'));
        $this->assertSame(3000, QuantityRules::toMilli(3));
        $this->assertNull(QuantityRules::toMilli('-1'));
        $this->assertNull(QuantityRules::toMilli('1.2345'));
        $this->assertNull(QuantityRules::toMilli('1e3'));
        $this->assertNull(QuantityRules::toMilli('abc'));
        $this->assertNull(QuantityRules::toMilli(['1']));
        $this->assertSame('1.25', QuantityRules::fromMilli(1250));
        $this->assertSame('2', QuantityRules::fromMilli(2000));
    }

    public function test_valid_quantities_follow_min_step_and_stock(): void
    {
        $rules = new QuantityRules(min: 500, step: 250, max: 2100);

        $this->assertTrue($rules->isValid(500));
        $this->assertTrue($rules->isValid(750));
        $this->assertFalse($rules->isValid(250));   // below minimum
        $this->assertFalse($rules->isValid(600));   // off step
        $this->assertFalse($rules->isValid(2250));  // above stock
        $this->assertSame(2000, $rules->maxAllowed());
        $this->assertSame(1250, $rules->clamp(1300));
        $this->assertNull($rules->clamp(400));
        $this->assertNull((new QuantityRules(1000, 1000, 500))->maxAllowed());
    }
}
