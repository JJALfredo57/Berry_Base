<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Traits\UploadsFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FeedbackController extends Controller
{
    use UploadsFiles;

    public function create()
    {
        $uid = session('user')['id'];
        $recentFeedback = DB::table('customer_feedback')
            ->where('user_id', $uid)
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return view('customer.feedback', compact('recentFeedback'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category' => 'required|in:suggestion,bug,experience,feature,other',
            'title'    => 'required|string|min:4|max:120',
            'message'  => 'required|string|min:10|max:1000',
            'attachments'   => 'nullable|array|max:5',
            'attachments.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ], [
            'title.required'   => 'Please add a short title.',
            'title.max'        => 'Title can only be up to 120 characters.',
            'message.required' => 'Please share your feedback.',
            'message.max'      => 'Feedback can only be up to 1000 characters.',
            'attachments.max'  => 'You can attach up to 5 images only.',
            'attachments.*.image' => 'Attachments must be image files.',
            'attachments.*.max'   => 'Each image can only be up to 5MB.',
        ]);

        $user = session('user');
        $attachments = $this->storeFeedbackAttachments($request);

        $data = [
            'user_id'    => $user['id'] ?? null,
            'name'       => $user['fullname'] ?? $user['username'] ?? 'Customer',
            'email'      => $user['email'] ?? null,
            'category'   => $validated['category'],
            'title'      => trim($validated['title']),
            'message'    => trim($validated['message']),
            'status'     => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('customer_feedback', 'source_role')) $data['source_role'] = 'customer';
        if (Schema::hasColumn('customer_feedback', 'attachment_paths')) {
            $data['attachment_paths'] = !empty($attachments) ? json_encode($attachments) : null;
        }

        DB::table('customer_feedback')->insert($data);

        DB::table('notifications')->insert([
            'receiver_role' => 'superadmin',
            'title'         => 'New customer feedback',
            'message'       => trim($validated['title']),
            'is_read'       => false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return back()->with('msg', 'Thank you. Your feedback was sent to the super admin.');
    }

    private function storeFeedbackAttachments(Request $request): array
    {
        $paths = [];
        if (!$request->hasFile('attachments')) return $paths;

        foreach (array_slice($request->file('attachments'), 0, 5) as $file) {
            if (!$file || !$file->isValid() || $file->getSize() > 5 * 1024 * 1024) continue;
            $ext = strtolower($file->getClientOriginalExtension());
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) continue;
            $path = $this->uploadFile($file, 'uploads/feedback');
            if ($path) $paths[] = $path;
        }

        return $paths;
    }
}
