<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('order_requests', 'unclaimed_policy_accepted_at')) $table->timestamp('unclaimed_policy_accepted_at')->nullable();
            if (!Schema::hasColumn('order_requests', 'unclaimed_policy_version')) $table->string('unclaimed_policy_version', 30)->nullable();
        });
        Schema::table('orders', function (Blueprint $table) {
            foreach ([
                'unclaimed_grace_started_at' => fn () => $table->timestamp('unclaimed_grace_started_at')->nullable()->index(),
                'unclaimed_grace_expires_at' => fn () => $table->timestamp('unclaimed_grace_expires_at')->nullable()->index(),
                'unclaimed_contact_note' => fn () => $table->text('unclaimed_contact_note')->nullable(),
                'unclaimed_returned_at' => fn () => $table->timestamp('unclaimed_returned_at')->nullable(),
                'unclaimed_policy_accepted_at' => fn () => $table->timestamp('unclaimed_policy_accepted_at')->nullable(),
                'unclaimed_policy_version' => fn () => $table->string('unclaimed_policy_version', 30)->nullable(),
            ] as $column => $definition) {
                if (!Schema::hasColumn('orders', $column)) $definition();
            }
        });

        if (!Schema::hasTable('unclaimed_cake_listings')) {
            Schema::create('unclaimed_cake_listings', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('order_id', 12)->unique();
                $table->string('shop_id', 12)->index();
                $table->string('product_id', 12)->index();
                $table->string('selected_size', 120)->nullable();
                $table->unsignedInteger('quantity')->default(1);
                $table->decimal('resale_price', 10, 2);
                $table->string('photo_path', 1000);
                $table->timestamp('safe_until')->nullable();
                $table->string('status', 30)->default('scheduled')->index();
                $table->timestamp('published_at')->nullable();
                $table->timestamp('sold_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('unclaimed_cake_listings');
    }
};
