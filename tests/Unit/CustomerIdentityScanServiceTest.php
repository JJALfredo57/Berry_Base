<?php

namespace Tests\Unit;

use App\Services\CustomerIdentityScanService;
use App\Services\IdentityVerificationSettingsService;
use Tests\TestCase;

class CustomerIdentityScanServiceTest extends TestCase
{
    private function service(): CustomerIdentityScanService
    {
        $settings = new class extends IdentityVerificationSettingsService {
            public function types(): array
            {
                return [
                    ['name' => 'National ID', 'keywords' => ['PhilID', 'PhilSys', 'National Identification']],
                    ['name' => "Driver's License", 'keywords' => ['Driver License', "Driver's License", 'LTO']],
                    ['name' => 'Postal ID', 'keywords' => ['Postal ID', 'PHLPost']],
                ];
            }
        };

        return new CustomerIdentityScanService($settings);
    }

    public function test_ocr_text_matching_selected_id_type_is_marked_match(): void
    {
        $result = $this->service()->evaluateText(
            "Driver's License",
            'Republic of the Philippines Land Transportation Office LTO Driver License'
        );

        $this->assertSame('match', $result['status']);
        $this->assertSame("Driver's License", $result['detected_id_type']);
    }

    public function test_ocr_text_detecting_different_id_type_is_marked_mismatch(): void
    {
        $result = $this->service()->evaluateText(
            "Driver's License",
            'Republic of the Philippines PhilSys PhilID National Identification Card'
        );

        $this->assertSame('mismatch', $result['status']);
        $this->assertSame('National ID', $result['detected_id_type']);
        $this->assertStringContainsString('Driver', $result['warning']);
        $this->assertStringContainsString('National ID', $result['warning']);
    }

    public function test_unclear_ocr_text_falls_back_to_manual_review(): void
    {
        $result = $this->service()->evaluateText("Driver's License", 'blurred unreadable text only');

        $this->assertSame('needs_review', $result['status']);
        $this->assertNull($result['detected_id_type']);
    }
}
