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

    public function show(CustomerVerificationService $verification, IdentityVerificationSettingsService $identitySettings)
    {
        $uid = session('user')['id'];
        $latest = $verification->latest($uid);
        $status = $latest->status ?? 'not_submitted';
        $benefits = $verification->benefits($status);
        $limitations = $verification->limitations($status);
        $loyaltyOverview = app(\App\Services\LoyaltyService::class)->membershipOverview($uid);
        $idTypes = $identitySettings->typeNames();
        $selfieRequired = true;

        return view('customer.verification', compact('latest', 'status', 'benefits', 'limitations', 'loyaltyOverview', 'idTypes', 'selfieRequired'));
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

        return response()->json([
            'ok' => $status === 'match' || ($scan['scan_status'] ?? null) === 'ocr_unavailable',
            'can_continue' => $status === 'match' || ($scan['scan_status'] ?? null) === 'ocr_unavailable',
            'match_status' => $status,
            'scan_status' => $scan['scan_status'] ?? 'needs_review',
            'expected_id_type' => $scan['id_type_scan_expected'] ?? $selectedIdType,
            'detected_id_type' => $scan['id_type_scan_detected'] ?? null,
            'message' => $status === 'match'
                ? 'ID type matched. You may scan the back of the ID.'
                : ($status === 'mismatch'
                    ? ($scan['id_type_match_warning'] ?? 'Selected ID type does not match the uploaded ID.')
                    : (($scan['scan_status'] ?? null) === 'ocr_unavailable'
                        ? 'OCR service is unavailable. Admin will review the ID type manually.'
                        : 'OCR could not confirm the selected ID type. Please retake a clearer front ID photo.')),
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

        return response()->json([
            'ok' => $status === 'match',
            'can_continue' => $status === 'match',
            'status' => $status,
            'score' => $faceMatch['score'] ?? null,
            'threshold' => $faceMatch['threshold'] ?? null,
            'engine' => $faceMatch['engine'] ?? 'external_face_compare',
            'message' => $faceMatch['message'] ?? ($status === 'mismatch' ? 'Selfie does not match the ID face.' : 'Face comparison needs review.'),
        ]);
    }

    public function store(Request $request, IdentityVerificationSettingsService $identitySettings, CustomerIdentityScanService $identityScanner, CustomerFaceMatchService $faceMatcher)
    {
        $idTypes = $identitySettings->typeNames();

        $request->validate([
            'id_type' => ['required', 'string', 'max:60', Rule::in($idTypes)],
            'id_front' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'id_back' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'selfie' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'customer_note' => 'nullable|string|max:500',
            'liveness_challenge' => 'nullable|string|max:80',
            'liveness_result' => 'nullable|string|max:40',
            'liveness_method' => 'nullable|string|max:60',
            'selfie_capture_source' => 'required|string|max:40',
            'id_front_hash' => ['nullable', 'string', 'regex:/^[01]{64}$/'],
            'id_back_hash' => ['nullable', 'string', 'regex:/^[01]{64}$/'],
        ]);

        if ($request->input('selfie_capture_source') !== 'live_camera') {
            return back()
                ->withInput()
                ->with('error', 'Face verification requires the live camera. Selfie upload is not accepted.');
        }

        $selectedIdType = trim($request->input('id_type'));
        $scan = $identityScanner->scanUploadedFile($selectedIdType, $request->file('id_front'));
        $scanStatus = $scan['scan_status'] ?? 'needs_review';
        $matchStatus = $scan['id_type_match_status'] ?? 'needs_review';
        if ($matchStatus !== 'match' && $scanStatus !== 'ocr_unavailable') {
            return back()
                ->withInput()
                ->with('error', $matchStatus === 'mismatch'
                    ? ($scan['id_type_match_warning'] ?? 'The front ID scan detected a different ID type. Please retake the correct ID photo.')
                    : 'OCR could not confirm the selected ID type. Please retake a clearer front ID photo.');
        }

        if ($this->frontAndBackLookSame($request)) {
            return back()
                ->withInput()
                ->with('error', 'The back ID photo looks like the front side again. Please flip the ID and scan the back side.');
        }

        $faceMatch = $faceMatcher->compareUploadedFiles($request->file('id_front'), $request->file('selfie'));
        if (($faceMatch['status'] ?? 'needs_review') !== 'match') {
            return back()
                ->withInput()
                ->with('error', $faceMatch['message'] ?? 'Face verification must match the face on the ID before submission. Please retake your selfie with the correct person.');
        }

        $front = $this->uploadFile($request->file('id_front'), 'uploads/customer-ids');
        if (!$front) return back()->with('error', 'Valid ID upload failed. Please try a smaller clear image.');

        $back = $this->uploadFile($request->file('id_back'), 'uploads/customer-ids');
        if (!$back) return back()->with('error', 'Back ID upload failed. Please try a smaller clear image.');

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
                'front_back_same_check' => 'passed',
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

    private function frontAndBackLookSame(Request $request): bool
    {
        $frontHash = $this->uploadedImageHash($request->file('id_front')) ?: trim((string) $request->input('id_front_hash'));
        $backHash = $this->uploadedImageHash($request->file('id_back')) ?: trim((string) $request->input('id_back_hash'));

        if (!preg_match('/^[01]{64}$/', $frontHash) || !preg_match('/^[01]{64}$/', $backHash)) {
            return false;
        }

        return $this->hashDistance($frontHash, $backHash) <= 16;
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

        $thumb = imagecreatetruecolor(8, 8);
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, 8, 8, imagesx($source), imagesy($source));

        $grays = [];
        $total = 0;
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $rgb = imagecolorat($thumb, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $gray = ($r + $g + $b) / 3;
                $grays[] = $gray;
                $total += $gray;
            }
        }

        imagedestroy($source);
        imagedestroy($thumb);

        $average = $total / max(1, count($grays));
        return implode('', array_map(fn ($gray) => $gray >= $average ? '1' : '0', $grays));
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
