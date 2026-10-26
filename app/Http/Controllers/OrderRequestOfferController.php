<?php

namespace App\Http\Controllers;

use App\Services\OrderRequestService;
use Illuminate\Http\Request;

class OrderRequestOfferController extends Controller
{
    public function show(Request $request, OrderRequestService $service, string $id, ?string $token = null)
    {
        $role = session('user')['role'] ?? null;
        $userId = $role === 'customer' ? (string) (session('user')['id'] ?? '') : null;
        $offer = $service->findForCustomer($id, $token, $userId);
        if (!$offer) abort(404);

        if (!empty($offer->user_id) && !$userId) {
            $request->session()->put('customer_intended_url', '/'.$request->path());
            return redirect()->route('login')->with('msg', 'Please sign in to view your cake request.');
        }
        $finalPrice = $service->finalOfferPrice($offer);
        $isCustomer = (bool) $userId;
        return view('order_requests.offer', compact('offer', 'finalPrice', 'token', 'isCustomer'));
    }

    public function accept(Request $request, OrderRequestService $service, string $id, ?string $token = null)
    {
        $role = session('user')['role'] ?? null;
        $userId = $role === 'customer' ? (string) (session('user')['id'] ?? '') : null;
        $offer = $service->findForCustomer($id, $token, $userId);
        if (!$offer) abort(404);

        if (!$userId) {
            return redirect()->route('login')->with('error', 'Please sign in and submit a request from your customer account to continue ordering.');
        }
        $result = $service->prepareCheckoutFromOffer($request, $offer, (bool) $userId);
        if (!$result['ok']) return back()->with('error', $result['message']);

        return redirect()->route('customer.checkout')
            ->with('msg', 'Offer accepted. Please complete checkout details to place the order.');
    }

    public function decline(Request $request, OrderRequestService $service, string $id, ?string $token = null)
    {
        $request->validate(['customer_decision_note' => 'nullable|string|max:500']);
        $role = session('user')['role'] ?? null;
        $userId = $role === 'customer' ? (string) (session('user')['id'] ?? '') : null;
        $offer = $service->findForCustomer($id, $token, $userId);
        if (!$offer) abort(404);

        if (!empty($offer->user_id) && !$userId) {
            return redirect()->route('login')->with('error', 'Please sign in to update your cake request.');
        }
        $result = $service->customerDecline($offer, $request->input('customer_decision_note'));
        return back()->with($result['ok'] ? 'msg' : 'error', $result['message']);
    }
}
