<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up():void {
 if(!Schema::hasTable('rider_delivery_earnings'))Schema::create('rider_delivery_earnings',function(Blueprint $t){$t->bigIncrements('id');$t->unsignedBigInteger('rider_id')->index();$t->string('order_id',40)->index();$t->unsignedBigInteger('return_case_id')->nullable()->index();$t->decimal('amount',12,2);$t->string('source',32);$t->string('status',24)->default('available')->index();$t->string('event_key',100)->unique();$t->timestamps();});
 if(!Schema::hasTable('rider_payout_requests'))Schema::create('rider_payout_requests',function(Blueprint $t){$t->bigIncrements('id');$t->unsignedBigInteger('rider_id')->index();$t->decimal('amount',12,2);$t->string('gcash_name',160);$t->string('gcash_number',20);$t->string('status',24)->default('requested')->index();$t->string('reference_number',120)->nullable()->unique();$t->string('receipt_path')->nullable();$t->string('receipt_hash',64)->nullable();$t->timestamp('paid_at')->nullable();$t->string('paid_by_user_id',40)->nullable();$t->timestamps();});
 if(Schema::hasTable('riders'))Schema::table('riders',function(Blueprint $t){if(!Schema::hasColumn('riders','gcash_name'))$t->string('gcash_name',160)->nullable();if(!Schema::hasColumn('riders','gcash_number'))$t->string('gcash_number',20)->nullable();});
 } public function down():void {Schema::dropIfExists('rider_payout_requests');Schema::dropIfExists('rider_delivery_earnings');if(Schema::hasTable('riders'))Schema::table('riders',function(Blueprint $t){foreach(['gcash_name','gcash_number'] as $c)if(Schema::hasColumn('riders',$c))$t->dropColumn($c);});} };
