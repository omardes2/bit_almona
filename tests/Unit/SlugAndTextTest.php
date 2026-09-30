<?php

namespace Tests\Unit;

use App\Support\ArabicText;
use App\Support\Decimal;
use App\Support\Slug;
use PHPUnit\Framework\TestCase;

class SlugAndTextTest extends TestCase
{
    public function test_slugs_keep_arabic_and_clean_everything_else(): void
    {
        $this->assertSame('جبنة-الخيرات-24-مثلث', Slug::make('  جبنة الخيرات 24 مثلث  '));
        $this->assertSame('olive-oil-1l', Slug::make('Olive Oil / 1L'));
        $this->assertSame('قهوة-cafe', Slug::make('قَهْوَة Café!!'));
        $this->assertMatchesRegularExpression(Slug::PATTERN, Slug::make('ألبان & أجبان'));
    }

    public function test_arabic_normalization_for_search(): void
    {
        $this->assertSame(ArabicText::normalize('جبنة أحمد إلى'), ArabicText::normalize('جبنه احمد الي'));
        $this->assertSame('قهوه', ArabicText::normalize('قَهْـوَة'));
    }

    public function test_decimal_trim(): void
    {
        $this->assertSame('5', Decimal::trim('5.000'));
        $this->assertSame('1.5', Decimal::trim('1.500'));
        $this->assertSame('18', Decimal::trim('18.00'));
        $this->assertSame('0', Decimal::trim(null));
    }
}
