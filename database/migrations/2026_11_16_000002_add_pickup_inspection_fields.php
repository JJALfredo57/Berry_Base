<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('orders', function(Blueprint $t){ if(!Schema::hasColumn('orders','pickup_inspected_at'))$t->timestamp('pickup_inspected_at')->nullable(); if(!Schema::hasColumn('orders','pickup_inspected_by_user_id'))$t->string('pickup_inspected_by_user_id',40)->nullable(); }); if(Schema::hasTable('post_delivery_return_cases'))Schema::table('post_delivery_return_cases',function(Blueprint $t){if(!Schema::hasColumn('post_delivery_return_cases','review_stage'))$t->string('review_stage',32)->nullable()->index();}); }
 public function down(): void { Schema::table('orders',function(Blueprint $t){foreach(['pickup_inspected_at','pickup_inspected_by_user_id'] as $c)if(Schema::hasColumn('orders',$c))$t->dropColumn($c);}); if(Schema::hasTable('post_delivery_return_cases'))Schema::table('post_delivery_return_cases',function(Blueprint $t){if(Schema::hasColumn('post_delivery_return_cases','review_stage'))$t->dropColumn('review_stage');}); }
};
