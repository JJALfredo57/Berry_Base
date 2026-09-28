<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\CustomerFaceMatchService;
use App\Services\CustomerIdentityScanService;
use App\Services\CustomerVerificationService;
use App\Services\IdentityVerificationSettingsService;
use App\Traits\UploadsFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class VerificationController extends Controller
{
    use UploadsFiles;

    private const BACK_SIDE_HARD_SAME_HASH_DISTANCE = 128;
    private const BACK_SIDE_REVIEW_HASH_DISTANCE = 128;

    public function show(CustomerVerificationService $verification, IdentityVerificationSettingsService $identitySettings)
    {
        $uid = session('user')['id'];
        $latest = $verification->latest($uid);
        $status = $latest->status ?? 'not_submitted';
        $benefits = $verification->benefits($status);
        $limitations = $verification->limitations($status);
        $loyaltyOverview = app(\App\Services\LoyaltyService::class)->membershipOverview($uid);
        $idTypes = $identitySettings->typeNames();
        $idTypeRules = $identitySettings->typeMap();
        $selfieRequired = true;

        return view('customer.verification', compact('latest', 'status', 'benefits', 'limitations', 'loyaltyOverview', 'idTypes', 'idTypeRules', 'selfieRequired'));
    }

    public function scanFront(Request $request, IdentityVerificationSettingsService $identitySettings, CustomerIdentityScanService $identityScanner)
    {
        $idTypes = $identitySettings->typeNames();

        $request->validate([
            'id_type' => ['required', 'string', 'max:60', Rule::in($idTypes)],
            'id_front' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $selectedIdType = trim($request->input('id_type'));
        $scan = $identityScanner->scanUploadedFile($selectedIdType, $request->file('id_front'));
        $status = $scan['id_type_match_status'] ?? 'needs_review';

        $canContinue = $status === 'match';

        return response()->json([
            'ok' => $canContinue,
            'can_continue' => $canContinue,
            'match_status' => $status,
            'scan_status' => $scan['scan_status'] ?? 'needs_review',
            'expected_id_type' => $scan['id_type_scan_expected'] ?? $selectedIdType,
            'detected_id_type' => $scan['id_type_scan_detected'] ?? null,
            'message' => $status === 'match'
                ? 'ID type matched. You may scan the back of the ID.'
                : ($status === 'mismatch'
                    ? ($scan['id_type_match_warning'] ?? 'Selected ID type does not match the uploaded ID.')
                    : ($scan['id_type_match_warning'] ?? 'The scanner must confirm this matches the selected ID type before continuing. Please retake a clearer front ID photo.')),
        ]);
    }

    public function scanBack(Request $request, IdentityVerificationSettingsService $identitySettings)
    {
        $idTypes = $identitySettings->typeNames();

        $request->validate([
            'id_type' => ['required', 'string', 'max:60', Rule::in($idTypes)],
            'id_front' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'id_back' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'id_front_hash' => ['nullable', 'string', 'regex:/^(?:[01]{64}|[01]{240})$/'],
            'id_back_hash' => ['nullable', 'string', 'regex:/^(?:[01]{64}|[01]{240})$/'],
        ]);

        $backCheck = $this->backSideFastCheck($request);

        return response()->json([
            'ok' => (bool) ($backCheck['ok'] ?? false),
            'can_continue' => (bool) ($backCheck['ok'] ?? false),
            'status' => $backCheck['status'] ?? 'needs_review',
            'message' => $backCheck['message'] ?? 'Back ID captured. Continue to face verification.',
            'scan_result' => $backCheck['scan_result'] ?? [],
        ]);
    }
    public function compareFace(Request $request, CustomerFaceMatchService $faceMatcher)
    {
        $request->validate([
            'id_front' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'selfie' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $faceMatch = $faceMatcher->compareUploadedFiles($request->file('id_front'), $request->file('selfie'));
        $status = $faceMatch['status'] ?? 'needs_review';

        $canContinue = $status === 'match';

        return response()->json([
            'ok' => $canContinue,
            'can_continue' => $canContinue,
            'status' => $status,
            'score' => $faceMatch['score'] ?? null,
            'threshold' => $faceMatch['threshold'] ?? null,
            'engine' => $faceMatch['engine'] ?? 'external_face_compare',
            'message' => $faceMatch['message'] ?? ($status === 'match' ? 'Face matched the ID.' : 'Face must clearly match the ID before continuing.'),
        ]);
    }

    public function store(Request $request, IdentityVerificationSettingsService $identitySettings, CustomerIdentityScanService $identityScanner, CustomerFaceMatchService $faceMatcher)
    {
        $idTypes = $identitySettings->typeNames();

        $request->validate([
            'id_type' => ['required', 'string', 'max:60', Rule::in($idTypes)],
            'id_front' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'id_back' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'selfie' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'customer_note' => 'nullable|string|max:500',
            'liveness_challenge' => 'nullable|string|max:80',
            'liveness_result' => 'nullable|string|max:40',
            'liveness_method' => 'nullable|string|max:60',
            'selfie_capture_source' => 'required|string|max:40',
            'id_front_hash' => ['nullable', 'string', 'regex:/^(?:[01]{64}|[01]{240})$/'],
            'id_back_hash' => ['nullable', 'string', 'regex:/^(?:[01]{64}|[01]{240})$/'],
        ]);

        $selectedIdType = trim($request->input('id_type'));
        $backRequired = $identitySettings->requiresBack($selectedIdType);
        if ($backRequired && !$request->hasFile('id_back')) {
            return back()
                ->withInput()
                ->with('error', 'Back ID is required for the selected ID type.');
        }

        if ($request->input('selfie_capture_source') !== 'live_camera') {
            return back()
                ->withInput()
                ->with('error', 'Face verification requires the live camera. Selfie upload is not accepted.');
        }

        $scan = $identityScanner->scanUploadedFile($selectedIdType, $request->file('id_front'));
        $matchStatus = $scan['id_type_match_status'] ?? 'needs_review';
        if ($matchStatus !== 'match') {
            return back()
                ->withInput()
                ->with('error', $matchStatus === 'mismatch'
                    ? ($scan['id_type_match_warning'] ?? 'The front ID scan detected a different ID type. Please retake the correct ID photo.')
                    : ($scan['id_type_match_warning'] ?? 'The scanner must confirm this matches the selected ID type before continuing. Please retake a clearer front ID photo.'));
        }

        $backSideCheck = [
            'ok' => true,
            'status' => 'not_required',
            'message' => 'Back ID is not required for this ID type.',
            'scan_result' => ['method' => 'not_required'],
        ];
        if ($backRequired) {
            $backSideCheck = $this->backSideFastCheck($request);
            if (!($backSideCheck['ok'] ?? false)) {
                return back()
                    ->withInput()
                    ->with('error', $backSideCheck['message'] ?? 'The back ID photo looks like the front side again. Please flip the ID and scan the back side.');
            }
        }

        $faceMatch = $faceMatcher->compareUploadedFiles($request->file('id_front'), $request->file('selfie'));
        if (($faceMatch['status'] ?? 'needs_review') !== 'match') {
            return back()
                ->withInput()
                ->with('error', $faceMatch['message'] ?? 'Face verification must clearly detect and match the face on the ID before submission. Please retake the front ID and selfie.');
        }

        $front = $this->uploadFile($request->file('id_front'), 'uploads/customer-ids');
        if (!$front) return back()->with('error', 'Valid ID upload failed. Please try a smaller clear image.');

        $back = null;
        if ($backRequired) {
            $back = $this->uploadFile($request->file('id_back'), 'uploads/customer-ids');
            if (!$back) return back()->with('error', 'Back ID upload failed. Please try a smaller clear image.');
        }

        $selfie = $this->uploadFile($request->file('selfie'), 'uploads/customer-ids');
        if (!$selfie) return back()->with('error', 'Selfie upload failed. Please try a smaller clear image.');

        $verificationData = [
            'user_id' => session('user')['id'],
            'id_type' => $selectedIdType,
            'id_front_path' => $front,
            'id_back_path' => $back,
            'selfie_path' => $selfie,
            'status' => 'pending',
            'customer_note' => trim((string) $request->input('customer_note')) ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('customer_verifications', 'scan_status')) {
            $verificationData['scan_status'] = $scan['scan_status'] ?? 'scanned';
        }
        if (Schema::hasColumn('customer_verifications', 'id_type_match_status')) {
            $verificationData['id_type_match_status'] = $scan['id_type_match_status'] ?? 'match';
        }
        if (Schema::hasColumn('customer_verifications', 'id_type_scan_expected')) {
            $verificationData['id_type_scan_expected'] = $scan['id_type_scan_expected'] ?? $selectedIdType;
        }
        if (Schema::hasColumn('customer_verifications', 'id_type_scan_detected')) {
            $verificationData['id_type_scan_detected'] = $scan['id_type_scan_detected'] ?? null;
        }
        if (Schema::hasColumn('customer_verifications', 'id_type_match_warning')) {
            $verificationData['id_type_match_warning'] = $scan['id_type_match_warning'] ?? null;
        }
        if (Schema::hasColumn('customer_verifications', 'scan_result')) {
            $verificationData['scan_result'] = json_encode($scan['scan_result'] ?? []);
        }
        if (Schema::hasColumn('customer_verifications', 'review_flags')) {
            $verificationData['review_flags'] = json_encode([
                'ocr_pending' => false,
                'ocr_needs_review' => ($scan['id_type_match_status'] ?? 'needs_review') !== 'match',
                'id_type_mismatch' => ($scan['id_type_match_status'] ?? null) === 'mismatch',
                'front_back_same_check' => $backRequired ? 'passed' : 'not_required',
                'back_id_required' => $backRequired,
                'back_side_check_status' => $backSideCheck['status'] ?? 'needs_review',
                'back_side_check_message' => $backSideCheck['message'] ?? null,
                'back_side_check_result' => $backSideCheck['scan_result'] ?? [],
                'selfie_required' => true,
                'guided_capture' => true,
                'selfie_capture_source' => $request->input('selfie_capture_source'),
                'face_match_required' => true,
                'face_match_status' => $faceMatch['status'] ?? 'needs_review',
                'face_match_score' => $faceMatch['score'] ?? null,
                'face_match_threshold' => $faceMatch['threshold'] ?? null,
                'face_match_engine' => $faceMatch['engine'] ?? 'external_face_compare',
                'face_match_message' => $faceMatch['message'] ?? null,
                'face_match_error' => $faceMatch['error'] ?? null,
                'liveness_challenge' => trim((string) $request->input('liveness_challenge')) ?: null,
                'liveness_result' => trim((string) $request->input('liveness_result')) ?: 'not_verified',
                'liveness_method' => trim((string) $request->input('liveness_method')) ?: 'not_available',
            ]);
        }

        DB::table('customer_verifications')->insert($verificationData);

        DB::table('notifications')->insert([
            'receiver_role' => 'admin',
            'title' => 'Customer Verification Pending',
            'message' => (session('user')['fullname'] ?? 'Customer') . ' submitted a valid ID for review.',
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('customer.verification')->with('msg', 'Valid ID submitted. We will review it soon.');
    }

    private function backSideFastCheck(Request $request): array
    {
        [$frontHash, $backHash] = $this->frontBackHashes($request);

        if (!preg_match('/^(?:[01]{64}|[01]{240})$/', $frontHash) || !preg_match('/^(?:[01]{64}|[01]{240})$/', $backHash)) {
            return [
                'ok' => false,
                'status' => 'side_check_unavailable',
                'message' => 'Back ID check could not compare both sides. Please tap Reset and scan the front and back ID again.',
                'scan_result' => ['method' => 'fast_hash_check', 'error' => 'missing_hash'],
            ];
        }

        $distance = $this->hashDistance($frontHash, $backHash);
        $backSignal = $this->uploadedBackIdSignal($request->file('id_back'));
        if (!($backSignal['ok'] ?? true)) {
            return [
                'ok' => false,
                'status' => 'back_id_not_detected',
                'message' => 'Back ID must show the actual back side of the ID inside the guide. Retake it closer and clearer.',
                'scan_result' => [
                    'method' => 'fast_hash_and_detail_check',
                    'hash_distance' => $distance,
                    'hard_threshold' => self::BACK_SIDE_HARD_SAME_HASH_DISTANCE,
                    'image_signal' => $backSignal,
                ],
            ];
        }

        if ($distance <= self::BACK_SIDE_HARD_SAME_HASH_DISTANCE) {
            return [
                'ok' => false,
                'status' => 'front_side_again',
                'message' => 'This still looks like the front side. Flip the ID and scan the actual back side.',
                'scan_result' => [
                    'method' => 'fast_hash_and_detail_check',
                    'hash_distance' => $distance,
                    'hard_threshold' => self::BACK_SIDE_HARD_SAME_HASH_DISTANCE,
                    'image_signal' => $backSignal,
                ],
            ];
        }

        return [
            'ok' => true,
            'status' => 'accepted',
            'message' => 'Back ID captured. Continue to face verification.',
            'scan_result' => [
                'method' => 'fast_hash_and_detail_check',
                'hash_distance' => $distance,
                'hard_threshold' => self::BACK_SIDE_HARD_SAME_HASH_DISTANCE,
                'image_signal' => $backSignal,
            ],
        ];
    }



    private function uploadedBackIdSignal($file): array
    {
        if (!$file) {
            return ['ok' => true, 'reason' => 'no_uploaded_file'];
        }

        $signal = $this->uploadedImageSignal($file);
        if (!($signal['ok'] ?? false)) {
            return $signal;
        }

        $hasEnoughDetail = ($signal['edge_density'] ?? 0) >= 0.035;
        $hasEnoughContrast = ($signal['contrast'] ?? 0) >= 8.0;
        $hasUsefulSize = ($signal['width'] ?? 0) >= 260 && ($signal['height'] ?? 0) >= 160;

        return $signal + [
            'ok' => $hasEnoughDetail && $hasEnoughContrast && $hasUsefulSize,
            'required_edge_density' => 0.035,
            'required_contrast' => 8.0,
            'reason' => $hasEnoughDetail && $hasEnoughContrast && $hasUsefulSize ? 'id_like_detail_detected' : 'not_enough_id_detail',
        ];
    }

    private function uploadedImageSignal($file): array
    {
        if (!$file || !function_exists('imagecreatefromstring')) {
            return ['ok' => false, 'reason' => 'image_tools_unavailable'];
        }

        $contents = @file_get_contents($file->getRealPath());
        if (!$contents) {
            return ['ok' => false, 'reason' => 'empty_image'];
        }

        $source = @imagecreatefromstring($contents);
        if (!$source) {
            return ['ok' => false, 'reason' => 'invalid_image'];
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $width = 96;
        $height = 64;
        $thumb = imagecreatetruecolor($width, $height);
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

        $total = 0.0;
        $totalSq = 0.0;
        $grays = [];
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($thumb, $x, $y);
                $gray = ((($rgb >> 16) & 0xFF) + (($rgb >> 8) & 0xFF) + ($rgb & 0xFF)) / 3;
                $grays[$y][$x] = $gray;
                $total += $gray;
                $totalSq += $gray * $gray;
            }
        }

        $edges = 0;
        $comparisons = 0;
        for ($y = 0; $y < $height - 1; $y++) {
            for ($x = 0; $x < $width - 1; $x++) {
                if (abs($grays[$y][$x] - $grays[$y][$x + 1]) > 18 || abs($grays[$y][$x] - $grays[$y + 1][$x]) > 18) {
                    $edges++;
                }
                $comparisons++;
            }
        }

        imagedestroy($source);
        imagedestroy($thumb);

        $pixels = $width * $height;
        $brightness = $total / $pixels;
        $variance = ($totalSq / $pixels) - ($brightness * $brightness);

        return [
            'ok' => true,
            'width' => $sourceWidth,
            'height' => $sourceHeight,
            'brightness' => round($brightness, 2),
            'contrast' => round(sqrt(max(0, $variance)), 2),
            'edge_density' => round($edges / max(1, $comparisons), 4),
        ];
    }
    private function frontBackHashes(Request $request): array
    {
        return [
            $this->uploadedImageHash($request->file('id_front')) ?: trim((string) $request->input('id_front_hash')),
            $this->uploadedImageHash($request->file('id_back')) ?: trim((string) $request->input('id_back_hash')),
        ];
    }

    private function uploadedImageHash($file): ?string
    {
        if (!$file || !function_exists('imagecreatefromstring')) {
            return null;
        }

        $contents = @file_get_contents($file->getRealPath());
        if (!$contents) {
            return null;
        }

        $source = @imagecreatefromstring($contents);
        if (!$source) {
            return null;
        }

        $width = 16;
        $height = 16;
        $thumb = imagecreatetruecolor($width, $height);
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));

        $grays = [];
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($thumb, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $grays[] = ($r + $g + $b) / 3;
            }
        }

        imagedestroy($source);
        imagedestroy($thumb);

        $bits = [];
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width - 1; $x++) {
                $left = $grays[($y * $width) + $x];
                $right = $grays[($y * $width) + $x + 1];
                $bits[] = $left > $right ? '1' : '0';
            }
        }

        return implode('', $bits);
    }

    private function hashDistance(string $a, string $b): int
    {
        $distance = 0;
        for ($i = 0; $i < min(strlen($a), strlen($b)); $i++) {
            if ($a[$i] !== $b[$i]) {
                $distance++;
            }
        }

        return $distance + abs(strlen($a) - strlen($b));
    }
}
