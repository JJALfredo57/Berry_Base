<?php

namespace Tests\Unit;

use App\Services\DeliveryLocationValidationService;
use App\Services\PsgcService;
use Illuminate\Http\Request;
use Tests\TestCase;

class DeliveryLocationValidationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(PsgcService::class, new class extends PsgcService {
            public function validateHierarchy(?string $provinceCode, ?string $cityCode, ?string $barangayCode): array
            {
                if ($provinceCode === '015500000' && $cityCode === '015506000' && $barangayCode === '015506001') {
                    return [
                        'ok' => true,
                        'message' => null,
                        'province' => ['code' => '015500000', 'name' => 'Pangasinan'],
                        'city' => ['code' => '015506000', 'name' => 'Bautista'],
                        'barangay' => ['code' => '015506001', 'name' => 'Poblacion'],
                    ];
                }

                return ['ok' => false, 'message' => 'Please choose a province, city/municipality, and barangay from the list.'];
            }
        });
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

    public function test_structured_delivery_address_composes_valid_full_address(): void
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
            'House 12, Rizal Street, Purok 2, Barangay Poblacion, Bautista, Pangasinan, 2424. Landmark: Near the blue gate',
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

    public function test_structured_delivery_address_rejects_invalid_postal_code(): void
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

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Postal Code', $result['message']);
    }

    public function test_structured_delivery_address_rejects_missing_psgc_codes(): void
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

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('choose a province, city/municipality, and barangay', $result['message']);
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
