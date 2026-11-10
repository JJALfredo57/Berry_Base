<?php
namespace App\Http\Controllers\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Services\OrderActionService;

class DashboardController extends Controller
{
    public function index()
    {
        $uid = session('user')['id'];
        // Mark notifications as read
        DB::table('notifications')
            ->where('receiver_role','customer')
            ->where('receiver_user_id', $uid)
            ->update(['is_read' => true]);
        $customerActionSummary = app(OrderActionService::class)->customerSummary((string) $uid);
        return view('customer.dashboard', compact('customerActionSummary'));
    }
}
