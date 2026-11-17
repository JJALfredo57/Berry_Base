<?php
namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\OrderRefundService;
use App\Services\PostDeliveryReturnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PostDeliveryReturnController extends Controller {
    public function __construct(private PostDeliveryReturnService $returns) {}
    private function shop(): ?object { return DB::table('shops')->where('seller_id', session('user')['id'])->first(); }
    public function index() {
        $shop=$this->shop(); if(!$shop) abort(404);
        $note=Schema::hasColumn('post_delivery_return_cases','seller_decision_note')?'c.seller_decision_note':'c.seller_response';
        $returns=fn()=>DB::table('post_delivery_return_cases as c')->join('orders as o','o.id','=','c.order_id')->leftJoin('users as u','u.id','=','c.customer_user_id')->where('c.shop_id',$shop->id)->selectRaw("'return' source,c.id record_id,c.case_number reference,c.order_id,u.fullname customer_name,c.status,COALESCE(c.approved_amount,c.requested_amount,0) refund_amount,c.reason,c.description,{$note} decision_note,o.fulfillment_type,c.created_at");
        $refunds=fn()=>DB::table('order_refunds as r')->join('orders as o','o.id','=','r.order_id')->leftJoin('users as u','u.id','=','o.user_id')->where('r.shop_id',$shop->id)->selectRaw("'refund' source,r.id record_id,CONCAT('REF-',r.id) reference,r.order_id,u.fullname customer_name,r.status,r.refund_amount,'cancellation' reason,r.cancel_reason description,r.review_note decision_note,o.fulfillment_type,r.created_at");
        $page=fn($a,$b,$key)=>DB::query()->fromSub($a->unionAll($b),'seller_request_items')->orderByDesc('created_at')->paginate(10,['*'],$key)->withQueryString();
        $activeItems=$page($returns()->whereNotIn('c.status',['refunded','seller_rejected']),$refunds()->whereIn('r.status',['pending','pending_seller_approval','approved_for_admin_refund','processing']),'action_page');
        $historyItems=$page($returns()->whereIn('c.status',['refunded','seller_rejected']),$refunds()->whereIn('r.status',['refunded','rejected']),'history_page');
        return view('seller.return_cases',compact('activeItems','historyItems'));
    }
    public function decide(Request $r,int $id){$data=$r->validate(['decision'=>['required','in:approved,rejected'],'note'=>['nullable','string','max:1500']]);$case=DB::table('post_delivery_return_cases as c')->join('orders as o','o.id','=','c.order_id')->where('c.id',$id)->select('o.fulfillment_type')->first();try{$this->returns->sellerDecide($id,(string)session('user')['id'],$data['decision'],trim((string)($data['note']??'')));}catch(\DomainException $e){return back()->with('err',$e->getMessage());}$message=$data['decision']==='approved'?(($case->fulfillment_type??'')==='Pickup'?'Pickup return approved. The refund is ready for Super Admin processing.':'Return approved. The rider may collect the cake.'):'Return request rejected.';return back()->with('msg',$message);}
    public function receive(int $id){try{$this->returns->sellerReceive($id,(string)session('user')['id']);}catch(\DomainException $e){return back()->with('err',$e->getMessage());}return back()->with('msg','Cake receipt confirmed. Refund is ready for Super Admin processing.');}
    private function sellerRefund(int $id): array { $shop=$this->shop();$refund=$shop?DB::table('order_refunds')->where('id',$id)->where('shop_id',$shop->id)->first():null;if(!$refund)abort(404);$order=DB::table('orders')->where('id',$refund->order_id)->where('shop_id',$shop->id)->first();if(!$order)abort(404);return[$shop,$order,$refund]; }
    public function approveRefund(int $id,OrderRefundService $refunds){[, $order,$refund]=$this->sellerRefund($id);try{$refunds->approveForAdminRefund($order,$refund,(string)(session('user')['id']??''));}catch(\DomainException $e){return back()->with('err',$e->getMessage());}return back()->with('msg',"Cancellation refund for Order #{$order->id} was approved. Super Admin will process the payment.");}
    public function rejectRefund(Request $r,int $id,OrderRefundService $refunds){$data=$r->validate(['note'=>['required','string','min:5','max:500']]);[, $order,$refund]=$this->sellerRefund($id);try{$refunds->reject($order,$refund,trim($data['note']),'seller',(string)(session('user')['id']??''));}catch(\DomainException $e){return back()->with('err',$e->getMessage());}return back()->with('msg',"Cancellation refund for Order #{$order->id} was rejected.");}
}
