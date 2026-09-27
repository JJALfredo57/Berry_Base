<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerVerificationController extends Controller
{
    public function index()
    {
        $rows = DB::table('customer_verifications as cv')
            ->join('users as u', 'u.id', '=', 'cv.user_id')
            ->select('cv.*', 'u.fullname', 'u.email', 'u.phone')
            ->orderByRaw("CASE WHEN cv.status = 'pending' THEN 0 WHEN cv.status = 'rejected' THEN 1 ELSE 2 END")
            ->orderByDesc('cv.id')
            ->paginate(20);

        return view('admin.customer_verifications', compact('rows'));
    }

    public function approve(string $id)
    {
        $user = session('user');
        $row = DB::table('customer_verifications')->where('id', $id)->first();
        if (!$row) return back()->with('err', 'Verification request not found.');

        if (($row->id_type_match_status ?? null) === 'mismatch') {
            return back()->with('err', 'Cannot approve this request because the selected ID type does not match the reviewed ID. Reject it and ask the customer to resubmit the correct ID.');
        }

        $reviewFlags = json_decode($row->review_flags ?? '', true) ?: [];
        if (($reviewFlags['face_match_status'] ?? null) === 'mismatch') {
            return back()->with('err', 'Cannot approve this request because the selfie does not match the ID face. Reject it and ask the customer to resubmit.');
        }

        DB::table('customer_verifications')->where('id', $id)->update([
            'status' => 'approved',
            'rejection_reason' => null,
            'reviewed_by' => $user['id'] ?? null,
            'reviewed_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('notifications')->insert([
            'receiver_role' => 'customer',
            'receiver_user_id' => $row->user_id,
            'title' => 'Account Verified',
            'message' => 'Your valid ID was approved. Rewards redemption and verified-only benefits are now unlocked.',
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('msg', 'Customer verification approved.');
    }

    public function reject(Request $request, string $id)
    {
        $request->validate(['reason' => 'required|string|min:5|max:500']);
        $user = session('user');
        $row = DB::table('customer_verifications')->where('id', $id)->first();
        if (!$row) return back()->with('err', 'Verification request not found.');

        DB::table('customer_verifications')->where('id', $id)->update([
            'status' => 'rejected',
            'rejection_reason' => trim($request->input('reason')),
            'reviewed_by' => $user['id'] ?? null,
            'reviewed_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('notifications')->insert([
            'receiver_role' => 'customer',
            'receiver_user_id' => $row->user_id,
            'title' => 'Verification Needs Review',
            'message' => 'Your valid ID was not approved yet. Reason: ' . trim($request->input('reason')),
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('msg', 'Customer verification rejected with reason.');
    }

    public function flagIdType(Request $request, string $id)
    {
        if (!Schema::hasColumn('customer_verifications', 'id_type_match_status')) {
            return back()->with('err', 'ID type review fields are not available yet. Please run migrations first.');
        }

        $request->validate([
            'detected_id_type' => 'nullable|string|max:60',
            'warning' => 'required|string|min:5|max:500',
        ]);

        $row = DB::table('customer_verifications')->where('id', $id)->first();
        if (!$row) return back()->with('err', 'Verification request not found.');

        $updates = [
            'id_type_match_status' => 'mismatch',
            'id_type_match_warning' => trim($request->input('warning')),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('customer_verifications', 'scan_status')) {
            $updates['scan_status'] = 'manual_review';
        }
        if (Schema::hasColumn('customer_verifications', 'id_type_scan_detected')) {
            $updates['id_type_scan_detected'] = trim((string) $request->input('detected_id_type')) ?: null;
        }

        DB::table('customer_verifications')->where('id', $id)->update($updates);

        return back()->with('msg', 'ID type mismatch warning saved. This request cannot be approved until the customer resubmits the correct ID.');
    }
}
