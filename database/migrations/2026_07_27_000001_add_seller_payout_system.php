<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('platform_settings')) {
            Schema::table('platform_settings', function (Blueprint $t) {
                if (!Schema::hasColumn('platform_settings', 'payout_mode')) {
                    $t->string('payout_mode', 20)->default('manual')->after('commission_rate_verified');
                }
                if (!Schema::hasColumn('platform_settings', 'payout_hold_days')) {
                    $t->unsignedTinyInteger('payout_hold_days')->default(3)->after('payout_mode');
                }
                if (!Schema::hasColumn('platform_settings', 'payout_minimum_amount')) {
                    $t->decimal('payout_minimum_amount', 10, 2)->default(500)->after('payout_hold_days');
                }
                if (!Schema::hasColumn('platform_settings', 'payout_schedule')) {
                    $t->string('payout_schedule', 20)->default('weekly')->after('payout_minimum_amount');
                }
                if (!Schema::hasColumn('platform_settings', 'payout_first_approval_required')) {
                    $t->boolean('payout_first_approval_required')->default(true)->after('payout_schedule');
                }
                if (!Schema::hasColumn('platform_settings', 'payout_auto_paused')) {
                    $t->boolean('payout_auto_paused')->default(true)->after('payout_first_approval_required');
                }
            });
        }

        if (Schema::hasTable('shops')) {
            Schema::table('shops', function (Blueprint $t) {
                if (!Schema::hasColumn('shops', 'payout_method')) {
                    $t->string('payout_method', 30)->nullable()->after('gcash_number');
                }
                if (!Schema::hasColumn('shops', 'payout_institution')) {
                    $t->string('payout_institution', 120)->nullable()->after('payout_method');
                }
                if (!Schema::hasColumn('shops', 'payout_account_name')) {
                    $t->string('payout_account_name', 150)->nullable()->after('payout_institution');
                }
                if (!Schema::hasColumn('shops', 'payout_account_number')) {
                    $t->string('payout_account_number', 80)->nullable()->after('payout_account_name');
                }
                if (!Schema::hasColumn('shops', 'payout_details_verified')) {
                    $t->boolean('payout_details_verified')->default(false)->after('payout_account_number');
                }
                if (!Schema::hasColumn('shops', 'payout_paused')) {
                    $t->boolean('payout_paused')->default(false)->after('payout_details_verified');
                }
            });
        }

        if (!Schema::hasTable('seller_payout_ledgers')) {
            Schema::create('seller_payout_ledgers', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('order_id', 12)->unique();
                $t->string('shop_id', 12)->index();
                $t->string('seller_id', 12)->nullable()->index();
                $t->decimal('gross_amount', 10, 2)->default(0);
                $t->decimal('commission_base', 10, 2)->default(0);
                $t->decimal('commission_rate', 5, 2)->default(0);
                $t->decimal('commission_amount', 10, 2)->default(0);
                $t->decimal('seller_net_amount', 10, 2)->default(0);
                $t->string('status', 30)->default('pending')->index();
                $t->timestamp('release_at')->nullable()->index();
                $t->unsignedBigInteger('payout_id')->nullable()->index();
                $t->text('notes')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('seller_payouts')) {
            Schema::create('seller_payouts', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('shop_id', 12)->index();
                $t->string('seller_id', 12)->nullable()->index();
                $t->string('mode', 20)->default('manual');
                $t->string('status', 30)->default('requested')->index();
                $t->decimal('gross_amount', 10, 2)->default(0);
                $t->decimal('commission_amount', 10, 2)->default(0);
                $t->decimal('net_amount', 10, 2)->default(0);
                $t->string('payout_method', 30)->nullable();
                $t->string('payout_institution', 120)->nullable();
                $t->string('payout_account_name', 150)->nullable();
                $t->string('payout_account_number', 80)->nullable();
                $t->string('reference_number', 120)->nullable();
                $t->string('paymongo_transfer_id', 120)->nullable();
                $t->text('admin_note')->nullable();
                $t->timestamp('requested_at')->nullable();
                $t->timestamp('processed_at')->nullable();
                $t->timestamp('paid_at')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('seller_payout_items')) {
            Schema::create('seller_payout_items', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('payout_id')->index();
                $t->unsignedBigInteger('ledger_id')->index();
                $t->string('order_id', 12);
                $t->decimal('net_amount', 10, 2)->default(0);
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_payout_items');
        Schema::dropIfExists('seller_payouts');
        Schema::dropIfExists('seller_payout_ledgers');

        if (Schema::hasTable('shops')) {
            Schema::table('shops', function (Blueprint $t) {
                foreach ([
                    'payout_method','payout_institution','payout_account_name',
                    'payout_account_number','payout_details_verified','payout_paused',
                ] as $col) {
                    if (Schema::hasColumn('shops', $col)) $t->dropColumn($col);
                }
            });
        }

        if (Schema::hasTable('platform_settings')) {
            Schema::table('platform_settings', function (Blueprint $t) {
                foreach ([
                    'payout_mode','payout_hold_days','payout_minimum_amount',
                    'payout_schedule','payout_first_approval_required','payout_auto_paused',
                ] as $col) {
                    if (Schema::hasColumn('platform_settings', $col)) $t->dropColumn($col);
                }
            });
        }
    }
};
