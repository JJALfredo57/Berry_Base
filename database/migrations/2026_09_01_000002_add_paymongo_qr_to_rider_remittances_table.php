<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('rider_remittances')) return;

        Schema::table('rider_remittances', function (Blueprint $t) {
            if (!Schema::hasColumn('rider_remittances', 'paymongo_payment_intent_id')) {
                $t->string('paymongo_payment_intent_id', 120)->nullable()->index()->after('receipt_path');
            }
            if (!Schema::hasColumn('rider_remittances', 'paymongo_payment_method_id')) {
                $t->string('paymongo_payment_method_id', 120)->nullable()->after('paymongo_payment_intent_id');
            }
            if (!Schema::hasColumn('rider_remittances', 'paymongo_client_key')) {
                $t->text('paymongo_client_key')->nullable()->after('paymongo_payment_method_id');
            }
            if (!Schema::hasColumn('rider_remittances', 'paymongo_qr_image')) {
                $t->longText('paymongo_qr_image')->nullable()->after('paymongo_client_key');
            }
            if (!Schema::hasColumn('rider_remittances', 'paymongo_action_url')) {
                $t->text('paymongo_action_url')->nullable()->after('paymongo_qr_image');
            }
            if (!Schema::hasColumn('rider_remittances', 'paymongo_status')) {
                $t->string('paymongo_status', 40)->nullable()->index()->after('paymongo_action_url');
            }
            if (!Schema::hasColumn('rider_remittances', 'paymongo_reference')) {
                $t->string('paymongo_reference', 120)->nullable()->index()->after('paymongo_status');
            }
            if (!Schema::hasColumn('rider_remittances', 'paymongo_expires_at')) {
                $t->timestamp('paymongo_expires_at')->nullable()->index()->after('paymongo_reference');
            }
            if (!Schema::hasColumn('rider_remittances', 'paymongo_paid_at')) {
                $t->timestamp('paymongo_paid_at')->nullable()->index()->after('paymongo_expires_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('rider_remittances')) return;

        Schema::table('rider_remittances', function (Blueprint $t) {
            foreach ([
                'paymongo_paid_at',
                'paymongo_expires_at',
                'paymongo_reference',
                'paymongo_status',
                'paymongo_action_url',
                'paymongo_qr_image',
                'paymongo_client_key',
                'paymongo_payment_method_id',
                'paymongo_payment_intent_id',
            ] as $column) {
                if (Schema::hasColumn('rider_remittances', $column)) {
                    $t->dropColumn($column);
                }
            }
        });
    }
};
