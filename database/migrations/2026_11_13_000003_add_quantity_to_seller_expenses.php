<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { if (Schema::hasTable('seller_expenses') && !Schema::hasColumn('seller_expenses','quantity')) Schema::table('seller_expenses', fn(Blueprint $t) => $t->unsignedInteger('quantity')->default(1)->after('description')); } public function down(): void { if (Schema::hasTable('seller_expenses') && Schema::hasColumn('seller_expenses','quantity')) Schema::table('seller_expenses', fn(Blueprint $t) => $t->dropColumn('quantity')); } };
