<?php

namespace Tests\Unit;

use App\Services\DeliveryLocationValidationService;
use App\Services\PsgcService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeliveryLocationValidationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(PsgcService::class, new class extends PsgcService {
            public function provinces(?string $query = null, int $limit = 50): array
            {
                return $this->filterFixtureItems([
                    ['code' => '015500000', 'name' => 'Pangasinan'],
                    ['code' => '036900000', 'name' => 'Tarlac'],
                ], $query, $limit);
            }

            public function citiesMunicipalities(string $provinceCode, ?string $query = null, int $limit = 80): array
            {
                $items = array_values(array_filter([
                    ['code' => '015506000', 'name' => 'Bautista', 'province_code' => '015500000'],
                    ['code' => '015546000', 'name' => 'Urdaneta City', 'province_code' => '015500000'],
                    ['code' => '036916000', 'name' => 'Tarlac City', 'province_code' => '036900000'],
                ], fn ($item) => $item['province_code'] === $provinceCode));
                return $this->filterFixtureItems($items, $query, $limit);
            }

            public function barangays(string $cityMunicipalityCode, ?string $query = null, int $limit = 120): array
            {
                $items = array_values(array_filter([
                    ['code' => '015506001', 'name' => 'Poblacion', 'city_municipality_code' => '015506000'],
                    ['code' => '015506002', 'name' => 'Other Barangay', 'city_municipality_code' => '015506000'],
                    ['code' => '015546001', 'name' => 'Poblacion', 'city_municipality_code' => '015546000'],
                    ['code' => '036916001', 'name' => 'Poblacion', 'city_municipality_code' => '036916000'],
                ], fn ($item) => $item['city_municipality_code'] === $cityMunicipalityCode));
                return $this->filterFixtureItems($items, $query, $limit);
            }

            public function validateHierarchy(?string $provinceCode, ?string $cityCode, ?string $barangayCode): array
            {
                $valid = [
                    '015500000|015506000|015506001' => ['Pangasinan', 'Bautista', 'Poblacion'],
                    '015500000|015506000|015506002' => ['Pangasinan', 'Bautista', 'Other Barangay'],
                    '015500000|015546000|015546001' => ['Pangasinan', 'Urdaneta City', 'Poblacion'],
                    '036900000|036916000|036916001' => ['Tarlac', 'Tarlac City', 'Poblacion'],
                ];
                $key = $provinceCode . '|' . $cityCode . '|' . $barangayCode;
                if (isset($valid[$key])) {
                    return [
                        'ok' => true,
                        'message' => null,
                        'province' => ['code' => $provinceCode, 'name' => $valid[$key][0]],
                        'city' => ['code' => $cityCode, 'name' => $valid[$key][1]],
                        'barangay' => ['code' => $barangayCode, 'name' => $valid[$key][2]],
                    ];
                }

                return ['ok' => false, 'message' => 'Please choose a province, city/municipality, and barangay from the list.'];
            }

            private function filterFixtureItems(array $items, ?string $query, int $limit): array
            {
                $query = mb_strtolower(trim((string) $query));
                return array_slice(array_values(array_filter($items, fn ($item) => $query === '' || str_contains(mb_strtolower($item['name']), $query))), 0, $limit);
            }
        });

        if (!Schema::hasTable('delivery_zones')) {
            Schema::create('delivery_zones', function ($table) {
                $table->increments('id');
                $table->string('shop_id')->nullable();
                $table->string('barangay');
                $table->string('zone_address')->nullable();
                $table->boolean('is_active')->default(true);
            });
        }
        DB::table('delivery_zones')->truncate();

        if (!Schema::hasTable('psgc_cities_municipalities')) {
            Schema::create('psgc_cities_municipalities', function ($table) {
                $table->string('code')->primary();
                $table->string('name');
                $table->string('province_code')->nullable();
                $table->string('province_name')->nullable();
            });
        }
        DB::table('psgc_cities_municipalities')->truncate();
        DB::table('psgc_cities_municipalities')->insert([
            ['code' => '015506000', 'name' => 'Bautista', 'province_code' => '015500000', 'province_name' => 'Pangasinan'],
            ['code' => '015546000', 'name' => 'Urdaneta City', 'province_code' => '015500000', 'province_name' => 'Pangasinan'],
            ['code' => '036916000', 'name' => 'Tarlac City', 'province_code' => '036900000', 'province_name' => 'Tarlac'],
        ]);
    }

    private function structuredRequest(array $overrides = []): Request
    {
        return Request::create('/checkout', 'POST', array_merge([
            '_structured_address' => '1',
            'address_house' => 'House 12',
            'address_street' => 'Rizal Street',
            'address_subdivision' => 'Purok 2',
            'address_barangay' => 'Poblacion',
            'address_barangay_code' => '015506001',
            'address_city' => 'Bautista',
            'address_city_code' => '015506000',
            'address_province' => 'Pangasinan',
            'address_province_code' => '015500000',
            'address_postal_code' => '2424',
            'address_landmark' => 'Near the blue gate',
        ], $overrides));
    }

    public function test_structured_delivery_address_requires_core_details(): void
    {
        $request = Request::create('/checkout', 'POST', [
            '_structured_address' => '1',
        ]);

        $result = (new DeliveryLocationValidationService())
            ->validateRequest($request, 15.8095, 120.4988, 'Poblacion', true);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('House / unit / building no.', $result['message']);
        $this->assertStringContainsString('Landmark', $result['message']);
    }

    public function test_structured_delivery_address_composes_valid_full_address_without_postal_code(): void
    {
        $request = Request::create('/checkout', 'POST', [
            '_structured_address' => '1',
            'address_house' => 'House 12',
            'address_street' => 'Rizal Street',
            'address_subdivision' => 'Purok 2',
            'address_barangay' => 'Poblacion',
            'address_barangay_code' => '015506001',
            'address_city' => 'Bautista',
            'address_city_code' => '015506000',
            'address_province' => 'Pangasinan',
            'address_province_code' => '015500000',
            'address_postal_code' => '2424',
            'address_landmark' => 'Near the blue gate',
        ]);

        $service = new DeliveryLocationValidationService();

        $this->assertTrue($service->validateRequest($request, 15.8095, 120.4988, 'Poblacion', true)['ok']);
        $this->assertSame(
            'House 12, Rizal Street, Purok 2, Barangay Poblacion, Bautista, Pangasinan. Landmark: Near the blue gate',
            $service->addressFromRequest($request)
        );
    }

    public function test_structured_delivery_address_rejects_gibberish_details(): void
    {
        $request = Request::create('/addresses', 'POST', [
            '_structured_address' => '1',
            'address_house' => 'dfsd',
            'address_street' => 'dfsdfd',
            'address_barangay' => 'fsdfdsf',
            'address_city' => 'dsf',
            'address_province' => 'sdfdsfsdf',
            'address_landmark' => 'dfsdfdsfsdfsdfsdf',
        ]);

        $result = (new DeliveryLocationValidationService())
            ->validateRequest($request, 15.7756639, 120.3845859, null, false, 'full_address');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('real, readable delivery address', $result['message']);
    }

    public function test_structured_delivery_address_ignores_legacy_postal_code_input(): void
    {
        $request = Request::create('/addresses', 'POST', [
            '_structured_address' => '1',
            'address_house' => 'House 12',
            'address_street' => 'Rizal Street',
            'address_barangay' => 'Poblacion',
            'address_city' => 'Bautista',
            'address_province' => 'Pangasinan',
            'address_postal_code' => 'abcd',
            'address_landmark' => 'Near the blue gate',
        ]);

        $result = (new DeliveryLocationValidationService())
            ->validateRequest($request, 15.8095, 120.4988, null, false, 'full_address');

        $this->assertTrue($result['ok']);
        $this->assertStringNotContainsString('abcd', (new DeliveryLocationValidationService())->addressFromRequest($request));
    }

    public function test_structured_delivery_address_accepts_typed_names_without_psgc_codes(): void
    {
        $request = Request::create('/addresses', 'POST', [
            '_structured_address' => '1',
            'address_house' => 'House 12',
            'address_street' => 'Rizal Street',
            'address_barangay' => 'Poblacion',
            'address_city' => 'Bautista',
            'address_province' => 'Pangasinan',
            'address_landmark' => 'Near the blue gate',
        ]);

        $result = (new DeliveryLocationValidationService())
            ->validateRequest($request, 15.8095, 120.4988, null, false, 'full_address');

        $this->assertTrue($result['ok']);
    }

    public function test_structured_delivery_address_rejects_unrecognized_place_names(): void
    {
        $request = Request::create('/addresses', 'POST', [
            '_structured_address' => '1',
            'address_house' => 'House 12',
            'address_street' => 'Rizal Street',
            'address_barangay' => 'Sampleville',
            'address_city' => 'Nowhere City',
            'address_province' => 'NotAProvince',
            'address_landmark' => 'Near the blue gate',
        ]);

        $result = (new DeliveryLocationValidationService())
            ->validateRequest($request, 15.8095, 120.4988, null, false, 'full_address');

        $this->assertFalse($result['ok']);
        $this->assertSame('province', $result['field']);
        $this->assertStringContainsString('valid province', $result['message']);
    }

    public function test_structured_delivery_address_rejects_city_outside_selected_province(): void
    {
        $request = Request::create('/addresses', 'POST', [
            '_structured_address' => '1',
            'address_house' => 'House 12',
            'address_street' => 'Rizal Street',
            'address_barangay' => 'Poblacion',
            'address_city' => 'Tarlac City',
            'address_province' => 'Pangasinan',
            'address_landmark' => 'Near the blue gate',
        ]);

        $result = (new DeliveryLocationValidationService())
            ->validateRequest($request, 15.8095, 120.4988, null, false, 'full_address');

        $this->assertFalse($result['ok']);
        $this->assertSame('city', $result['field']);
    }

    public function test_structured_delivery_address_rejects_unrecognized_barangay(): void
    {
        $request = Request::create('/addresses', 'POST', [
            '_structured_address' => '1',
            'address_house' => 'House 12',
            'address_street' => 'Rizal Street',
            'address_barangay' => 'Fake Barangay',
            'address_city' => 'Bautista',
            'address_province' => 'Pangasinan',
            'address_landmark' => 'Near the blue gate',
        ]);

        $result = (new DeliveryLocationValidationService())
            ->validateRequest($request, 15.8095, 120.4988, null, false, 'full_address');

        $this->assertFalse($result['ok']);
        $this->assertSame('barangay', $result['field']);
    }

    public function test_structured_delivery_address_rejects_missing_names(): void
    {
        $request = Request::create('/addresses', 'POST', [
            '_structured_address' => '1',
            'address_house' => 'House 12',
            'address_street' => 'Rizal Street',
            'address_landmark' => 'Near the blue gate',
        ]);

        $result = (new DeliveryLocationValidationService())
            ->validateRequest($request, 15.8095, 120.4988, null, false, 'full_address');

        $this->assertFalse($result['ok']);
    }

    public function test_shop_coverage_allows_address_inside_delivery_zone(): void
    {
        DB::table('delivery_zones')->insert([
            'shop_id' => 'shop123',
            'barangay' => 'Poblacion, Bautista',
            'is_active' => true,
        ]);

        $result = (new DeliveryLocationValidationService())
            ->validateRequest($this->structuredRequest(), 15.8095, 120.4988, 'Poblacion, Bautista', true, 'address', 'shop123');

        $this->assertTrue($result['ok']);
    }

    public function test_shop_coverage_rejects_address_outside_shop_province_or_city(): void
    {
        DB::table('delivery_zones')->insert([
            'shop_id' => 'shop123',
            'barangay' => 'Poblacion, Bautista',
            'is_active' => true,
        ]);

        $result = (new DeliveryLocationValidationService())
            ->validateRequest($this->structuredRequest([
                'address_province' => 'Tarlac',
                'address_province_code' => '036900000',
                'address_city' => 'Tarlac City',
                'address_city_code' => '036916000',
                'address_barangay_code' => '036916001',
            ]), 15.8095, 120.4988, 'Poblacion, Bautista', true, 'address', 'shop123');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('selected province', $result['message']);
    }

    public function test_shop_coverage_rejects_uncovered_barangay(): void
    {
        DB::table('delivery_zones')->insert([
            'shop_id' => 'shop123',
            'barangay' => 'Poblacion, Bautista',
            'is_active' => true,
        ]);

        $result = (new DeliveryLocationValidationService())
            ->validateRequest($this->structuredRequest([
                'address_barangay' => 'Other Barangay',
                'address_barangay_code' => '015506002',
            ]), 15.8095, 120.4988, 'Poblacion, Bautista', true, 'address', 'shop123');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Selected barangay is not covered', $result['message']);
    }
    public function test_saved_address_can_validate_full_address_fallback(): void
    {
        $request = Request::create('/addresses', 'POST', [
            'full_address' => 'House 8, Mabini Street, Barangay Poblacion, Bautista, Pangasinan. Landmark: beside the market',
        ]);

        $result = (new DeliveryLocationValidationService())
            ->validateRequest($request, 15.8095, 120.4988, null, false, 'full_address');

        $this->assertTrue($result['ok']);
    }
}
