<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RiderController extends Controller
{
    public function index(Request $request)
    {
        $search = trim($request->input('search', ''));
        $riders = DB::table('riders')
            ->when($search, fn($q) => $q->where(fn($sq) => $sq
                ->where('name', 'like', "%$search%")
                ->orWhere('phone', 'like', "%$search%")
                ->orWhere('vehicle_type', 'like', "%$search%")
            ))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();
        $riderIds = collect($riders->items())->pluck('id')->toArray();
        $incidents = []; $deliveries = [];
        if ($riderIds) {
            foreach (DB::table('orders')->whereIn('rider_id',$riderIds)->whereNotNull('issue_type')
                ->select('rider_id',DB::raw('count(*) as cnt'))->groupBy('rider_id')->get() as $i)
                $incidents[$i->rider_id] = $i->cnt;
            foreach (DB::table('orders')->whereIn('rider_id',$riderIds)->where('status','Delivered')
                ->select('rider_id',DB::raw('count(*) as cnt'))->groupBy('rider_id')->get() as $d)
                $deliveries[$d->rider_id] = $d->cnt;
        }
        return view('admin.riders', compact('riders','incidents','deliveries','search'));
    }

    public function store(Request $request)
    {
        $name    = trim($request->input('name',''));
        $phone   = trim($request->input('phone',''));
        $nick    = trim($request->input('nickname',''));
        $vtype   = $request->input('vehicle_type','Motorcycle');
        $plate   = trim($request->input('license_plate',''));
        $ecName  = trim($request->input('emergency_contact_name',''));

        if (!$name)  return back()->with('err','Full name is required.')->withInput();

        $phone = preg_replace('/\D/', '', $phone);
        if (strlen($phone) === 10) $phone = '+63' . $phone;
        elseif (strlen($phone) === 11 && $phone[0] === '0') $phone = '+63' . substr($phone, 1);
        elseif ($phone === '') $phone = '';

        // Duplicate checks
        if (DB::table('riders')->whereRaw('LOWER(name) = ?',[strtolower($name)])->exists())
            return back()->with('err',"Rider named \"{$name}\" already exists.")->withInput();
        if ($phone !== '' && DB::table('riders')->where('phone',$phone)->exists())
            return back()->with('err',"Phone {$phone} is already used by another rider.")->withInput();

        DB::table('riders')->insert([
            'name'                   => $name,
            'nickname'               => $nick ?: null,
            'phone'                  => $phone,
            'vehicle_type'           => $vtype,
            'license_plate'          => $plate ?: null,
            'emergency_contact_name' => $ecName ?: null,
            'is_active' => true,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        CakeshopHelper::logActivity(session('user')['id'], 'admin', 'Add Rider', "Added: {$name}");
        return back()->with('msg', "Rider {$name} added! ✅");
    }

    public function update(Request $request, int $id)
    {
        $rider = DB::table('riders')->where('id',$id)->first();
        if (!$rider) return back()->with('err','Rider not found.');

        $name    = trim($request->input('name',''));
        $phone   = trim($request->input('phone',''));
        $nick    = trim($request->input('nickname',''));
        $vtype   = $request->input('vehicle_type','Motorcycle');
        $plate   = trim($request->input('license_plate',''));
        $ecName  = trim($request->input('emergency_contact_name',''));

        if (!$name)  return back()->with('err','Full name is required.')->withInput();

        $phone = preg_replace('/\D/', '', $phone);
        if (strlen($phone) === 10) $phone = '+63' . $phone;
        elseif (strlen($phone) === 11 && $phone[0] === '0') $phone = '+63' . substr($phone, 1);
        elseif ($phone === '') $phone = '';

        // Duplicate checks (exclude self)
        if (DB::table('riders')->whereRaw('LOWER(name) = ?',[strtolower($name)])->where('id','!=',$id)->exists())
            return back()->with('err',"Rider named \"{$name}\" already exists.")->withInput();
        if ($phone !== '' && DB::table('riders')->where('phone',$phone)->where('id','!=',$id)->exists())
            return back()->with('err',"Phone {$phone} is already used by another rider.")->withInput();

        DB::table('riders')->where('id',$id)->update([
            'name'                   => $name,
            'nickname'               => $nick ?: null,
            'phone'                  => $phone,
            'vehicle_type'           => $vtype,
            'license_plate'          => $plate ?: null,
            'emergency_contact_name' => $ecName ?: null,
            'updated_at'             => now(),
        ]);

        CakeshopHelper::logActivity(session('user')['id'], 'admin', 'Update Rider', "Updated: {$name}");
        return back()->with('msg', "Rider {$name} updated! ✅");
    }

    public function toggle(int $id)
    {
        $rider = DB::table('riders')->where('id',$id)->first();
        if (!$rider) return back()->with('err','Rider not found.');
        DB::table('riders')->where('id',$id)->update(['is_active' => !$rider->is_active]);
        $action = $rider->is_active ? 'deactivated' : 'activated';
        CakeshopHelper::logActivity(session('user')['id'], 'admin', 'Toggle Rider', "{$rider->name} {$action}");
        return back()->with('msg', "Rider {$rider->name} {$action}.");
    }

    public function resolveIssue(Request $request, string $orderId)
    {
        $order = DB::table('orders')->where('id',$orderId)->first();
        if (!$order) return back()->with('err','Order not found.');

        $liability  = $request->input('liability');
        $resolution = $request->input('resolution_type');
        $amount     = (float)$request->input('issue_amount',0);
        $note       = trim($request->input('resolution_note',''));

        DB::table('orders')->where('id',$orderId)->update([
                        'issue_amount'      => $liability === 'rider_liable' ? $amount : null,
                        'resolution_note'   => $note ?: null,
            'issue_resolved_at' => now(),
        ]);

        if ($liability === 'rider_liable' && $order->rider_id)
            DB::table('riders')->where('id',$order->rider_id)->increment('incidents_count');

        DB::table('order_tracking')->insert([
            'order_id'   => $orderId,
            'status'     => 'Issue Resolved',
            'notes'      => "Resolution: {$resolution}" . ($note ? " — {$note}" : ''),
            'created_at' => now(),
        ]);

        CakeshopHelper::logActivity(session('user')['id'], 'admin', 'Resolve Issue', "Order #{$orderId} — {$resolution}");
        return back()->with('msg', "Issue resolved! Message the customer about the resolution. ✅");
    }

    public function markSettled(string $orderId)
    {
        DB::table('orders')->where('id',$orderId)->update(['settled_at' => now()]);
        return back()->with('msg', "Settlement marked as paid. ✅");
    }
}
