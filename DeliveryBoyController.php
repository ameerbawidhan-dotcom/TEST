<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Hash;
use Auth;
use App\Models\Country;
use App\Models\State;
use App\Models\City;
use App\Models\DeliveryBoy;
use App\Models\DeliveryHistory;
use App\Models\DeliveryBoyCollection;
use App\Models\User;
use App\Models\DeliveryBoyPayment;
use App\Models\Order;


class DeliveryBoyController extends Controller
{
    public function __construct() {
        // Staff Permission Check
        $this->middleware(['permission:view_all_delivery_boy'])->only('index');
        $this->middleware(['permission:add_delivery_boy'])->only('create');
        $this->middleware(['permission:edit_delivery_boy'])->only('edit');
        $this->middleware(['permission:ban_delivery_boy'])->only('ban');
        $this->middleware(['permission:collect_from_delivery_boy'])->only('order_collection_form');
        $this->middleware(['permission:pay_to_delivery_boy'])->only('delivery_earning_form');
        $this->middleware(['permission:delivery_boy_payment_history'])->only('delivery_boys_payment_histories');
        $this->middleware(['permission:collected_histories_from_delivery_boy'])->only('delivery_boys_collection_histories');
        $this->middleware(['permission:order_cancle_request_by_delivery_boy'])->only('cancel_request_list');
         $this->middleware(['permission:delivery_boy_configuration'])->only('delivery_boy_configure');
    }

    public function index(Request $request)
    {
        $sort_search = null;
        $delivery_boys = DeliveryBoy::orderBy('created_at', 'desc');
        
        if ($request->has('search') && $request->search != null){
            $sort_search = $request->search;
            $user_ids = User::where('user_type', 'delivery_boy')->where(function($query) use ($sort_search){
                $query->where('name', 'like', '%'.$sort_search.'%')
                     ->orWhere('email', 'like', '%'.$sort_search.'%');
            })->pluck('id')->toArray();
            
            $delivery_boys = $delivery_boys->whereIn('user_id', $user_ids);
        }
        
        $delivery_boys = $delivery_boys->paginate(15);
        return view('backend.delivery_boys.index', compact('delivery_boys', 'sort_search'));
    }

    public function create()
    {
        $countries = Country::where('status', 1)->get();
        return view('backend.delivery_boys.create', compact('countries'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'          => 'required',
            'email'         => 'required|unique:users|max:255',
            'phone'         => 'required',
            'country_id'    => 'required',
            'state_id'      => 'required',
            'city_id'       => 'required',
        ]);
        
        $country = Country::where('id', $request->country_id)->first();
        $state = \App\Models\State::where('id', $request->state_id)->first();
        $city = City::where('id', $request->city_id)->first();
        
        $user = new User;
        $user->user_type            = 'delivery_boy';
        $user->name                 = $request->name;
        $user->email                = $request->email;
        $user->phone                = $request->phone;
        $user->country              = $country->name;
        $user->state                = $state->name;
        $user->city                 = $city->name;
        $user->avatar_original      = $request->avatar_original;
        $user->address              = $request->address;
        $user->email_verified_at    = date("Y-m-d H:i:s");
        $user->password             = Hash::make($request->password);
        $user->save();
        
        $delivery_boy = new DeliveryBoy;
        $delivery_boy->user_id = $user->id;
        $delivery_boy->save();
        
        flash(translate('Delivery Boy has been created successfully'))->success();
        return redirect()->route('delivery-boys.index');
    }

