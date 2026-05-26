<?php
namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeedbackController extends Controller
{
    public function create()
    {
        $recentFeedback = collect();
        $isPublicFeedback = true;

        return view('customer.feedback', compact('recentFeedback', 'isPublicFeedback'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category' => 'required|in:suggestion,bug,experience,feature,other',
            'name'     => 'nullable|string|max:120',
            'email'    => 'nullable|email|max:150',
            'title'    => 'required|string|min:4|max:120',
            'message'  => 'required|string|min:10|max:1000',
        ], [
            'title.required'   => 'Please add a short title.',
            'title.max'        => 'Title can only be up to 120 characters.',
            'message.required' => 'Please share your feedback.',
            'message.max'      => 'Feedback can only be up to 1000 characters.',
        ]);

        DB::table('customer_feedback')->insert([
            'user_id'    => null,
            'name'       => trim($validated['name'] ?? '') ?: 'Guest Customer',
            'email'      => trim($validated['email'] ?? '') ?: null,
            'category'   => $validated['category'],
            'title'      => trim($validated['title']),
            'message'    => trim($validated['message']),
            'status'     => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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
}
