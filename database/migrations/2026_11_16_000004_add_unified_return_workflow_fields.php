<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('orders', function (Blueprint $t) { if (!Schema::hasColumn('orders','rider_arrived_at')) $t->timestamp('rider_arrived_at')->nullable()->index(); });
  Schema::table('post_delivery_return_cases', function (Blueprint $t) {
   foreach (['seller_decision','seller_decision_note','rider_return_proof_path'] as $c) if (!Schema::hasColumn('post_delivery_return_cases',$c)) $t->text($c)->nullable();
   foreach (['seller_decided_by_user_id'=>'pdr_seller_decided_idx','seller_received_return_by_user_id'=>'pdr_seller_received_idx'] as $c=>$index) if (!Schema::hasColumn('post_delivery_return_cases',$c)) { $t->string($c,40)->nullable(); $t->index($c,$index); }
   foreach (['seller_decided_at'=>'pdr_seller_decided_at_idx','rider_collected_return_at'=>'pdr_rider_collected_idx','rider_returned_to_seller_at'=>'pdr_rider_returned_idx','seller_received_return_at'=>'pdr_seller_received_at_idx','refund_processing_at'=>'pdr_refund_processing_idx'] as $c=>$index) if (!Schema::hasColumn('post_delivery_return_cases',$c)) { $t->timestamp($c)->nullable(); $t->index($c,$index); }
  });
 }
 public function down(): void {
  Schema::table('orders', function (Blueprint $t) { if (Schema::hasColumn('orders','rider_arrived_at')) $t->dropColumn('rider_arrived_at'); });
  Schema::table('post_delivery_return_cases', function (Blueprint $t) { foreach (['seller_decision','seller_decision_note','rider_return_proof_path','seller_decided_by_user_id','seller_received_return_by_user_id','seller_decided_at','rider_collected_return_at','rider_returned_to_seller_at','seller_received_return_at','refund_processing_at'] as $c) if (Schema::hasColumn('post_delivery_return_cases',$c)) $t->dropColumn($c); });
 }
};
