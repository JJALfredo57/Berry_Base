<?php
namespace App\Http\Controllers\Seller;
use App\Http\Controllers\Controller;
use App\Services\PostDeliveryReturnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class PostDeliveryReturnController extends Controller {
 public function __construct(private PostDeliveryReturnService $returns) {}
 private function shop():?object{return DB::table('shops')->where('seller_id',session('user')['id'])->first();}
 public function index(){ $shop=$this->shop();if(!$shop)abort(404);$cases=DB::table('post_delivery_return_cases as c')->join('orders as o','o.id','=','c.order_id')->leftJoin('users as u','u.id','=','c.customer_user_id')->where('c.shop_id',$shop->id)->select('c.*','o.total_price','o.status as order_status','o.fulfillment_type','u.fullname as customer_name')->orderByDesc('c.created_at')->paginate(30);return view('seller.return_cases',compact('cases')); }
 public function decide(Request $r,int $id){$data=$r->validate(['decision'=>['required','in:approved,rejected'],'note'=>['nullable','string','max:1500']]);$case=DB::table('post_delivery_return_cases as c')->join('orders as o','o.id','=','c.order_id')->where('c.id',$id)->select('o.fulfillment_type')->first();try{$this->returns->sellerDecide($id,(string)session('user')['id'],$data['decision'],trim((string)($data['note']??'')));}catch(\DomainException $e){return back()->with('err',$e->getMessage());}$approved=$data['decision']==='approved';$message=$approved?(($case->fulfillment_type??'')==='Pickup'?'Pickup return approved. The cake remains at your counter and the refund is ready for processing.':'Return approved. The rider may collect the cake.'):'Return request rejected.';return back()->with('msg',$message);}
 public function receive(int $id){try{$this->returns->sellerReceive($id,(string)session('user')['id']);}catch(\DomainException $e){return back()->with('err',$e->getMessage());}return back()->with('msg','Cake receipt confirmed. Refund is ready for Super Admin processing.');}
}
