<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $t) {
            if (!Schema::hasColumn('platform_settings', 'platform_bg_type'))
                $t->string('platform_bg_type', 20)->default('color')->after('platform_primary_color');
            if (!Schema::hasColumn('platform_settings', 'platform_bg_color'))
                $t->string('platform_bg_color', 20)->default('#FFF8F8')->after('platform_bg_type');
            if (!Schema::hasColumn('platform_settings', 'platform_bg_gradient_start'))
                $t->string('platform_bg_gradient_start', 20)->default('#fff7fb')->after('platform_bg_color');
            if (!Schema::hasColumn('platform_settings', 'platform_bg_gradient_end'))
                $t->string('platform_bg_gradient_end', 20)->default('#ffe3f1')->after('platform_bg_gradient_start');
            if (!Schema::hasColumn('platform_settings', 'platform_bg_image'))
                $t->string('platform_bg_image')->nullable()->after('platform_bg_gradient_end');
            if (!Schema::hasColumn('platform_settings', 'platform_bg_opacity'))
                $t->decimal('platform_bg_opacity', 3, 2)->default(1.00)->after('platform_bg_image');
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $t) {
            $t->dropColumn([
                'platform_bg_type', 'platform_bg_color',
                'platform_bg_gradient_start', 'platform_bg_gradient_end',
                'platform_bg_image', 'platform_bg_opacity',
            ]);
        });
    }
};
