<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\CustomerVerificationService;
use App\Traits\UploadsFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VerificationController extends Controller
{
    use UploadsFiles;

    public function show(CustomerVerificationService $verification)
    {
        $uid = session('user')['id'];
        $latest = $verification->latest($uid);
        $status = $latest->status ?? 'not_submitted';
        $benefits = $verification->benefits($status);
        $limitations = $verification->limitations($status);

        return view('customer.verification', compact('latest', 'status', 'benefits', 'limitations'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_type' => 'required|string|max:60',
            'id_front' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'id_back' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'selfie' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
            'customer_note' => 'nullable|string|max:500',
        ]);

        $front = $this->uploadFile($request->file('id_front'), 'uploads/customer-ids');
        if (!$front) return back()->with('error', 'Valid ID upload failed. Please try a smaller clear image.');

        $back = $request->hasFile('id_back') ? $this->uploadFile($request->file('id_back'), 'uploads/customer-ids') : null;
        $selfie = $request->hasFile('selfie') ? $this->uploadFile($request->file('selfie'), 'uploads/customer-ids') : null;

        DB::table('customer_verifications')->insert([
            'user_id' => session('user')['id'],
            'id_type' => trim($request->input('id_type')),
            'id_front_path' => $front,
            'id_back_path' => $back,
            'selfie_path' => $selfie,
            'status' => 'pending',
            'customer_note' => trim((string) $request->input('customer_note')) ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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
