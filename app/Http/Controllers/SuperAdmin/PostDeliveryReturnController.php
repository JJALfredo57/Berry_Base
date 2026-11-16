<?php
namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\PayoutReceiptScanService;
use App\Services\PostDeliveryReturnService;
use App\Traits\UploadsFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PostDeliveryReturnController extends Controller
{
    use UploadsFiles;
    public function __construct(private PostDeliveryReturnService $returns, private PayoutReceiptScanService $scanner) {}
    public function index() { $cases=DB::table('post_delivery_return_cases as c')->join('orders as o','o.id','=','c.order_id')->leftJoin('shops as s','s.id','=','c.shop_id')->leftJoin('users as u','u.id','=','c.customer_user_id')->select('c.*','o.total_price','o.status as order_status','s.shop_name','u.fullname as customer_name')->orderByDesc('c.created_at')->paginate(50); $evidence=DB::table('post_delivery_return_evidence')->whereIn('return_case_id',$cases->pluck('id'))->get()->groupBy('return_case_id'); return view('superadmin.return_cases',compact('cases','evidence')); }
    public function decide(Request $request,int $caseId) { $data=$request->validate(['decision'=>['required','in:approved,rejected'],'approved_amount'=>['nullable','numeric','min:0'],'liability'=>['nullable','in:seller,rider,platform'],'decision_note'=>['required','string','min:10','max:1500']]); try{$this->returns->decide($caseId,(string)session('user')['id'],$data['decision'],(float)($data['approved_amount']??0),trim($data['decision_note']),$data['liability']??null);}catch(\DomainException $e){return back()->with('err',$e->getMessage());} return back()->with('msg','Return case decision saved.'); }
    public function refund(Request $request,int $caseId) { $data=$request->validate(['receipt'=>['required','image','mimes:jpg,jpeg,png,webp','max:5120'],'reference_number'=>['required','string','min:6','max:120']]); $case=DB::table('post_delivery_return_cases')->where('id',$caseId)->first(); if(!$case) abort(404); $scan=$this->scanner->scanRefund($request->file('receipt'),(object)['id'=>0,'refund_amount'=>$case->approved_amount,'refund_gcash_name'=>$case->refund_gcash_name,'refund_gcash_number'=>$case->refund_gcash_number]); if(!$scan['ok']||!hash_equals((string)$scan['reference_number'],trim($data['reference_number']))) return back()->with('err',implode(' ',$scan['errors']??['Receipt verification failed.'])); $path=$this->uploadFile($request->file('receipt'),'uploads/post_delivery_refunds'); if(!$path)return back()->with('err','Receipt upload failed.'); try{$this->returns->completeRefund($caseId,(string)session('user')['id'],trim($data['reference_number']),$path,hash_file('sha256',$request->file('receipt')->getRealPath()));}catch(\DomainException $e){return back()->with('err',$e->getMessage());} return back()->with('msg','Refund receipt verified and refund marked completed.'); }
}
