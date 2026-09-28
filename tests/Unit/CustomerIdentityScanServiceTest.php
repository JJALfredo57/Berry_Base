<?php

namespace Tests\Unit;

use App\Services\CustomerIdentityScanService;
use App\Services\IdentityVerificationSettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
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

    public function test_ocr_text_with_spacing_or_token_variations_can_match_id_type(): void
    {
        $national = $this->service()->evaluateText(
            'National ID',
            'Republic of the Philippines Phil ID National Identification Card'
        );

        $driver = $this->service()->evaluateText(
            "Driver's License",
            'Republic of the Philippines Land Transportation Office Driver Licence L T O'
        );

        $this->assertSame('match', $national['status']);
        $this->assertSame('National ID', $national['detected_id_type']);
        $this->assertSame('match', $driver['status']);
        $this->assertSame("Driver's License", $driver['detected_id_type']);
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

    public function test_http_ocr_driver_can_match_selected_id_type(): void
    {
        config()->set('services.ocr.driver', 'http');
        config()->set('services.ocr.service_url', 'https://ocr.example.test/ocr');
        config()->set('services.ocr.service_token', 'test-token');

        Http::fake([
            'ocr.example.test/ocr' => Http::response([
                'ok' => true,
                'text' => 'Republic of the Philippines PhilSys PhilID National Identification Card',
            ]),
        ]);

        $image = UploadedFile::fake()->create('front.jpg', 10, 'image/jpeg');
        $result = $this->service()->scanUploadedFile('National ID', $image);

        $this->assertSame('scanned', $result['scan_status']);
        $this->assertSame('match', $result['id_type_match_status']);
        $this->assertSame('http', $result['scan_result']['engine']);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_http_ocr_health_check_reports_available_service(): void
    {
        config()->set('services.ocr.driver', 'http');
        config()->set('services.ocr.service_url', 'https://ocr.example.test/ocr');

        Http::fake([
            'ocr.example.test/health' => Http::response([
                'ok' => true,
                'version' => 'tesseract v5.3.0',
                'language' => 'eng',
            ]),
        ]);

        $result = $this->service()->healthCheck();

        $this->assertTrue($result['ok']);
        $this->assertSame('http', $result['driver']);
        $this->assertSame('tesseract v5.3.0', $result['version']);
    }
}