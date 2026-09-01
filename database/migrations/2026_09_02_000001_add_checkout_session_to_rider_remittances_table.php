<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('rider_remittances')) return;

        Schema::table('rider_remittances', function (Blueprint $t) {
            if (!Schema::hasColumn('rider_remittances', 'paymongo_checkout_session_id')) {
                $t->string('paymongo_checkout_session_id', 120)->nullable()->index()->after('paymongo_payment_method_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('rider_remittances')) return;

        Schema::table('rider_remittances', function (Blueprint $t) {
            if (Schema::hasColumn('rider_remittances', 'paymongo_checkout_session_id')) {
                $t->dropColumn('paymongo_checkout_session_id');
            }
        });
    }
};
