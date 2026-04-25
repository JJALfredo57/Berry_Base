<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // All base tables are created in 2026_04_01_000000_create_base_tables.php
        // This migration only adds tables/columns not in the base migration

        if (!Schema::hasTable('shops')) {
            Schema::create('shops', function (Blueprint $t) {
                $t->string('id', 12)->primary();
                $t->string('seller_id', 12)->nullable();
                $t->string('shop_name', 100);
                $t->string('shop_slug', 120)->unique();
                $t->string('shop_logo')->nullable();
                $t->string('shop_cover')->nullable();
                $t->text('description')->nullable();
                $t->string('address')->nullable();
                $t->string('city', 80)->nullable();
                $t->string('contact_number', 20)->nullable();
                $t->string('email', 150)->nullable();
                $t->string('gcash_number', 20)->nullable();
                $t->string('theme_color', 7)->nullable();
                $t->enum('status', ['pending','approved','suspended','rejected'])->default('pending');
                $t->enum('tier', ['basic','verified'])->default('basic');
                $t->boolean('is_verified')->default(false);
                $t->decimal('commission_rate', 5, 2)->default(3.00);
                $t->text('rejected_reason')->nullable();
                $t->timestamp('verified_at')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('seller_documents')) {
            Schema::create('seller_documents', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('shop_id', 12);
                $t->enum('document_type', ['valid_id','dti','business_permit']);
                $t->string('file_path');
                $t->text('ocr_text')->nullable();
                $t->string('ocr_business_name')->nullable();
                $t->string('ocr_expiry_date')->nullable();
                $t->boolean('ocr_is_expired')->nullable();
                $t->boolean('ocr_is_dti_document')->nullable();
                $t->boolean('ocr_name_match')->nullable();
                $t->enum('ocr_status', ['likely_valid','needs_review','likely_invalid'])->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('platform_settings')) {
            Schema::create('platform_settings', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('platform_name')->default('Cake Shop Platform');
                $t->string('platform_logo')->nullable();
                $t->string('platform_favicon')->nullable();
                $t->string('platform_tagline')->nullable();
                $t->string('platform_email')->nullable();
                $t->string('platform_phone')->nullable();
                $t->string('platform_primary_color', 7)->nullable();
                $t->boolean('dev_mode')->default(false);
                $t->decimal('commission_rate_basic', 5, 2)->default(3.00);
                $t->decimal('commission_rate_verified', 5, 2)->default(2.00);
                $t->integer('max_products_basic')->default(20);
                $t->string('paymongo_mode', 10)->default('test');
                $t->string('paymongo_test_secret')->nullable();
                $t->string('paymongo_test_public')->nullable();
                $t->string('paymongo_live_secret')->nullable();
                $t->string('paymongo_live_public')->nullable();
                $t->string('paymongo_public_key')->nullable();
                $t->string('paymongo_secret_key')->nullable();
                $t->string('philsms_token')->nullable();
                $t->string('philsms_sender')->nullable();
                $t->timestamps();
            });
        }

        // MySQL only: expand users role enum
        if (DB::getDriverName() === 'mysql') {
            try {
                DB::statement("ALTER TABLE `users` MODIFY `role` ENUM('admin','customer','seller','superadmin') NOT NULL DEFAULT 'customer'");
            } catch (\Exception $e) {}
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_documents');
        Schema::dropIfExists('shops');
        Schema::dropIfExists('platform_settings');
    }
};
