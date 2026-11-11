<?php

namespace Tests\Feature;

use App\Services\OrderRequestService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderRequestServiceTest extends TestCase
{
    public function test_customer_can_decline_schedule_or_alternative_updates(): void
    {
        Schema::create('order_requests', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('status');
            $table->string('customer_decision_note')->nullable();
            $table->timestamp('customer_decision_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        foreach (['schedule_suggested', 'alternative_offered'] as $status) {
            $id = 'REQ-' . $status;
            DB::table('order_requests')->insert(['id' => $id, 'status' => $status]);

            $result = app(OrderRequestService::class)->customerDecline((object) [
                'id' => $id,
                'status' => $status,
            ], 'Not suitable');

            $this->assertTrue($result['ok']);
            $this->assertDatabaseHas('order_requests', [
                'id' => $id,
                'status' => 'customer_declined',
                'customer_decision_note' => 'Not suitable',
            ]);
        }
    }
}
