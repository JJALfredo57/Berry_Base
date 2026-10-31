<?php

namespace Tests\Unit;

use App\Services\HighValueOrderPolicyService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HighValueOrderPolicyServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('customer_verifications');
        Schema::create('customer_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('status');
            $table->timestamps();
        });
    }

    public function test_unverified_customer_requires_full_gcash_at_threshold(): void
    {
        $service = app(HighValueOrderPolicyService::class);

        $this->assertTrue($service->validatePayment('USR1', 9999.99, 'COP')['ok']);
        $this->assertFalse($service->validatePayment('USR1', 10000.00, 'COP')['ok']);
        $this->assertTrue($service->validatePayment('USR1', 10000.00, 'GCash')['ok']);
    }

    public function test_verified_customer_keeps_standard_deposit_option(): void
    {
        DB::table('customer_verifications')->insert([
            'user_id' => 'USR2', 'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = app(HighValueOrderPolicyService::class)->validatePayment('USR2', 25000.00, 'COD');

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['requires_full_gcash']);
    }
}
