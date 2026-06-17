<?php

namespace Tests\Unit;

use App\Support\StockFormatter;
use PHPUnit\Framework\TestCase;

class StockFormatterTest extends TestCase
{
    public function test_it_removes_unneeded_trailing_zeroes(): void
    {
        $this->assertSame('10', StockFormatter::format(10));
        $this->assertSame('10', StockFormatter::format('10.000'));
        $this->assertSame('-4', StockFormatter::format('-4.000'));
        $this->assertSame('10.5', StockFormatter::format('10.500'));
        $this->assertSame('10.125', StockFormatter::format('10.125'));
        $this->assertSame('0', StockFormatter::format('-0.000'));
    }

    public function test_it_formats_signed_stock_differences(): void
    {
        $this->assertSame('+4', StockFormatter::signed(4));
        $this->assertSame('-3', StockFormatter::signed(-3));
        $this->assertSame('0', StockFormatter::signed(0));
    }
}
