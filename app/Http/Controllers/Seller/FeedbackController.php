<?php
namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Traits\UploadsFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FeedbackController extends Controller
{
    use UploadsFiles;

    private function getShop(): object
    {
        $uid = session('user')['id'];
        $shop = DB::table('shops')->where('seller_id', $uid)->first();
        if (!$shop) abort(403);
        return $shop;
    }

    public function create()
    {
        $shop = $this->getShop();
        $uid = session('user')['id'];

        $recentFeedback = DB::table('customer_feedback')
            ->where('user_id', $uid)
            ->when(Schema::hasColumn('customer_feedback', 'source_role'), fn($q) => $q->where('source_role', 'seller'))
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        return view('seller.feedback', compact('shop', 'recentFeedback'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category' => 'required|in:suggestion,bug,experience,feature,other',
            'title'    => 'required|string|min:4|max:120',
            'message'  => 'required|string|min:10|max:1200',
            'attachments'   => 'nullable|array|max:5',
            'attachments.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ], [
            'title.required'   => 'Please add a short title.',
            'title.max'        => 'Title can only be up to 120 characters.',
            'message.required' => 'Please describe your feedback.',
            'message.max'      => 'Feedback can only be up to 1200 characters.',
            'attachments.max'  => 'You can attach up to 5 images only.',
            'attachments.*.image' => 'Attachments must be image files.',
            'attachments.*.max'   => 'Each image can only be up to 5MB.',
        ]);

        $shop = $this->getShop();
        $user = session('user');
        $attachments = $this->storeFeedbackAttachments($request);

        $data = [
            'user_id'    => $user['id'] ?? null,
            'name'       => trim(($shop->shop_name ?? 'Seller') . ' - ' . ($user['fullname'] ?? $user['username'] ?? 'Seller')),
            'email'      => $user['email'] ?? null,
            'category'   => $validated['category'],
            'title'      => trim($validated['title']),
            'message'    => trim($validated['message']),
            'status'     => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('customer_feedback', 'source_role')) $data['source_role'] = 'seller';
        if (Schema::hasColumn('customer_feedback', 'shop_id')) $data['shop_id'] = $shop->id;
        if (Schema::hasColumn('customer_feedback', 'attachment_paths')) {
            $data['attachment_paths'] = !empty($attachments) ? json_encode($attachments) : null;
        }

        DB::table('customer_feedback')->insert($data);

        DB::table('notifications')->insert([
            'receiver_role' => 'superadmin',
            'title'         => 'New seller feedback',
            'message'       => ($shop->shop_name ?? 'Seller') . ': ' . trim($validated['title']),
            'is_read'       => false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return back()->with('msg', 'Thanks. Your feedback was sent to the platform team.');
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
