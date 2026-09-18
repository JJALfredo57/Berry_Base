<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('vouchers')) {
            Schema::table('vouchers', function (Blueprint $table) {
                if (!Schema::hasColumn('vouchers', 'audience')) {
                    $table->string('audience', 30)->default('public')->after('shop_id')->index();
                }
                if (!Schema::hasColumn('vouchers', 'created_by_role')) {
                    $table->string('created_by_role', 30)->nullable()->after('is_active');
                }
                if (!Schema::hasColumn('vouchers', 'created_by_id')) {
                    $table->string('created_by_id', 12)->nullable()->after('created_by_role');
                }
            });
        }

        if (!Schema::hasTable('voucher_assignments')) {
            Schema::create('voucher_assignments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('voucher_id')->index();
                $table->string('user_id', 12)->index();
                $table->string('assigned_by_role', 30)->nullable();
                $table->string('assigned_by_id', 12)->nullable();
                $table->string('status', 30)->default('active')->index();
                $table->text('assignment_note')->nullable();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->unique(['voucher_id', 'user_id'], 'voucher_assignment_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_assignments');
        if (Schema::hasTable('vouchers')) {
            Schema::table('vouchers', function (Blueprint $table) {
                foreach (['created_by_id', 'created_by_role', 'audience'] as $column) {
                    if (Schema::hasColumn('vouchers', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
