<?php

namespace App\Http\Controllers;

use App\Services\DeliveryMessageService;
use App\Traits\UploadsFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryMessageController extends Controller
{
    use UploadsFiles;

    public function customerIndex(Request $request, string $orderId, DeliveryMessageService $messages)
    {
        $order = $messages->customerConversation($orderId, (array) session('user'));
        $messages->markRead($order, 'customer');
        return response()->json(['ok' => true, 'messages' => $messages->messages($order)]);
    }

    public function customerSend(Request $request, string $orderId, DeliveryMessageService $messages)
    {
        $order = $messages->customerConversation($orderId, (array) session('user'));
        $payload = $this->validatedPayload($request);
        $message = $messages->send($order, 'customer', (int) session('user.id'), $payload['message'], $this->storeImage($request));
        return response()->json(['ok' => true, 'message' => $message], 201);
    }

    public function customerRead(Request $request, string $orderId, DeliveryMessageService $messages)
    {
        $order = $messages->customerConversation($orderId, (array) session('user'));
        return response()->json(['ok' => true, 'read' => $messages->markRead($order, 'customer')]);
    }

    public function riderIndex(Request $request, string $orderId, DeliveryMessageService $messages)
    {
        $order = $messages->riderConversation($orderId, $this->rider());
        $messages->markRead($order, 'rider');
        return response()->json(['ok' => true, 'messages' => $messages->messages($order)]);
    }

    public function riderSend(Request $request, string $orderId, DeliveryMessageService $messages)
    {
        $rider = $this->rider();
        $order = $messages->riderConversation($orderId, $rider);
        $payload = $this->validatedPayload($request);
        $message = $messages->send($order, 'rider', (int) $rider->id, $payload['message'], $this->storeImage($request));
        return response()->json(['ok' => true, 'message' => $message], 201);
    }

    public function riderRead(Request $request, string $orderId, DeliveryMessageService $messages)
    {
        $order = $messages->riderConversation($orderId, $this->rider());
        return response()->json(['ok' => true, 'read' => $messages->markRead($order, 'rider')]);
    }

    private function validatedPayload(Request $request): array
    {
        return $request->validate([
            'message' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
    }

    private function storeImage(Request $request): ?string
    {
        if (!$request->hasFile('image')) {
            return null;
        }
        $path = $this->uploadFile($request->file('image'), 'uploads/delivery_messages');
        if (!$path) {
            throw ValidationException::withMessages(['image' => 'We could not save that image. Please try again.']);
        }
        return $path;
    }

    private function rider(): object
    {
        $id = session('rider.id');
        $rider = $id ? DB::table('riders')->where('id', $id)->where('is_active', true)->first() : null;
        if (!$rider) {
            abort(401, 'Please log in to your rider account.');
        }
        return $rider;
    }
}
