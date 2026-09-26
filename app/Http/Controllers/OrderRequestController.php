<?php

namespace App\Http\Controllers;

use App\Services\OrderRequestService;
use Illuminate\Http\Request;

class OrderRequestController extends Controller
{
    public function store(Request $request, OrderRequestService $requests)
    {
        $role = session('user')['role'] ?? null;
        $userId = $role === 'customer' ? (string) (session('user')['id'] ?? '') : null;
        $fullname = $role === 'customer' ? (string) (session('user')['fullname'] ?? '') : null;
        $phone = $role === 'customer' ? (string) (session('user')['phone'] ?? '') : null;

        $result = $requests->createReadyMade([
            'user_id' => $userId ?: null,
            'guest_name' => $request->input('guest_name', $fullname),
            'guest_phone' => $request->input('guest_phone', $phone),
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