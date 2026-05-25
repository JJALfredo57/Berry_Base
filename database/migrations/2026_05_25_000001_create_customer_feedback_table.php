<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('customer_feedback')) {
            Schema::create('customer_feedback', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('user_id', 12)->nullable()->index();
                $table->string('name', 120)->nullable();
                $table->string('email', 150)->nullable();
                $table->string('category', 40)->default('suggestion')->index();
                $table->string('title', 120);
                $table->text('message');
                $table->string('status', 20)->default('open')->index();
                $table->text('admin_note')->nullable();
                $table->string('resolved_by', 12)->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_feedback');
    }
};
