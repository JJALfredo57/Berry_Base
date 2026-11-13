<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('seller_expenses')) return;

        Schema::create('seller_expenses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('shop_id', 40)->index();
            $table->string('seller_id', 40)->index();
            $table->date('expense_date')->index();
            $table->string('category', 40)->index();
            $table->string('description', 300);
            $table->decimal('amount', 12, 2);
            $table->string('reference', 120)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_expenses');
    }
};
