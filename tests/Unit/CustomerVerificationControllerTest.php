<?php

namespace Tests\Unit;

use App\Http\Controllers\Customer\VerificationController;
use Illuminate\Http\Request;
use ReflectionClass;
use Tests\TestCase;

class CustomerVerificationControllerTest extends TestCase
{
    private function runBackSideFastCheck(string $frontHash, string $backHash): array
    {
        $controller = new VerificationController();
        $method = (new ReflectionClass($controller))->getMethod('backSideFastCheck');
        $method->setAccessible(true);

        return $method->invoke($controller, Request::create('/customer/verification/scan-back', 'POST', [
            'id_front_hash' => $frontHash,
            'id_back_hash' => $backHash,
        ]));
    }

    public function test_back_side_fast_check_accepts_borderline_visual_similarity_for_review(): void
    {
        $frontHash = str_repeat('0', 240);
        $backHash = str_repeat('1', 100).str_repeat('0', 140);

        $result = $this->runBackSideFastCheck($frontHash, $backHash);

        $this->assertTrue($result['ok']);
        $this->assertSame('accepted_needs_review', $result['status']);
        $this->assertSame(100, $result['scan_result']['hash_distance']);
        $this->assertSame('front_back_visual_similarity', $result['scan_result']['review_reason']);
    }

    public function test_back_side_fast_check_accepts_distinct_back_hash(): void
    {
        $frontHash = str_repeat('0', 240);
        $backHash = str_repeat('1', 130).str_repeat('0', 110);

        $result = $this->runBackSideFastCheck($frontHash, $backHash);

        $this->assertTrue($result['ok']);
        $this->assertSame('accepted', $result['status']);
        $this->assertSame(130, $result['scan_result']['hash_distance']);
    }

    public function test_back_side_fast_check_rejects_very_same_front_hash(): void
    {
        $frontHash = str_repeat('0', 240);
        $backHash = str_repeat('1', 60).str_repeat('0', 180);

        $result = $this->runBackSideFastCheck($frontHash, $backHash);

        $this->assertFalse($result['ok']);
        $this->assertSame('front_side_again', $result['status']);
        $this->assertSame(60, $result['scan_result']['hash_distance']);
    }
}