 /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }
    
    public function edit($id)
    {
        $countries = Country::where('status', 1)->get();
        $states = \App\Models\State::where('status', 1)->get();
        $cities = City::where('status', 1)->get();
        $delivery_boy = User::findOrFail($id);
        
        return view('backend.delivery_boys.edit', compact('delivery_boy', 'countries', 'states', 'cities'));
    }

    public function update(Request $request, $id)
    {
        $delivery_boy = User::findOrFail($id);
        
        $request->validate([
            'name'       => 'required',
            'email'      => 'required|unique:users,email,'.$delivery_boy->id,
            'phone'      => 'required',
            'country_id' => 'required',
            'state_id'   => 'required',
            'city_id'    => 'required',
        ]);

        $country = Country::where('id', $request->country_id)->first();
        $state = \App\Models\State::where('id', $request->state_id)->first();
        $city = City::where('id', $request->city_id)->first();
        
        $delivery_boy->name             = $request->name;
        $delivery_boy->email            = $request->email;
        $delivery_boy->phone            = $request->phone;
        $delivery_boy->country          = $country->name;
        $delivery_boy->state            = $state->name;
        $delivery_boy->city             = $city->name;
        $delivery_boy->avatar_original  = $request->avatar_original;
        $delivery_boy->address          = $request->address;
        
        if(strlen($request->password) > 0){
            $delivery_boy->password = Hash::make($request->password);
        }
        
        $delivery_boy->save();
        
        flash(translate('Delivery Boy has been updated successfully'))->success();
        return back();
    }

        /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
    
    public function ban($id) {
        $delivery_boy = User::findOrFail($id);
        
        if($delivery_boy->banned == 1) {
            $delivery_boy->banned = 0;
            flash(translate('Delivery Boy UnBanned Successfully'))->success();
        } else {
            $delivery_boy->banned = 1;
            flash(translate('Delivery Boy Banned Successfully'))->success();
        }

        $delivery_boy->save();
        return back();
    }
    
  public function collection_from_delivery_boy(Request $request) {
        $delivery_boy = DeliveryBoy::where('user_id', $request->delivery_boy_id)->first();
        
		if($request->payout_amount > $delivery_boy->total_collection){
            flash(translate('Collection Amount Can Not Be Larger Than Collected Amount'))->error();
            return redirect()->route('delivery-boys.index');
        }
		
        $delivery_boy->total_collection -= $request->payout_amount;
        
        if($delivery_boy->save()){
            $delivery_boy_collection          = new DeliveryBoyCollection;
            $delivery_boy_collection->user_id = $request->delivery_boy_id;
            $delivery_boy_collection->collection_amount = $request->payout_amount;

            $delivery_boy_collection->save();

            flash(translate('Collection From Delivery Boy Successfully'))->success();
        } else {
            flash(translate('Something went wrong'))->error();
        }
        
        return redirect()->route('delivery-boys.index');
    }

    public function delivery_boy_configure()
    {
        return view('backend.delivery_boys.delivery_boy_configure');
    }

    public function delivery_boy_config_update(Request $request)
    {
        if($request->has('types')){
            foreach ($request->types as $key => $type) {
                $business_settings = \App\Models\BusinessSetting::where('type', $type)->first();
                if ($business_settings != null) {
                    $business_settings->value = $request[$type];
                    $business_settings->save();
                } else {
                    $business_settings = new \App\Models\BusinessSetting;
                    $business_settings->type = $type;
                    $business_settings->value = $request[$type];
                    $business_settings->save();
                }
            }
        }

        flash(translate('Delivery boy configuration updated successfully'))->success();
        return back();
    }

  public function paid_to_delivery_boy(Request $request) {
        $delivery_boy = DeliveryBoy::where('user_id', $request->delivery_boy_id)->first();
        
        if($request->paid_amount > $delivery_boy->total_earning){
            flash(translate('Paid Amount Can Not Be Larger Than Payable Amount'))->error();
            return redirect()->route('delivery-boys.index');
        }

        $delivery_boy->total_earning -= $request->paid_amount;
        
        if($delivery_boy->save()){
            $delivery_boy_payment          = new DeliveryBoyPayment;
            $delivery_boy_payment->user_id = $request->delivery_boy_id;
            $delivery_boy_payment->payment = $request->paid_amount;

            $delivery_boy_payment->save();

            flash(translate('Pay To Delivery Boy Successfully'))->success();
        } else {
            flash(translate('Something went wrong'))->error();
        }
        
        return redirect()->route('delivery-boys.index');
    }
    
    public function delivery_boys_payment_histories(Request $request)
    {
        $sort_search = null;
        $payment_histories = DeliveryBoyPayment::orderBy('created_at', 'desc');

        if ($request->has('search') && $request->search != null){
            $sort_search = $request->search;
            
            $user_ids = User::where('user_type', 'delivery_boy')
                ->where(function($query) use ($sort_search){
                    $query->where('name', 'like', '%'.$sort_search.'%')
                          ->orWhere('email', 'like', '%'.$sort_search.'%');
                })->pluck('id')->toArray();

            $payment_histories = $payment_histories->whereIn('delivery_boy_id', function($query) use ($user_ids){
                $query->select('id')->from('delivery_boys')->whereIn('user_id', $user_ids);
            });
        }

        $payment_histories = $payment_histories->paginate(15);
        return view('backend.delivery_boys.delivery_boys_payment_list', compact('payment_histories', 'sort_search'));
    }

    public function delivery_boys_collection_histories(Request $request)
    {
        $sort_search = null;
        $collection_histories = \DB::table('delivery_boy_collections')->orderBy('created_at', 'desc');

        if ($request->has('search') && $request->search != null){
            $sort_search = $request->search;
            
            $user_ids = User::where('user_type', 'delivery_boy')
                ->where(function($query) use ($sort_search){
                    $query->where('name', 'like', '%'.$sort_search.'%')
                          ->orWhere('email', 'like', '%'.$sort_search.'%');
                })->pluck('id')->toArray();

            $delivery_boy_ids = \DB::table('delivery_boys')->whereIn('user_id', $user_ids)->pluck('id')->toArray();
            $collection_histories = $collection_histories->whereIn('delivery_boy_id', $delivery_boy_ids);
        }

        $collection_data = $collection_histories->get();
        foreach($collection_data as $item) {
            $dboy = \App\Models\DeliveryBoy::find($item->delivery_boy_id);
            $item->user = $dboy ? \App\Models\User::find($dboy->user_id) : null;
        }

        $currentPage = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
        $perPage = 15;
        $currentItems = $collection_data->slice(($currentPage * $perPage) - $perPage, $perPage)->all();
        
        $delivery_boy_collections = new \Illuminate\Pagination\LengthAwarePaginator($currentItems, count($collection_data), $perPage);
        $delivery_boy_collections->setPath($request->url());

        return view('backend.delivery_boys.delivery_boys_collection_list', compact('delivery_boy_collections', 'sort_search'));
    }

    public function cancel_request_list(Request $request)
    {
        $schema = \Schema::hasColumn('orders', 'delivery_boy_cancel_request');
        $field = $schema ? 'delivery_boy_cancel_request' : 'cancel_request';
        $orders_data = \DB::table('orders')->where($field, 1)->orderBy('created_at', 'desc')->get();
        
        $currentPage = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
        $perPage = 15;
        $currentItems = $orders_data->slice(($currentPage * $perPage) - $perPage, $perPage)->all();
        
        $cancel_requests = new \Illuminate\Pagination\LengthAwarePaginator($currentItems, count($orders_data), $perPage);
        $cancel_requests->setPath($request->url());

        return view('backend.delivery_boys.cancel_request_list', compact('cancel_requests'));
    }


    
    public function assigned_delivery(Request $request)
    {
        $user_id = Auth::user()->id;
        $delivery_boy = DeliveryBoy::where('user_id', $user_id)->first();
        $delivery_boy_id = $delivery_boy ? $delivery_boy->id : 0;

        $assigned_deliveries = Order::whereIn('delivery_status', ['assigned', 'confirmed', 'pending', 'picked_up', 'on_the_way'])
            ->where(function($query) use ($delivery_boy_id, $user_id) {
                $query->where('delivery_boy_id', $delivery_boy_id)
                      ->orWhere('delivery_boy_id', $user_id)
                      ->orWhere('assign_delivery_boy', $delivery_boy_id)
                      ->orWhere('assign_delivery_boy', $user_id);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('delivery_boys.assigned_delivery', compact('assigned_deliveries'));
    }

    public function assigned_delivery_custom(Request $request)
    {
        return $this->assigned_delivery($request);
    }

    // ?? دالة المكتمل الذهبية والمنقذة: جلب الفواتير القياسية وحقن كافة الحقول المفقودة (delivery_status و collection) لتعرض بداخل أسطر الجدول فوراً دون أي كسر أو خطأ 500
    public function completed_delivery(Request $request)
    {
        $user_id = Auth::user()->id;
        $delivery_boy = DeliveryBoy::where('user_id', $user_id)->first();
        $delivery_boy_id = $delivery_boy ? $delivery_boy->id : 0;

        $orders = Order::whereIn('delivery_status', ['delivered', 'completed'])
            ->where(function($query) use ($delivery_boy_id, $user_id) {
                $query->where('delivery_boy_id', $delivery_boy_id)
                      ->orWhere('delivery_boy_id', $user_id)
                      ->orWhere('assign_delivery_boy', $delivery_boy_id)
                      ->orWhere('assign_delivery_boy', $user_id);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // ?? حقن التوليد الذكي ليرضى الـ Blade القياسي لمتجرك وتنتهي عقدة السطر 34 نهائياً
        $orders->getCollection()->transform(function ($order) {
            $history = new \stdClass();
            $history->id = $order->id;
            $history->order_id = $order->id;
            $history->delivery_boy_id = $order->assign_delivery_boy;
            $history->status = $order->delivery_status;
            
            // حقن الخصائص المطلوبة لملف الـ Blade بالملي لمنع الانهيار
            $history->delivery_status = $order->delivery_status; 
            $history->collection = isset($order->total_collection) ? $order->total_collection : $order->grand_total; 
            $history->created_at = $order->created_at;
            $history->order = $order; 
            return $history;
        });

        $completed_deliveries = $orders;

        return view('delivery_boys.completed_delivery', compact('completed_deliveries'));
    }
    public function pending_delivery(Request $request)
    {
        $user_id = Auth::user()->id;
        $delivery_boy = DeliveryBoy::where('user_id', $user_id)->first();
        $delivery_boy_id = $delivery_boy ? $delivery_boy->id : 0;

        $pending_deliveries = Order::whereNotIn('delivery_status', ['delivered', 'completed', 'cancelled'])
            ->where(function($query) use ($delivery_boy_id, $user_id) {
                $query->where('delivery_boy_id', $delivery_boy_id)
                      ->orWhere('delivery_boy_id', $user_id)
                      ->orWhere('assign_delivery_boy', $delivery_boy_id)
                      ->orWhere('assign_delivery_boy', $user_id);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('delivery_boys.pending_delivery', compact('pending_deliveries'));
    }

    public function cancelled_delivery(Request $request)
    {
        $user_id = Auth::user()->id;
        $delivery_boy = DeliveryBoy::where('user_id', $user_id)->first();
        $delivery_boy_id = $delivery_boy ? $delivery_boy->id : 0;

        $cancelled_deliveries = Order::where('delivery_status', 'cancelled')
            ->where(function($query) use ($delivery_boy_id, $user_id) {
                $query->where('delivery_boy_id', $delivery_boy_id)
                      ->orWhere('delivery_boy_id', $user_id)
                      ->orWhere('assign_delivery_boy', $delivery_boy_id)
                      ->orWhere('assign_delivery_boy', $user_id);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('delivery_boys.cancelled_delivery', compact('cancelled_deliveries'));
    }

    
    /**
     * Show the list of total earning by the delivery boy.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    public function total_earning()
    {
        $total_earnings = DeliveryHistory::where('delivery_boy_id', Auth::user()->id)
                ->where('delivery_status', 'delivered')
                ->paginate(10);
        
        return view('delivery_boys.total_earning_list', compact('total_earnings'));
    }

    public function on_the_way_deliveries(Request $request)
    {
        $user_id = Auth::user()->id;
        $delivery_boy = DeliveryBoy::where('user_id', $user_id)->first();
        $delivery_boy_id = $delivery_boy ? $delivery_boy->id : 0;

        $on_the_way_deliveries = Order::where('delivery_status', 'on_the_way')
            ->where(function($query) use ($delivery_boy_id, $user_id) {
                $query->where('delivery_boy_id', $delivery_boy_id)
                      ->orWhere('delivery_boy_id', $user_id)
                      ->orWhere('assign_delivery_boy', $delivery_boy_id)
                      ->orWhere('assign_delivery_boy', $user_id);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('delivery_boys.on_the_way_delivery', compact('on_the_way_deliveries'));
    }

    public function pickup_delivery(Request $request)
    {
        $user_id = Auth::user()->id;
        $delivery_boy = DeliveryBoy::where('user_id', $user_id)->first();
        $delivery_boy_id = $delivery_boy ? $delivery_boy->id : 0;

        $pickup_deliveries = Order::where('delivery_status', 'picked_up')
            ->where(function($query) use ($delivery_boy_id, $user_id) {
                $query->where('delivery_boy_id', $delivery_boy_id)
                      ->orWhere('delivery_boy_id', $user_id)
                      ->orWhere('assign_delivery_boy', $delivery_boy_id)
                      ->orWhere('assign_delivery_boy', $user_id);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('delivery_boys.pickup_delivery', compact('pickup_deliveries'));
    }

    public function pickup_deliveries(Request $request)
    {
        return $this->pickup_delivery($request);
    }

    public function update_delivery_status(Request $request)
    {
        $order = Order::findOrFail($request->order_id);
        $order->delivery_status = $request->status;
        $order->save();

        $user_id = Auth::user()->id;
        $delivery_boy = DeliveryBoy::where('user_id', $user_id)->first();
        $delivery_boy_id = $delivery_boy ? $delivery_boy->id : 0;

        if (\Schema::hasTable('delivery_histories')) {
            \DB::table('delivery_histories')
                ->where('order_id', $order->id)
                ->where(function($q) use ($delivery_boy_id, $user_id) {
                    $q->where('delivery_boy_id', $delivery_boy_id)
                      ->orWhere('delivery_boy_id', $user_id);
                })
                ->update([
                    'status'     => $request->status,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
        }

        if ($request->ajax()) {
            return response()->json(['status' => true, 'message' => translate('Status updated successfully')]);
        }

        flash(translate('Status updated successfully'))->success();
        return back();
    }

   public function total_collection()
    {
        $today_collections = DeliveryHistory::where('delivery_boy_id', Auth::user()->id)
                ->where('delivery_status', 'delivered')
                ->where('payment_type', 'cash_on_delivery')
                ->paginate(10);
        
        return view('delivery_boys.total_collection_list', compact('today_collections'));
    }

    public function delivery_boys_cancel_request_list(Request $request)
    {
        $user_id = Auth::user()->id;
        $delivery_boy = DeliveryBoy::where('user_id', $user_id)->first();
        $delivery_boy_id = $delivery_boy ? $delivery_boy->id : 0;

        $schema = \Schema::hasColumn('orders', 'delivery_boy_cancel_request');
        $field = $schema ? 'delivery_boy_cancel_request' : 'cancel_request';
        
        $cancel_requests = Order::where($field, 1)
            ->where(function($query) use ($delivery_boy_id, $user_id) {
                $query->where('delivery_boy_id', $delivery_boy_id)
                      ->orWhere('delivery_boy_id', $user_id)
                      ->orWhere('assign_delivery_boy', $delivery_boy_id)
                      ->orWhere('assign_delivery_boy', $user_id);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('delivery_boys.cancel_request_list', compact('cancel_requests', 'delivery_boy'));
    }

    public function order_detail($id)
    {
        try {
            $order_id = decrypt($id);
        } catch (\Exception $e) {
            $order_id = $id;
        }

        $order = Order::find($order_id);
        return view('delivery_boys.order_detail', compact('order'));
    }

    public function profile(Request $request)
    {
        $delivery_boy = DeliveryBoy::where('user_id', Auth::user()->id)->first();
        return view('delivery_boys.profile', compact('delivery_boy'));
    }

   public function cancel_request($order_id) {
        $order = Order::findOrFail($order_id);
        $order->cancel_request = '1';
        $order->cancel_request_at = date("Y-m-d H:i:s");
        $order->save();
        
        return back();
    }
    
 /**
     * For only delivery boy while changing delivery status. 
     * Call from order controller
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function store_delivery_history($order) {
        $delivery_history = new DeliveryHistory;
        
        $delivery_history->order_id         = $order->id;
        $delivery_history->delivery_boy_id  = Auth::user()->id;
        $delivery_history->delivery_status  = $order->delivery_status;
        $delivery_history->payment_type     = $order->payment_type;
        if($order->delivery_status == 'delivered') {
            $delivery_boy = DeliveryBoy::where('user_id', Auth::user()->id)->first();
            
            if(get_setting('delivery_boy_payment_type') == 'commission') {
                $delivery_history->earning      = get_setting('delivery_boy_commission');
                $delivery_boy->total_earning   += get_setting('delivery_boy_commission');
            }
            if($order->payment_type == 'cash_on_delivery') {
                $delivery_history->collection    = $order->grand_total;
                $delivery_boy->total_collection += $order->grand_total;
                
                $order->payment_status           = 'paid';
                if($order->commission_calculated == 0) {
                    calculateCommissionAffilationClubPoint($order);
                    $order->commission_calculated = 1;
                }
                
            }
            
            $delivery_boy->save();
            
        }
        $order->delivery_history_date = date("Y-m-d H:i:s");
        
        $order->save();
        $delivery_history->save();
        
    }

    


}
