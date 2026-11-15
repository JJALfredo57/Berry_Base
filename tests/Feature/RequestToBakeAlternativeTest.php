<?php

namespace Tests\Feature;

use App\Services\OrderRequestService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RequestToBakeAlternativeTest extends TestCase
{
    public function test_an_alternative_must_be_an_available_cake_from_the_same_shop(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('shop_id');
            $table->boolean('is_available')->default(true);
            $table->timestamp('archived_at')->nullable();
            $table->decimal('price', 10, 2)->default(0);
        });

        DB::table('products')->insert([
            ['id' => 'OTHER-SHOP', 'shop_id' => 'SHOP-2', 'is_available' => true, 'archived_at' => null, 'price' => 500],
            ['id' => 'UNAVAILABLE', 'shop_id' => 'SHOP-1', 'is_available' => false, 'archived_at' => null, 'price' => 500],
            ['id' => 'ARCHIVED', 'shop_id' => 'SHOP-1', 'is_available' => true, 'archived_at' => now(), 'price' => 500],
        ]);

        $request = (object) ['shop_id' => 'SHOP-1', 'product_id' => 'ORIGINAL'];
        $service = app(OrderRequestService::class);

        $this->assertSame(0.0, $service->fixedPriceForRequest($request, 'OTHER-SHOP'));
        $this->assertSame(0.0, $service->fixedPriceForRequest($request, 'UNAVAILABLE'));
        $this->assertSame(0.0, $service->fixedPriceForRequest($request, 'ARCHIVED'));
        $this->assertNull(Route::getRoutes()->getByName('seller.order_requests.schedule_validation'));
        $this->assertStringNotContainsString(
            'Suggest another schedule',
            file_get_contents(resource_path('views/seller/order_requests.blade.php'))
        );
    }
}
