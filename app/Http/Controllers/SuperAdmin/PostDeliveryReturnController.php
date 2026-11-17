<?php
namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\PayoutReceiptScanService;
use App\Services\PostDeliveryReturnService;
use App\Services\OrderRefundService;
use App\Traits\UploadsFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PostDeliveryReturnController extends Controller
{
    use UploadsFiles;
    public function __construct(private PostDeliveryReturnService $returns, private PayoutReceiptScanService $scanner) {}
    public function index()
    {
        $returnDecisionColumn = Schema::hasColumn('post_delivery_return_cases', 'seller_decision_note')
            ? 'c.seller_decision_note'
            : 'c.seller_response';
        $returnItems = fn () => DB::table('post_delivery_return_cases as c')
            ->join('orders as o', 'o.id', '=', 'c.order_id')->leftJoin('shops as s', 's.id', '=', 'c.shop_id')->leftJoin('users as u', 'u.id', '=', 'c.customer_user_id')
            ->selectRaw("'return' as source, c.id as record_id, c.case_number as reference, c.order_id, s.shop_name, u.fullname as customer_name, c.refund_gcash_name, c.refund_gcash_number, c.status, COALESCE(c.approved_amount, c.requested_amount, 0) as refund_amount, c.description, {$returnDecisionColumn} as decision_note, o.fulfillment_type, c.refund_reference as reference_number, c.created_at");
        $refundItems = fn () => DB::table('order_refunds as r')
            ->join('orders as o', 'o.id', '=', 'r.order_id')->leftJoin('shops as s', 's.id', '=', 'r.shop_id')->leftJoin('users as u', 'u.id', '=', 'o.user_id')
            ->selectRaw("'refund' as source, r.id as record_id, CONCAT('REF-', r.id) as reference, r.order_id, s.shop_name, u.fullname as customer_name, r.refund_gcash_name, r.refund_gcash_number, r.status, r.refund_amount, r.cancel_reason as description, r.review_note as decision_note, o.fulfillment_type, r.reference_number, r.created_at");
        $paginate = fn ($returns, $refunds, string $pageName) => DB::query()->fromSub($returns->unionAll($refunds), 'unified_items')->orderByDesc('created_at')->paginate(10, ['*'], $pageName)->withQueryString();
        $activeItems = $paginate($returnItems()->whereNotIn('c.status', ['refunded', 'seller_rejected']), $refundItems()->whereNotIn('r.status', ['refunded', 'rejected']), 'action_page');
        $historyItems = $paginate($returnItems()->whereIn('c.status', ['refunded', 'seller_rejected']), $refundItems()->whereIn('r.status', ['refunded', 'rejected']), 'history_page');
        $returnIds = collect($activeItems->items())->merge($historyItems->items())->where('source', 'return')->pluck('record_id')->unique()->values();
        $evidence = $returnIds->isEmpty() ? collect() : DB::table('post_delivery_return_evidence')->whereIn('return_case_id', $returnIds)->get()->groupBy('return_case_id');
        return view('superadmin.return_cases', compact('activeItems', 'historyItems', 'evidence'));
    }
    public function scanReceipt(Request $request,int $caseId) { $request->validate(['receipt'=>['required','image','mimes:jpg,jpeg,png,webp','max:5120']]); $case=DB::table('post_delivery_return_cases')->where('id',$caseId)->first(); if(!$case)return response()->json(['ok'=>false,'errors'=>['Return case not found.']],404); $scan=$this->scanReturnReceipt($request->file('receipt'),$case); return response()->json($scan,$scan['ok']?200:422); }
 public function refund(Request $request,int $caseId,OrderRefundService $refunds) { $data=$request->validate(['receipt'=>['required','image','mimes:jpg,jpeg,png,webp','max:5120'],'reference_number'=>['required','string','min:6','max:120']]); $case=DB::table('post_delivery_return_cases')->where('id',$caseId)->first(); if(!$case) abort(404); $scan=$this->scanReturnReceipt($request->file('receipt'),$case); if(!$scan['ok']||!hash_equals((string)$scan['reference_number'],trim($data['reference_number']))) return back()->with('err',implode(' ',$scan['errors']??['Receipt verification failed.'])); $path=$this->uploadFile($request->file('receipt'),'uploads/post_delivery_refunds'); if(!$path)return back()->with('err','Receipt upload failed.'); try{$this->returns->completeRefund($caseId,(string)session('user')['id'],trim($data['reference_number']),$path,hash_file('sha256',$request->file('receipt')->getRealPath()));$order=DB::table('orders')->where('id',$case->order_id)->first();if($order)$refunds->createSellerRecovery($order,(float)($case->approved_amount??$case->requested_amount??0),'RET'.$caseId,'Refund recovery for return case #'.$caseId.' on Order #'.$case->order_id.'.');}catch(\DomainException $e){return back()->with('err',$e->getMessage());} return back()->with('msg','Refund receipt verified and refund marked completed.'); }
    public function scanOrderRefundReceipt(Request $request,int $refundId) { $request->validate(['refund_receipt'=>['required','image','mimes:jpg,jpeg,png,webp','max:5120']]); $refund=DB::table('order_refunds')->where('id',$refundId)->first(); if(!$refund||!in_array($refund->status,['approved_for_admin_refund','processing'],true))return response()->json(['ok'=>false,'errors'=>['This refund is not awaiting Super Admin action.']],422); $scan=$this->scanner->scanRefund($request->file('refund_receipt'),$refund); return response()->json($scan,$scan['ok']?200:422); }
    public function completeOrderRefund(Request $request,int $refundId,OrderRefundService $refunds) { $data=$request->validate(['refund_receipt'=>['required','image','mimes:jpg,jpeg,png,webp','max:5120'],'reference_number'=>['required','string','min:6','max:120'],'admin_note'=>['nullable','string','max:500']]); $refund=DB::table('order_refunds')->where('id',$refundId)->first(); if(!$refund||!in_array($refund->status,['approved_for_admin_refund','processing'],true))return back()->with('err','This refund is not awaiting Super Admin action.'); $order=DB::table('orders')->where('id',$refund->order_id)->first(); if(!$order)return back()->with('err','The order for this refund no longer exists.'); $scan=$this->scanner->scanRefund($request->file('refund_receipt'),$refund); if(!$scan['ok']||!hash_equals((string)($scan['reference_number']??''),trim($data['reference_number'])))return back()->with('err',implode(' ',$scan['errors']??['Receipt scan validation failed.'])); $path=$this->uploadFile($request->file('refund_receipt'),'uploads/refund_receipts'); if(!$path)return back()->with('err','Receipt upload failed. Please try again with a valid image.'); try{$refunds->completeAdminRefund($order,$refund,$path,$scan,trim((string)($data['admin_note']??'')),(string)(session('user')['id']??''),hash_file('sha256',$request->file('refund_receipt')->getRealPath()));}catch(\DomainException $e){return back()->with('err',$e->getMessage());} return back()->with('msg',"Refund #{$refundId} marked as completed."); }
    private function scanReturnReceipt($file, object $case): array { $scan=$this->scanner->scanRefund($file,(object)['id'=>0,'refund_amount'=>$case->requested_amount,'refund_gcash_name'=>$case->refund_gcash_name,'refund_gcash_number'=>$case->refund_gcash_number]); $reference=$scan['reference_number']??null; if($reference&&DB::table('post_delivery_return_cases')->where('id','<>',$case->id)->where('refund_reference',$reference)->exists())$scan['errors'][]='This receipt reference is already linked to another return refund.'; $scan['ok']=empty($scan['errors']); return $scan; }
}
