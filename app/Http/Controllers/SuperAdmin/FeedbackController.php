<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Helpers\CakeshopHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FeedbackController extends Controller
{
    public function index(Request $request)
    {
        $search = trim($request->input('search', ''));
        $status = $request->input('status', 'all');
        $category = $request->input('category', 'all');
        $source = $request->input('source', 'all');
        $hasSource = Schema::hasColumn('customer_feedback', 'source_role');

        $query = DB::table('customer_feedback as f')
            ->leftJoin('users as u', 'u.id', '=', 'f.user_id')
            ->select('f.*', 'u.fullname as user_fullname', 'u.username as user_username', 'u.email as user_email')
            ->when($search, fn($q) => $q->where(fn($sq) => $sq
                ->where('f.title', 'like', "%{$search}%")
                ->orWhere('f.message', 'like', "%{$search}%")
                ->orWhere('f.name', 'like', "%{$search}%")
                ->orWhere('f.email', 'like', "%{$search}%")
                ->orWhere('u.fullname', 'like', "%{$search}%")
                ->orWhere('u.email', 'like', "%{$search}%")
            ))
            ->when(in_array($status, ['open', 'done'], true), fn($q) => $q->where('f.status', $status))
            ->when(in_array($category, ['suggestion', 'bug', 'experience', 'feature', 'other'], true), fn($q) => $q->where('f.category', $category))
            ->when($hasSource && in_array($source, ['customer', 'guest', 'seller'], true), fn($q) => $q->where('f.source_role', $source))
            ->orderByRaw("CASE WHEN f.status = 'open' THEN 0 ELSE 1 END")
            ->orderByDesc('f.created_at');

        $feedback = $query->paginate(12)->withQueryString();

        $stats = [
            'total' => DB::table('customer_feedback')->count(),
            'open'  => DB::table('customer_feedback')->where('status', 'open')->count(),
            'done'  => DB::table('customer_feedback')->where('status', 'done')->count(),
        ];

        return view('superadmin.feedback', compact('feedback', 'stats', 'search', 'status', 'category', 'source', 'hasSource'));
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'status'     => 'required|in:open,done',
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $feedback = DB::table('customer_feedback')->where('id', $id)->first();
        if (!$feedback) {
            return back()->with('err', 'Feedback not found.');
        }

        $updates = [
            'status'     => $validated['status'],
            'admin_note' => trim((string) ($validated['admin_note'] ?? '')) ?: null,
            'updated_at' => now(),
        ];

        if ($validated['status'] === 'done' && $feedback->status !== 'done') {
            $updates['resolved_by'] = session('user')['id'] ?? null;
            $updates['resolved_at'] = now();
        } elseif ($validated['status'] === 'open') {
            $updates['resolved_by'] = null;
            $updates['resolved_at'] = null;
        }

        DB::table('customer_feedback')->where('id', $id)->update($updates);

        CakeshopHelper::logActivity(session('user')['id'], 'superadmin', 'Update Feedback', "Feedback #{$id} marked {$validated['status']}");

        return back()->with('msg', 'Feedback updated.');
    }
}
