<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\DisplayFormatter;
use Tests\TestCase;

class DisplayFormatterTest extends TestCase
{
    public function test_formats_large_integer_rupiah_without_losing_digits(): void
    {
        $this->assertSame('Rp 9.007.199.254.740.993', DisplayFormatter::rupiah(9_007_199_254_740_993));
        $this->assertSame('Rp 0', DisplayFormatter::rupiah(0));
        $this->assertSame('—', DisplayFormatter::rupiah(null));
    }
}
