<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
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
            'id_front' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $selectedIdType = trim($request->input('id_type'));
        $scan = $identityScanner->scanUploadedFile($selectedIdType, $request->file('id_front'));
        $status = $scan['id_type_match_status'] ?? 'needs_review';

        return response()->json([
            'ok' => $status === 'match',
            'can_continue' => $status === 'match',
            'match_status' => $status,
            'scan_status' => $scan['scan_status'] ?? 'needs_review',
            'expected_id_type' => $scan['id_type_scan_expected'] ?? $selectedIdType,
            'detected_id_type' => $scan['id_type_scan_detected'] ?? null,
            'message' => $status === 'match'
                ? 'ID type matched. You may scan the back of the ID.'
                : ($scan['id_type_match_warning'] ?? 'ID scan could not verify the selected ID type. Please retake the front ID photo.'),
        ]);
    }

    public function store(Request $request, IdentityVerificationSettingsService $identitySettings, CustomerIdentityScanService $identityScanner)
    {
        $idTypes = $identitySettings->typeNames();

        $request->validate([
            'id_type' => ['required', 'string', 'max:60', Rule::in($idTypes)],
            'id_front' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120',
            'id_back' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120',
            'selfie' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120',
            'customer_note' => 'nullable|string|max:500',
        ]);

        $selectedIdType = trim($request->input('id_type'));
        $scan = $identityScanner->scanUploadedFile($selectedIdType, $request->file('id_front'));
        if (($scan['id_type_match_status'] ?? 'needs_review') !== 'match') {
            return back()
                ->withInput()
                ->with('error', $scan['id_type_match_warning'] ?? 'The front ID scan did not match the selected ID type. Please retake the front ID photo.');
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
                'ocr_needs_review' => false,
                'id_type_mismatch' => false,
                'selfie_required' => true,
                'guided_capture' => true,
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
}