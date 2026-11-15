<?php

namespace Tests\Unit;

use App\Services\PayoutReceiptScanService;
use ReflectionMethod;
use Tests\TestCase;

class PayoutReceiptScanServiceTest extends TestCase
{
    public function test_it_normalizes_local_and_international_philippine_gcash_numbers(): void
    {
        $scanner = app(PayoutReceiptScanService::class);
        $method = new ReflectionMethod($scanner, 'normalizedPhilippineMobile');

        $this->assertSame('9104587030', $method->invoke($scanner, '09104587030'));
        $this->assertSame('9104587030', $method->invoke($scanner, '+63 910 458 7030'));
        $this->assertSame('', $method->invoke($scanner, '0910458703'));
    }
}
