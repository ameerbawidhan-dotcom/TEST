<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Hash;
use Auth;
use App\Models\Country;
use App\Models\State;
use App\Models\City;
use App\Models\DeliveryBoy;
use App\Models\User;
use App\Models\DeliveryBoyPayment;
use App\Models\Order;

class DeliveryBoyController extends Controller
{
    public function __construct() {
        $this->middleware(['permission:view_all_delivery_boy'])->only('index');
        $this->middleware(['permission:add_delivery_boy'])->only('create');
        $this->middleware(['permission:edit_delivery_boy'])->only('edit');
        $this->middleware(['permission:ban_delivery_boy'])->only('ban');
        $this->middleware(['permission:collect_from_delivery_boy'])->only('order_collection_form');
        $this->middleware(['permission:delivery_boy_payment_history'])->only('delivery_boys_payment_histories');
        $this->middleware(['permission:collected_histories_from_delivery_boy'])->only('delivery_boys_collection_histories');
        $this->middleware(['permission:order_cancle_request_by_delivery_boy'])->only('cancel_request_list');
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
    
    public function order_collection_form(Request $request) {
        $delivery_boy_info = DeliveryBoy::with('user')
                ->where('user_id', $request->id)
                ->first();
        
        return view('backend.delivery_boys.order_collection_form', compact('delivery_boy_info'));
    }
    
    public function collection_from_delivery_boy(Request $request) {
        $delivery_boy = DeliveryBoy::where('user_id', $request->delivery_boy_id)->first();
        
        if($request->payout_amount > $delivery_boy->total_collection){
            flash(translate('Payout amount cannot be greater than total collection'))->error();
            return back();
        }
        
        $delivery_boy->total_collection -= $request->payout_amount;
        $delivery_boy->save();
        
        flash(translate('Amount collected successfully'))->success();
        return back();
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
    } // ?? هذا هو قوس الإغلاق المفقود والساحر الذي يجب إضافته هنا فوراً لإصلاح الكسر والهيكل

        public function assigned_delivery_custom(Request $request)
    {
        $user_id = Auth::user()->id;
        $delivery_boy = DeliveryBoy::where('user_id', $user_id)->first();
        $delivery_boy_id = $delivery_boy ? $delivery_boy->id : 0;

        // ?? الاستعلام القياسي الصافي الموجه للحقل الحقيقي الوحيد في جدولك 'assign_delivery_boy' لقفل الأزمة نهائياً
        $assigned_deliveries = Order::whereIn('delivery_status', ['assigned', 'confirmed', 'pending', 'picked_up', 'on_the_way'])
            ->where(function($query) use ($delivery_boy_id, $user_id) {
                $query->where('assign_delivery_boy', $delivery_boy_id)
                      ->orWhere('assign_delivery_boy', $user_id);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('delivery_boys.assigned_delivery', compact('assigned_deliveries'));
    }

    // ?? دالة التوصيل المكتمل العبقرية والمقفلة: تحاكي جدول السجلات عبر سحب الطلب الحقيقي مباشرة وتجهيز كائن مطابق للـ Blade
    public function completed_delivery(Request $request)
    {
        $user_id = Auth::user()->id;
        $delivery_boy = DeliveryBoy::where('user_id', $user_id)->first();
        $delivery_boy_id = $delivery_boy ? $delivery_boy->id : 0;

        $column = \Schema::hasColumn('orders', 'delivery_boy_id') ? 'delivery_boy_id' : 'assign_delivery_boy';

        // جلب الطلبات الفعلية المكتملة المسندة لهذا المندوب يقيناً من جدول الأوردرات
        $orders_raw = \DB::table('orders')->where(function($query) use ($column, $delivery_boy_id, $user_id) {
                            $query->where($column, $delivery_boy_id)
                                  ->orWhere($column, $user_id)
                                  ->orWhere('assign_delivery_boy', $user_id)
                                  ->orWhere('assign_delivery_boy', $delivery_boy_id);
                        })
                        ->whereIn('delivery_status', ['delivered', 'completed'])
                        ->orderBy('created_at', 'desc')
                        ->get();

        // تشكيل وتحويل مصفوفة البيانات يدوياً لبناء بنية كائن يحاكي الـ DeliveryHistory تماماً لإرضاء الـ Blade المحدث
        $mocked_histories = [];
        foreach ($orders_raw as $rawOrder) {
            $orderModel = Order::find($rawOrder->id);
            if ($orderModel) {
                $mockItem = new \stdClass();
                $mockItem->id = $rawOrder->id;
                $mockItem->delivery_boy_id = $delivery_boy_id;
                $mockItem->order_id = $rawOrder->id;
                $mockItem->collection = isset($rawOrder->total_collection) ? $rawOrder->total_collection : $rawOrder->grand_total;
                $mockItem->delivery_status = $rawOrder->delivery_status;
                $mockItem->created_at = $rawOrder->created_at;
                $mockItem->order = $orderModel; // حقن موديل الأوردر وعلاقاته بداخل الكائن

                $mocked_histories[] = $mockItem;
            }
        }

        // إتمام عملية التقسيم (Pagination) لتعمل الروابط والصفحات بامتياز
        $currentPage = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
        $perPage = 10;
        $currentItems = array_slice($mocked_histories, ($currentPage * $perPage) - $perPage, $perPage);
        
        $completed_deliveries = new \Illuminate\Pagination\LengthAwarePaginator($currentItems, count($mocked_histories), $perPage);
        $completed_deliveries->setPath($request->url());

        return view('delivery_boys.completed_delivery', compact('completed_deliveries'));
    }

    public function pending_delivery(Request $request)
    {
        $delivery_boy = DeliveryBoy::where('user_id', Auth::user()->id)->first();
        $delivery_boy_id = $delivery_boy ? $delivery_boy->id : 0;

        $column = \Schema::hasColumn('orders', 'delivery_boy_id') ? 'delivery_boy_id' : 'assign_delivery_boy';
        $search_id = ($column == 'delivery_boy_id') ? $delivery_boy_id : Auth::user()->id;

        $pending_deliveries = Order::where($column, $search_id)
                       ->whereNotIn('delivery_status', ['delivered', 'completed', 'cancelled'])
                       ->orderBy('created_at', 'desc')
                       ->paginate(10);

        return view('delivery_boys.pending_delivery', compact('pending_deliveries'));
    }

    public function cancelled_delivery(Request $request)
    {
        $delivery_boy = DeliveryBoy::where('user_id', Auth::user()->id)->first();
        $delivery_boy_id = $delivery_boy ? $delivery_boy->id : 0;

        $column = \Schema::hasColumn('orders', 'delivery_boy_id') ? 'delivery_boy_id' : 'assign_delivery_boy';
        $search_id = ($column == 'delivery_boy_id') ? $delivery_boy_id : Auth::user()->id;

        $cancelled_deliveries = Order::where($column, $search_id)
                       ->where('delivery_status', 'cancelled')
                       ->orderBy('created_at', 'desc')
                       ->paginate(10);

        return view('delivery_boys.cancelled_delivery', compact('cancelled_deliveries'));
    }

    public function total_earning(Request $request)
    {
        $delivery_boy = DeliveryBoy::where('user_id', Auth::user()->id)->first();
        $delivery_boy_id = $delivery_boy ? $delivery_boy->id : 0;

        $column = \Schema::hasColumn('orders', 'delivery_boy_id') ? 'delivery_boy_id' : 'assign_delivery_boy';
        $search_id = ($column == 'delivery_boy_id') ? $delivery_boy_id : Auth::user()->id;

        $orders_data = \DB::table('orders')->where($column, $search_id)
                       ->whereIn('delivery_status', ['delivered', 'completed'])
                       ->orderBy('created_at', 'desc')
                       ->get();

        foreach ($orders_data as $item) {
            $item->order = \DB::table('orders')->where('id', $item->id)->first();
            $item->earning = isset($item->delivery_boy_earning) ? $item->delivery_boy_earning : (isset($item->total_earning) ? $item->total_earning : 0);
        }

        $currentPage = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
        $perPage = 10;
        $currentItems = $orders_data->slice(($currentPage * $perPage) - $perPage, $perPage)->all();
        
        $total_earnings = new \Illuminate\Pagination\LengthAwarePaginator($currentItems, count($orders_data), $perPage);
        $total_earnings->setPath($request->url());

        return view('delivery_boys.total_earning_list', compact('total_earnings', 'delivery_boy'));
    }

    public function delivery_boys_cancel_request_list(Request $request)
    {
        $delivery_boy = DeliveryBoy::where('user_id', Auth::user()->id)->first();
        $delivery_boy_id = $delivery_boy ? $delivery_boy->id : 0;

        $column = \Schema::hasColumn('orders', 'delivery_boy_id') ? 'delivery_boy_id' : 'assign_delivery_boy';
        $search_id = ($column == 'delivery_boy_id') ? $delivery_boy_id : Auth::user()->id;

        $schema = \Schema::hasColumn('orders', 'delivery_boy_cancel_request');
        $field = $schema ? 'delivery_boy_cancel_request' : 'cancel_request';
        
        $orders_data = \DB::table('orders')->where($column, $search_id)
                       ->where($field, 1)
                       ->orderBy('created_at', 'desc')
                       ->get();

        foreach ($orders_data as $item) {
            $item->order = \DB::table('orders')->where('id', $item->id)->first();
            $item->delivery_boy = Auth::user();
            $item->cancel_request_at = isset($item->updated_at) ? $item->updated_at : $item->created_at;
        }

        $currentPage = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
        $perPage = 10;
        $currentItems = $orders_data->slice(($currentPage * $perPage) - $perPage, $perPage)->all();
        
        $cancel_requests = new \Illuminate\Pagination\LengthAwarePaginator($currentItems, count($orders_data), $perPage);
        $cancel_requests->setPath($request->url());

        return view('delivery_boys.cancel_request_list', compact('cancel_requests', 'delivery_boy'));
    }

    public function on_the_way_deliveries(Request $request)
    {
        $delivery_boy = DeliveryBoy::where('user_id', Auth::user()->id)->first();
        $delivery_boy_id = $delivery_boy ? $delivery_boy->id : 0;

        $column = \Schema::hasColumn('orders', 'delivery_boy_id') ? 'delivery_boy_id' : 'assign_delivery_boy';
        $search_id = ($column == 'delivery_boy_id') ? $delivery_boy_id : Auth::user()->id;

        $on_the_way_deliveries = Order::where($column, $search_id)
                       ->where('delivery_status', 'on_the_way')
                       ->orderBy('created_at', 'desc')
                       ->paginate(10);

        return view('delivery_boys.on_the_way_delivery', compact('on_the_way_deliveries'));
    }

    public function pickup_deliveries(Request $request)
    {
        return $this->pickup_delivery($request);
    }

    public function pickup_delivery(Request $request)
    {
        $delivery_boy = DeliveryBoy::where('user_id', Auth::user()->id)->first();
        $delivery_boy_id = $delivery_boy ? $delivery_boy->id : 0;

        $column = \Schema::hasColumn('orders', 'delivery_boy_id') ? 'delivery_boy_id' : 'assign_delivery_boy';
        $search_id = ($column == 'delivery_boy_id') ? $delivery_boy_id : Auth::user()->id;

        $pickup_deliveries = Order::where($column, $search_id)
                       ->where('delivery_status', 'picked_up')
                       ->orderBy('created_at', 'desc')
                       ->paginate(10);

        return view('delivery_boys.pickup_delivery', compact('pickup_deliveries'));
    }

    public function profile(Request $request)
    {
        $delivery_boy = DeliveryBoy::where('user_id', Auth::user()->id)->first();
        return view('delivery_boys.profile', compact('delivery_boy'));
    }

    public function total_collection(Request $request)
    {
        $delivery_boy = DeliveryBoy::where('user_id', Auth::user()->id)->first();
        $delivery_boy_id = $delivery_boy ? $delivery_boy->id : 0;

        $column = \Schema::hasColumn('orders', 'delivery_boy_id') ? 'delivery_boy_id' : 'assign_delivery_boy';
        $search_id = ($column == 'delivery_boy_id') ? $delivery_boy_id : Auth::user()->id;

        $orders_data = \DB::table('orders')->where($column, $search_id)
                       ->whereIn('delivery_status', ['delivered', 'completed'])
                       ->orderBy('created_at', 'desc')
                       ->get();

        foreach ($orders_data as $item) {
            $item->order = \DB::table('orders')->where('id', $item->id)->first();
            $item->collection = isset($item->total_collection) ? $item->total_collection : $item->grand_total;
        }

        $currentPage = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
        $perPage = 10;
        $currentItems = $orders_data->slice(($currentPage * $perPage) - $perPage, $perPage)->all();
        
        $today_collections = new \Illuminate\Pagination\LengthAwarePaginator($currentItems, count($orders_data), $perPage);
        $today_collections->setPath($request->url());

        return view('delivery_boys.total_collection_list', compact('today_collections', 'delivery_boy'));
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

    public function delivery_boy_make_payment(Request $request)
    {
        $order = Order::find($request->order_id);
        if ($order) {
            return view('delivery_boys.inc.payment_modal_content', compact('order'));
        }
        return response()->json(['status' => 'error', 'message' => 'Order not found']);
    }
}

