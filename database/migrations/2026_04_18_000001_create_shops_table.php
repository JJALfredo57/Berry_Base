<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
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
                $t->string('gcash_number', 20)->nullable();
                $t->enum('status', ['pending','approved','suspended','rejected'])->default('pending');
                $t->enum('tier', ['basic','verified'])->default('basic');
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
                $t->decimal('commission_rate_basic', 5, 2)->default(3.00);
                $t->decimal('commission_rate_verified', 5, 2)->default(2.00);
                $t->integer('max_products_basic')->default(20);
                $t->string('paymongo_public_key')->nullable();
                $t->string('paymongo_secret_key')->nullable();
                $t->string('philsms_token')->nullable();
                $t->string('philsms_sender')->nullable();
                $t->timestamps();
            });
        }

        // Safely add seller/superadmin roles to users enum (only if not already present)
        try {
            DB::statement("ALTER TABLE `users` MODIFY `role` ENUM('admin','customer','seller','superadmin') NOT NULL DEFAULT 'customer'");
        } catch (\Exception $e) {
            // Already up-to-date, skip
        }

        // Seed default platform settings only if table is empty
        if (!DB::table('platform_settings')->exists()) {
            DB::table('platform_settings')->insert([
                'platform_name'            => 'Cake Shop Platform',
                'commission_rate_basic'    => 3.00,
                'commission_rate_verified' => 2.00,
                'max_products_basic'       => 20,
                'created_at'               => now(),
                'updated_at'               => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_documents');
        Schema::dropIfExists('shops');
        Schema::dropIfExists('platform_settings');
    }
};
