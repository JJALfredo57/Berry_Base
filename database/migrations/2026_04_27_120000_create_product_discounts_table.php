<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('product_discounts')) {
            Schema::create('product_discounts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('product_id', 12)->index();
                $table->string('label', 100)->nullable();
                $table->string('discount_type', 20);
                $table->decimal('discount_value', 10, 2)->default(0);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();

                $table->index(['product_id', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_discounts');
    }
};
