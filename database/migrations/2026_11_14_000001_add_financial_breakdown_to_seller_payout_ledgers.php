<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { if(!Schema::hasTable('seller_payout_ledgers')) return; Schema::table('seller_payout_ledgers',function(Blueprint $t){if(!Schema::hasColumn('seller_payout_ledgers','cake_amount'))$t->decimal('cake_amount',12,2)->default(0)->after('gross_amount');if(!Schema::hasColumn('seller_payout_ledgers','delivery_fund_amount'))$t->decimal('delivery_fund_amount',12,2)->default(0)->after('cake_amount');}); } public function down(): void { if(!Schema::hasTable('seller_payout_ledgers'))return; Schema::table('seller_payout_ledgers',function(Blueprint $t){foreach(['cake_amount','delivery_fund_amount'] as $c)if(Schema::hasColumn('seller_payout_ledgers',$c))$t->dropColumn($c);}); }};
