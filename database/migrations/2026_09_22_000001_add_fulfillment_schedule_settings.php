<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('site_settings', 'ready_made_prep_minutes')) {
                $table->unsignedSmallInteger('ready_made_prep_minutes')->default(90);
            }
            if (!Schema::hasColumn('site_settings', 'custom_cake_prep_minutes')) {
                $table->unsignedSmallInteger('custom_cake_prep_minutes')->default(0);
            }
            if (!Schema::hasColumn('site_settings', 'pickup_buffer_minutes')) {
                $table->unsignedSmallInteger('pickup_buffer_minutes')->default(0);
            }
            if (!Schema::hasColumn('site_settings', 'delivery_base_buffer_minutes')) {
                $table->unsignedSmallInteger('delivery_base_buffer_minutes')->default(30);
            }
            if (!Schema::hasColumn('site_settings', 'delivery_minutes_per_km')) {
                $table->unsignedSmallInteger('delivery_minutes_per_km')->default(5);
            }
        });

        if (!Schema::hasTable('fulfillment_time_slots')) {
            Schema::create('fulfillment_time_slots', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('shop_id', 12)->nullable()->index();
                $table->string('label', 80);
                $table->time('start_time');
                $table->time('end_time');
                $table->string('fulfillment_method', 20)->default('both');
                $table->string('order_type', 20)->default('both');
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        $this->seedDefaultSlots();
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_time_slots');

        Schema::table('site_settings', function (Blueprint $table) {
            foreach ([
                'delivery_minutes_per_km',
                'delivery_base_buffer_minutes',
                'pickup_buffer_minutes',
                'custom_cake_prep_minutes',
                'ready_made_prep_minutes',
            ] as $column) {
                if (Schema::hasColumn('site_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function seedDefaultSlots(): void
    {
        if (!Schema::hasTable('fulfillment_time_slots')) {
            return;
        }

        $defaults = [
            ['9:00 AM - 11:00 AM', '09:00:00', '11:00:00', 1],
            ['11:00 AM - 1:00 PM', '11:00:00', '13:00:00', 2],
            ['1:00 PM - 3:00 PM', '13:00:00', '15:00:00', 3],
            ['3:00 PM - 5:00 PM', '15:00:00', '17:00:00', 4],
            ['5:00 PM - 7:00 PM', '17:00:00', '19:00:00', 5],
        ];

        $shopIds = DB::table('shops')->pluck('id')->filter()->values();
        foreach ($shopIds as $shopId) {
            $hasSlots = DB::table('fulfillment_time_slots')->where('shop_id', $shopId)->exists();
            if ($hasSlots) {
                continue;
            }

            foreach ($defaults as [$label, $start, $end, $sort]) {
                DB::table('fulfillment_time_slots')->insert([
                    'shop_id' => $shopId,
                    'label' => $label,
                    'start_time' => $start,
                    'end_time' => $end,
                    'fulfillment_method' => 'both',
                    'order_type' => 'both',
                    'is_active' => true,
                    'sort_order' => $sort,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
