<?php
namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\PostDeliveryReturnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PostDeliveryReturnController extends Controller
{
    public function __construct(private PostDeliveryReturnService $returns) {}
    private function shop(): ?object { return DB::table('shops')->where('seller_id', session('user')['id'])->first(); }
    public function index() { $shop=$this->shop(); if(!$shop) abort(404); $cases=DB::table('post_delivery_return_cases as c')->join('orders as o','o.id','=','c.order_id')->leftJoin('users as u','u.id','=','c.customer_user_id')->where('c.shop_id',$shop->id)->select('c.*','o.total_price','o.status as order_status','u.fullname as customer_name')->orderByDesc('c.created_at')->paginate(30); return view('seller.return_cases',compact('cases')); }
    public function respond(Request $request, int $caseId) { $data=$request->validate(['response'=>['required','string','min:10','max:1500']]); try{$this->returns->sellerRespond($caseId,(string)session('user')['id'],trim($data['response']));}catch(\DomainException $e){return back()->with('err',$e->getMessage());} return back()->with('msg','Your response was sent to the Super Admin review queue.'); }
}
