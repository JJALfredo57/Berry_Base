<?php

use App\Services\IdentityVerificationSettingsService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_settings')) {
            Schema::table('platform_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('platform_settings', 'verification_id_types')) {
                    $table->json('verification_id_types')->nullable()->after('loyalty_redemption_requires_verified');
                }
                if (!Schema::hasColumn('platform_settings', 'verification_selfie_required')) {
                    $table->boolean('verification_selfie_required')->default(true)->after('verification_id_types');
                }
            });

            $settings = DB::table('platform_settings')->first();
            $defaults = json_encode(app(IdentityVerificationSettingsService::class)->defaultTypes());

            if ($settings) {
                if (empty($settings->verification_id_types ?? null)) {
                    DB::table('platform_settings')->where('id', $settings->id)->update([
                        'verification_id_types' => $defaults,
                        'verification_selfie_required' => true,
                        'updated_at' => now(),
                    ]);
                }
            } else {
                DB::table('platform_settings')->insert([
                    'platform_name' => 'Cake Shop Platform',
                    'verification_id_types' => $defaults,
                    'verification_selfie_required' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (Schema::hasTable('customer_verifications')) {
            Schema::table('customer_verifications', function (Blueprint $table) {
                if (!Schema::hasColumn('customer_verifications', 'scan_status')) {
                    $table->string('scan_status', 40)->default('manual_review')->after('status');
                }
                if (!Schema::hasColumn('customer_verifications', 'id_type_match_status')) {
                    $table->string('id_type_match_status', 40)->default('not_scanned')->after('scan_status');
                }
                if (!Schema::hasColumn('customer_verifications', 'id_type_scan_expected')) {
                    $table->string('id_type_scan_expected', 60)->nullable()->after('id_type_match_status');
                }
                if (!Schema::hasColumn('customer_verifications', 'id_type_scan_detected')) {
                    $table->string('id_type_scan_detected', 60)->nullable()->after('id_type_scan_expected');
                }
                if (!Schema::hasColumn('customer_verifications', 'id_type_match_warning')) {
                    $table->text('id_type_match_warning')->nullable()->after('id_type_scan_detected');
                }
                if (!Schema::hasColumn('customer_verifications', 'scan_result')) {
                    $table->json('scan_result')->nullable()->after('id_type_match_warning');
                }
                if (!Schema::hasColumn('customer_verifications', 'review_flags')) {
                    $table->json('review_flags')->nullable()->after('scan_result');
                }
            });

            DB::table('customer_verifications')
                ->whereNull('id_type_scan_expected')
                ->update([
                    'id_type_scan_expected' => DB::raw('id_type'),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customer_verifications')) {
            Schema::table('customer_verifications', function (Blueprint $table) {
                foreach ([
                    'review_flags',
                    'scan_result',
                    'id_type_match_warning',
                    'id_type_scan_detected',
                    'id_type_scan_expected',
                    'id_type_match_status',
                    'scan_status',
                ] as $column) {
                    if (Schema::hasColumn('customer_verifications', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('platform_settings')) {
            Schema::table('platform_settings', function (Blueprint $table) {
                foreach (['verification_selfie_required', 'verification_id_types'] as $column) {
                    if (Schema::hasColumn('platform_settings', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
