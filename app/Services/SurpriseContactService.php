<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SurpriseContactService
{
    public function details(object $order): array
    {
        $registered = null;
        if (!empty($order->user_id) && Schema::hasTable('users')) {
            $registered = DB::table('users')->where('id', $order->user_id)->first(['fullname', 'phone']);
        }

        $senderName = trim((string) ($registered->fullname ?? $order->guest_name ?? $order->fullname ?? 'Sender'));
        $senderPhone = trim((string) ($registered->phone ?? $order->guest_phone ?? ''));
        $recipientName = trim((string) ($order->recipient_name ?? 'Recipient')) ?: 'Recipient';
        $recipientPhone = trim((string) ($order->recipient_phone ?? ''));

        return [
            'policy' => 'sender_first',
            'instruction' => 'Contact sender first. Use recipient phone only as backup if delivery cannot be completed. Do not mention price to recipient.',
            'sender_name' => $senderName,
            'sender_phone' => $senderPhone,
            'recipient_name' => $recipientName,
            'recipient_phone' => $recipientPhone,
            'primary_name' => $senderName,
            'primary_phone' => $senderPhone,
            'backup_name' => $recipientPhone !== '' ? $recipientName : null,
            'backup_phone' => $recipientPhone !== '' ? $recipientPhone : null,
        ];
    }
}
