<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $duplicateProducts = DB::table('products')
            ->selectRaw('shop_id, LOWER(TRIM(name)) as name_key, LOWER(TRIM(classification)) as class_key, COUNT(*) as total')
            ->groupBy('shop_id', 'name_key', 'class_key')->having('total', '>', 1)->exists();
        $duplicateSizes = DB::table('product_sizes')
            ->selectRaw('product_id, LOWER(TRIM(label)) as label_key, COUNT(*) as total')
            ->groupBy('product_id', 'label_key')->having('total', '>', 1)->exists();

        if ($duplicateProducts || $duplicateSizes) {
            throw new RuntimeException('Duplicate product or size identities must be resolved before enabling strict uniqueness.');
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('name_key', 150)->nullable()->after('name');
            $table->string('classification_key', 60)->nullable()->after('classification');
        });
        Schema::table('product_sizes', function (Blueprint $table) {
            $table->string('label_key', 60)->nullable()->after('label');
        });

        DB::table('products')->orderBy('id')->each(function ($product) {
            DB::table('products')->where('id', $product->id)->update([
                'name_key' => mb_strtolower(trim(preg_replace('/\s+/', ' ', $product->name))),
                'classification_key' => mb_strtolower(trim($product->classification ?? '')),
            ]);
        });
        DB::table('product_sizes')->orderBy('id')->each(function ($size) {
            DB::table('product_sizes')->where('id', $size->id)->update([
                'label_key' => mb_strtolower(trim(preg_replace('/\s+/', ' ', $size->label))),
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unique(['shop_id', 'classification_key', 'name_key'], 'products_shop_class_name_key_unique');
        });
        Schema::table('product_sizes', function (Blueprint $table) {
            $table->unique(['product_id', 'label_key'], 'product_sizes_product_label_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('product_sizes', function (Blueprint $table) {
            $table->dropUnique('product_sizes_product_label_key_unique');
            $table->dropColumn('label_key');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_shop_class_name_key_unique');
            $table->dropColumn(['name_key', 'classification_key']);
        });
    }
};
