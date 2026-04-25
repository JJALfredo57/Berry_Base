<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('custom_orders', function (Blueprint $t) {
            if (!Schema::hasColumn('custom_orders', 'review_status'))
                $t->string('review_status', 30)->default('pending')->after('status');
            if (!Schema::hasColumn('custom_orders', 'admin_price'))
                $t->decimal('admin_price', 10, 2)->nullable()->after('quoted_price');
            if (!Schema::hasColumn('custom_orders', 'admin_comment'))
                $t->text('admin_comment')->nullable()->after('admin_price');
            if (!Schema::hasColumn('custom_orders', 'price_confirmed'))
                $t->string('price_confirmed', 30)->nullable()->after('admin_comment');
            if (!Schema::hasColumn('custom_orders', 'progress_image'))
                $t->string('progress_image')->nullable()->after('price_confirmed');
            if (!Schema::hasColumn('custom_orders', 'progress_message'))
                $t->text('progress_message')->nullable()->after('progress_image');
        });
    }

    public function down(): void
    {
        Schema::table('custom_orders', function (Blueprint $t) {
            $t->dropColumn([
                'review_status', 'admin_price', 'admin_comment',
                'price_confirmed', 'progress_image', 'progress_message',
            ]);
        });
    }
};
