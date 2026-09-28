<?php

namespace Tests\Unit;

use App\Services\CustomerFaceMatchService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CustomerFaceMatchServiceTest extends TestCase
{
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        parent::tearDown();
    }

    private function upload(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'face_test_');
        file_put_contents($path, 'test-image-bytes');
        $this->tempFiles[] = $path;

        return new UploadedFile($path, $name, 'image/jpeg', null, true);
    }

    public function test_http_face_compare_match_result_is_normalized(): void
    {
        config()->set('services.face_compare.service_url', 'https://ocr.example.test/face-compare');
        config()->set('services.face_compare.service_token', 'test-token');

        Http::fake([
            'ocr.example.test/face-compare' => Http::response([
                'ok' => true,
                'status' => 'match',
                'score' => 0.82,
                'threshold' => 0.60,
                'engine' => 'opencv_face_compare',
                'message' => 'Selfie appears to match the face on the ID.',
            ]),
        ]);

        $result = app(CustomerFaceMatchService::class)->compareUploadedFiles(
            $this->upload('front.jpg'),
            $this->upload('selfie.jpg'),
        );

        $this->assertTrue($result['ok']);
        $this->assertSame('match', $result['status']);
        $this->assertSame(0.82, $result['score']);
        $this->assertSame('opencv_face_compare', $result['engine']);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_http_face_compare_mismatch_result_is_preserved(): void
    {
        config()->set('services.face_compare.service_url', 'https://ocr.example.test/face-compare');

        Http::fake([
            'ocr.example.test/face-compare' => Http::response([
                'ok' => true,
                'status' => 'mismatch',
                'score' => 0.21,
                'message' => 'Selfie does not appear to match the face on the ID.',
            ]),
        ]);

        $result = app(CustomerFaceMatchService::class)->compareUploadedFiles(
            $this->upload('front.jpg'),
            $this->upload('selfie.jpg'),
        );

        $this->assertFalse($result['ok']);
        $this->assertSame('mismatch', $result['status']);
        $this->assertSame(0.21, $result['score']);
    }

    public function test_missing_face_compare_url_falls_back_to_manual_review(): void
    {
        config()->set('services.face_compare.service_url', null);
        config()->set('services.ocr.service_url', null);

        $result = app(CustomerFaceMatchService::class)->compareUploadedFiles(
            $this->upload('front.jpg'),
            $this->upload('selfie.jpg'),
        );

        $this->assertFalse($result['ok']);
        $this->assertSame('needs_review', $result['status']);
        $this->assertSame('missing_face_compare_service_url', $result['error']);
    }

    public function test_openbiometrics_staging_user_uses_document_verify(): void
    {
        config()->set('services.customer_identity.provider', 'staging');
        config()->set('services.customer_identity.staging_user_ids', '57');
        config()->set('services.openbiometrics.base_url', 'https://bio.example.test');
        config()->set('services.openbiometrics.api_key', 'bio-token');
        config()->set('services.openbiometrics.face_threshold', 0.45);
        session(['user' => ['id' => 57]]);

        Http::fake([
            'bio.example.test/api/v1/documents/verify' => Http::response([
                'is_match' => true,
                'similarity' => 0.87,
            ]),
        ]);

        $result = app(CustomerFaceMatchService::class)->compareUploadedFiles(
            $this->upload('front.jpg'),
            $this->upload('selfie.jpg'),
        );

        $this->assertTrue($result['ok']);
        $this->assertSame('match', $result['status']);
        $this->assertSame(0.87, $result['score']);
        $this->assertSame(0.45, $result['threshold']);
        $this->assertSame('openbiometrics_document_verify', $result['engine']);

        Http::assertSent(fn ($request) => $request->url() === 'https://bio.example.test/api/v1/documents/verify'
            && $request->hasHeader('Authorization', 'Bearer bio-token'));
    }
}
