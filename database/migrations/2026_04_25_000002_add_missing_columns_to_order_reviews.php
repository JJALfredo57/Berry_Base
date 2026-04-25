<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_reviews', function (Blueprint $t) {
            if (!Schema::hasColumn('order_reviews', 'user_id'))
                $t->string('user_id', 12)->nullable()->after('order_id');
            if (!Schema::hasColumn('order_reviews', 'guest_name'))
                $t->string('guest_name', 100)->nullable()->after('user_id');
            // Migration uses review_text, code uses review — add review as alias column
            if (!Schema::hasColumn('order_reviews', 'review'))
                $t->text('review')->nullable()->after('rating');
            if (!Schema::hasColumn('order_reviews', 'rider_rating'))
                $t->unsignedTinyInteger('rider_rating')->nullable()->after('review');
            if (!Schema::hasColumn('order_reviews', 'image_path'))
                $t->string('image_path')->nullable()->after('rider_rating');
        });
    }

    public function down(): void
    {
        Schema::table('order_reviews', function (Blueprint $t) {
            $t->dropColumn(['user_id', 'guest_name', 'review', 'rider_rating', 'image_path']);
        });
    }
};
