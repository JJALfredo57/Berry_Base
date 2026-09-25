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

        $result = $service->prepareCheckoutFromOffer($request, $offer, (bool) $userId);
        if (!$result['ok']) return back()->with('error', $result['message']);

        return redirect()->route($userId ? 'customer.checkout' : 'guest.checkout')
            ->with('msg', 'Offer accepted. Please complete checkout details to place the order.');
    }

    public function decline(Request $request, OrderRequestService $service, string $id, ?string $token = null)
    {
        $role = session('user')['role'] ?? null;
        $userId = $role === 'customer' ? (string) (session('user')['id'] ?? '') : null;
        $offer = $service->findForCustomer($id, $token, $userId);
        if (!$offer) abort(404);

        $result = $service->customerDecline($offer, $request->input('customer_decision_note'));
        return back()->with($result['ok'] ? 'msg' : 'error', $result['message']);
    }
}
