<?php

namespace Tests\Unit;

use App\Http\Controllers\Customer\VerificationController;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use ReflectionClass;
use Tests\TestCase;

class CustomerVerificationControllerTest extends TestCase
{
    private function runBackSideFastCheck(string $frontHash, string $backHash, ?UploadedFile $backFile = null): array
    {
        $controller = new VerificationController();
        $method = (new ReflectionClass($controller))->getMethod('backSideFastCheck');
        $method->setAccessible(true);

        $request = Request::create('/customer/verification/scan-back', 'POST', [
            'id_front_hash' => $frontHash,
            'id_back_hash' => $backHash,
        ]);
        if ($backFile) {
            $request->files->set('id_back', $backFile);
        }

        return $method->invoke($controller, $request);
    }

    public function test_back_side_fast_check_accepts_distinct_back_similarity(): void
    {
        $frontHash = str_repeat('0', 240);
        $backHash = str_repeat('1', 100).str_repeat('0', 140);

        $result = $this->runBackSideFastCheck($frontHash, $backHash);

        $this->assertTrue($result['ok']);
        $this->assertSame('accepted', $result['status']);
        $this->assertSame(100, $result['scan_result']['hash_distance']);
    }

    public function test_back_side_fast_check_accepts_distinct_back_hash(): void
    {
        $frontHash = str_repeat('0', 240);
        $backHash = str_repeat('1', 130).str_repeat('0', 110);

        $result = $this->runBackSideFastCheck($frontHash, $backHash);

        $this->assertTrue($result['ok']);
        $this->assertSame('accepted', $result['status']);
        $this->assertSame(130, $result['scan_result']['hash_distance']);
    }

    public function test_back_side_fast_check_rejects_blank_random_back_image(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is not available.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'blank-back-id-') . '.png';
        $image = imagecreatetruecolor(320, 200);
        $color = imagecolorallocate($image, 130, 130, 130);
        imagefill($image, 0, 0, $color);
        imagepng($image, $tmp);
        imagedestroy($image);

        $backFile = new UploadedFile($tmp, 'blank-back.png', 'image/png', null, true);
        $frontHash = str_repeat('0', 240);
        $backHash = str_repeat('1', 150).str_repeat('0', 90);

        $result = $this->runBackSideFastCheck($frontHash, $backHash, $backFile);

        $this->assertFalse($result['ok']);
        $this->assertSame('back_id_not_detected', $result['status']);
        @unlink($tmp);
    }

    public function test_back_side_fast_check_rejects_very_same_front_hash(): void
    {
        $frontHash = str_repeat('0', 240);
        $backHash = str_repeat('1', 60).str_repeat('0', 180);

        $result = $this->runBackSideFastCheck($frontHash, $backHash);

        $this->assertFalse($result['ok']);
        $this->assertSame('front_side_again', $result['status']);
        $this->assertSame(60, $result['scan_result']['hash_distance']);
    }
    public function test_face_needs_review_is_submittable_for_manual_review(): void
    {
        $faceMatch = ['status' => 'needs_review', 'message' => 'Admin must review manually.'];

        $this->assertTrue(VerificationController::faceStatusCanSubmitForReview($faceMatch));
    }

    public function test_face_mismatch_is_not_submittable_for_manual_review(): void
    {
        $faceMatch = ['status' => 'mismatch', 'message' => 'Face does not match.'];

        $this->assertFalse(VerificationController::faceStatusCanSubmitForReview($faceMatch));
    }
}
