<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('order_id', 12)->index();
                $table->string('shop_id', 12)->nullable()->index();
                $table->string('product_id', 12)->nullable()->index();
                $table->string('product_name', 160);
                $table->string('image_path')->nullable();
                $table->integer('quantity')->default(1);
                $table->string('selected_size', 60)->nullable();
                $table->decimal('unit_price_snapshot', 10, 2)->default(0);
                $table->decimal('final_unit_price_snapshot', 10, 2)->default(0);
                $table->decimal('discount_amount_snapshot', 10, 2)->default(0);
                $table->string('discount_label_snapshot', 100)->nullable();
                $table->text('custom_note')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->index(['order_id', 'shop_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
