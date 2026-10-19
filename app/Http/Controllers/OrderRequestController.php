<?php

namespace App\Http\Controllers;

use App\Services\OrderRequestService;
use Illuminate\Http\Request;

class OrderRequestController extends Controller
{
    public function store(Request $request, OrderRequestService $requests)
    {
        $role = session('user')['role'] ?? null;
        if ($role !== 'customer') {
            return redirect()->route('login')->with('error', 'Please use a customer account to submit a cake request.');
        }
        $request->validate([
            'product_id' => 'required|string|max:80',
            'type' => 'nullable|string|max:40',
            'source' => 'nullable|string|max:40',
            'request_reason' => 'nullable|string|max:80',
            'quantity' => 'nullable|integer|min:1|max:20',
            'selected_size' => 'nullable|string|max:120',
            'preferred_date' => 'nullable|date_format:Y-m-d',
            'preferred_time' => 'nullable|date_format:H:i',
            'customer_note' => 'nullable|string|max:500',
        ]);
        $userId = $role === 'customer' ? (string) (session('user')['id'] ?? '') : null;
        $fullname = $role === 'customer' ? (string) (session('user')['fullname'] ?? '') : null;
        $phone = $role === 'customer' ? (string) (session('user')['phone'] ?? '') : null;

        $result = $requests->createReadyMade([
            'user_id' => $userId ?: null,
            'guest_name' => $fullname,
            'guest_phone' => $phone,
            'product_id' => $request->input('product_id'),
            'type' => $request->input('type', 'ready_made'),
            'source' => $request->input('source', 'shop'),
            'request_reason' => $request->input('request_reason', 'out_of_stock'),
            'quantity' => $request->input('quantity', 1),
            'selected_size' => $request->input('selected_size'),
            'preferred_date' => $request->input('preferred_date'),
            'preferred_time' => $request->input('preferred_time'),
            'allow_similar_cake' => $request->boolean('allow_similar_cake'),
            'customer_note' => $request->input('customer_note'),
        ]);

        if (!$result['ok']) {
            return back()->with('error', $result['message'])->withInput();
        }

        if ($role === 'customer') {
            return redirect()->route('customer.order_requests.index')->with('msg', $result['message']);
        }

        return back()->with('msg', $result['message']);
    }
}
