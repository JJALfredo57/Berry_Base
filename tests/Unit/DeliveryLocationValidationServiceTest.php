<?php

namespace Tests\Unit;

use App\Services\DeliveryLocationValidationService;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class DeliveryLocationValidationServiceTest extends TestCase
{
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
            'address_city' => 'Bautista',
            'address_province' => 'Pangasinan',
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

    public function test_saved_address_can_validate_full_address_fallback(): void
    {
        $request = Request::create('/addresses', 'POST', [
            'full_address' => 'House 8, Mabini Street, Barangay Poblacion, Bautista, Pangasinan. Landmark: beside the market',
        ]);

        $result = (new DeliveryLocationValidationService())
            ->validateRequest($request, 15.8095, 120.4988, null, false, 'full_address');

        $this->assertTrue($result['ok']);
    }}

