<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function markReadAdmin()
    {
        DB::table('notifications')
            ->where('receiver_role','admin')
            ->where('is_read', false)
            ->update(['is_read' => true]);
        return back();
    }

    public function markReadCustomer()
    {
        $uid = session('user')['id'];
        DB::table('notifications')
            ->where('receiver_role','customer')
            ->where('receiver_user_id', $uid)
            ->where('is_read', false)
            ->update(['is_read' => true]);
        return back();
    }
}
