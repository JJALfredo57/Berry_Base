<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('custom_orders', function (Blueprint $t) {
            if (!Schema::hasColumn('custom_orders', 'cake_name'))
                $t->string('cake_name', 150)->nullable()->after('guest_phone');
            if (!Schema::hasColumn('custom_orders', 'flavor'))
                $t->string('flavor', 100)->nullable()->after('cake_name');
            if (!Schema::hasColumn('custom_orders', 'size'))
                $t->string('size', 100)->nullable()->after('flavor');
            if (!Schema::hasColumn('custom_orders', 'layers'))
                $t->string('layers', 100)->nullable()->after('size');
            if (!Schema::hasColumn('custom_orders', 'design_complexity'))
                $t->string('design_complexity', 100)->nullable()->after('layers');
            if (!Schema::hasColumn('custom_orders', 'dedication'))
                $t->text('dedication')->nullable()->after('design_complexity');
            if (!Schema::hasColumn('custom_orders', 'time_slot'))
                $t->string('time_slot', 100)->nullable()->after('dedication');
            if (!Schema::hasColumn('custom_orders', 'estimated_price'))
                $t->decimal('estimated_price', 10, 2)->nullable()->after('time_slot');
            if (!Schema::hasColumn('custom_orders', 'price_breakdown'))
                $t->json('price_breakdown')->nullable()->after('estimated_price');
            if (!Schema::hasColumn('custom_orders', 'customer_confirmed_at'))
                $t->timestamp('customer_confirmed_at')->nullable()->after('price_breakdown');
        });
    }

    public function down(): void
    {
        Schema::table('custom_orders', function (Blueprint $t) {
            $t->dropColumn([
                'cake_name', 'flavor', 'size', 'layers', 'design_complexity',
                'dedication', 'time_slot', 'estimated_price', 'price_breakdown',
                'customer_confirmed_at',
            ]);
        });
    }
};
