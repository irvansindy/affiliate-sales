<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\CommissionCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CommissionCalculatorTest extends TestCase
{
    private CommissionCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new CommissionCalculator;
    }

    public function test_ten_percent_of_base_amount(): void
    {
        $this->assertSame(500_000, $this->calculator->amount(5_000_000, '10'));
        $this->assertSame(500_000, $this->calculator->amount(5_000_000, '10.00'));
    }

    public function test_rounds_half_up_to_whole_rupiah(): void
    {
        $this->assertSame(500_001, $this->calculator->amount(5_000_005, '10')); // 500.000,5 -> naik
        $this->assertSame(100_000, $this->calculator->amount(1_000_004, '10')); // 100.000,4 -> turun
        $this->assertSame(1, $this->calculator->amount(5, '10'));               // 0,5 -> 1
        $this->assertSame(0, $this->calculator->amount(4, '10'));               // 0,4 -> 0
    }

    public function test_handles_fractional_rates(): void
    {
        $this->assertSame(125_000, $this->calculator->amount(1_000_000, '12.5'));
        $this->assertSame(125_000, $this->calculator->amount(1_000_000, 12.50));
    }

    public function test_edge_values(): void
    {
        $this->assertSame(0, $this->calculator->amount(0, '10'));
        $this->assertSame(0, $this->calculator->amount(1_000_000, '0'));
        $this->assertSame(1_000_000, $this->calculator->amount(1_000_000, '100'));
    }

    public function test_large_amount_has_no_float_drift(): void
    {
        $this->assertSame(100_000_000_000, $this->calculator->amount(1_000_000_000_000, '10'));
    }

    public function test_rejects_invalid_input(): void
    {
        foreach ([[-1, '10'], [1000, '-1'], [1000, '100.01']] as [$base, $rate]) {
            try {
                $this->calculator->amount($base, $rate);
                $this->fail("Seharusnya melempar exception untuk base={$base}, rate={$rate}");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
