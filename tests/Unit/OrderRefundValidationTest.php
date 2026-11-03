<?php

namespace Tests\Unit;

use App\Services\MobileNotificationService;
use App\Services\OrderRefundService;
use Mockery;
use Tests\TestCase;

class OrderRefundValidationTest extends TestCase
{
    public function test_refund_details_require_a_full_name_and_valid_gcash_number(): void
    {
        $service = new OrderRefundService(Mockery::mock(MobileNotificationService::class));

        $this->assertTrue($service->validateGcashAccountName('Juan Dela Cruz'));
        $this->assertTrue($service->validateGcashAccountName("Maria O'Connor"));
        $this->assertFalse($service->validateGcashAccountName('ABC'));
        $this->assertFalse($service->validateGcashAccountName('123'));
        $this->assertFalse($service->validateGcashAccountName('jkdfsdjf 46i8y45v'));

        $this->assertTrue($service->validateGcashNumber('09171234567'));
        $this->assertTrue($service->validateGcashNumber('+639171234567'));
        $this->assertFalse($service->validateGcashNumber('0917123456'));
        $this->assertFalse($service->validateGcashNumber('abc09171234567'));
    }
}
