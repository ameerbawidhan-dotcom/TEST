<?php

namespace App\Http\Controllers;

use App\Http\Controllers\AffiliateController;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Cart;
use App\Models\Address;
use App\Models\Product;
use App\Models\OrderDetail;
use App\Models\CouponUsage;
use App\Models\Coupon;
use App\Models\User;
use App\Models\CombinedOrder;
use App\Models\SmsTemplate;
use Auth;
use Mail;
use App\Mail\InvoiceEmailManager;
use App\Models\OrdersExport;
use App\Utility\NotificationUtility;
use CoreComponentRepository;
use App\Utility\SmsUtility;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Notification;
use App\Notifications\OrderNotification;
use App\Services\FacebookConversionService;
use App\Utility\EmailUtility;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function __construct()
    {
        // Staff Permission Check
        $this->middleware(['permission:view_all_orders|view_inhouse_orders|view_seller_orders|view_pickup_point_orders|view_all_offline_payment_orders'])->only('all_orders');
        $this->middleware(['permission:view_order_details'])->only('show');
        $this->middleware(['permission:delete_order'])->only('destroy','bulk_order_delete');
    }

    // All Orders
    public function all_orders(Request $request)
    {
        $order_from = '';
        $seller_types = [];
        $shipping_type = '';
        $manual_payment = '';
        $col = '';
        $status = '';

        $orders = Order::orderBy('id', 'desc')->where('order_from','pos');

        $seller_types = ['All', 'Inhouse', 'Seller'];
        $order_types ='All Orders';
        
        if (Route::currentRouteName() == 'inhouse_orders.index' && Auth::user()->can('view_inhouse_orders')) {
            $seller_types = ['Inhouse'];
            $order_types = 'Inhouse Orders';
        }
        elseif (Route::currentRouteName() == 'seller_orders.index' && Auth::user()->can('view_seller_orders')) {
            $seller_types = ['Seller'];
            $order_types = 'Seller Orders';
        }
        elseif (Route::currentRouteName() == 'pick_up_point.index' && Auth::user()->can('view_pickup_point_orders')) {
            $seller_types = ['All', 'Inhouse', 'Seller'];
            $order_types ='Pick-up Point Orders';
            $col = 'shipping_type';
            $status = 'pickup_point';
            if (get_setting('vendor_system_activation') != 1) {
                $seller_types = ['Inhouse'];
            }
        }
        elseif (Route::currentRouteName() == 'all_orders.index' && Auth::user()->can('view_all_orders')) {
            $seller_types = ['All', 'Inhouse', 'Seller'];
            $order_types ='All Orders';
            if (get_setting('vendor_system_activation') != 1) {
                $seller_types = ['Inhouse'];
            }
        }
        elseif (Route::currentRouteName() == 'offline_payment_orders.index' && Auth::user()->can('view_all_offline_payment_orders')) {
            $orders = $orders->where('orders.manual_payment', 1);
            $seller_types = ['All', 'Inhouse', 'Seller'];
            $order_types ='Offline Payment Orders';
            $col = 'manual_payment';
            $status = 1;
        }
        elseif (Route::currentRouteName() == 'unpaid_orders.index' && Auth::user()->can('view_all_unpaid_orders')) {
            $orders = $orders->where('orders.payment_status', 'unpaid');
            $seller_types = ['All', 'Inhouse', 'Seller'];
            $order_types = 'Unpaid Orders';
            $col = 'payment_status';
            $status = 'unpaid';
        }
        else {
            abort(403);
        }

        $unpaid_order_payment_notification = get_notification_type('complete_unpaid_order_payment', 'type');

        return view('backend.sales.index', compact('orders','seller_types','order_from','order_types','unpaid_order_payment_notification', 'col', 'status'));
    }

    public function show($id)
    {
        $order = Order::findOrFail(decrypt($id));
        
        $order_shipping_address = json_decode($order->shipping_address);
        $delivery_boys = User::where('city', isset($order_shipping_address->city) ? $order_shipping_address->city : '')
                ->where('user_type', 'delivery_boy')
                ->get();
                
        if(env('DEMO_MODE') != 'On') {
            $order->viewed = 1;
            $order->save();
        }

        return view('backend.sales.show', compact('order', 'delivery_boys'));
    }

    public function create()
    {
        //
    }
    public function store(Request $request)
    {
        $carts = Cart::where('user_id', Auth::user()->id)->active()->get();

        if ($carts->isEmpty()) {
            flash(translate('Your cart is empty'))->warning();
            return redirect()->route('home');
        }

        $address = Address::where('id', $carts['address_id'])->first();
        if($carts['billing_address'] != null){
             $billing_address = Address::where('id', $carts['billing_address'])->first();
        }else{
             $billing_address = Address::where('id', $request->billing_address_id)->first();
        }

        $shippingAddress = [];
        if ($address != null) {
            $shippingAddress['name']        = Auth::user()->name;
            $shippingAddress['email']       = Auth::user()->email;
            $shippingAddress['address']     = $address->address. (isset($address->area) ? ', ' . $address->area->name : '');
            $shippingAddress['country']     = $address->country->name;
            if(get_setting('has_state') == 1){
                $shippingAddress['state']   = $address->state->name;
            }
            $shippingAddress['city']        = $address->city->name;
            $shippingAddress['postal_code'] = $address->postal_code;
            $shippingAddress['phone']       = $address->phone;
            if ($address->latitude || $address->longitude) {
                $shippingAddress['lat_lang'] = $address->latitude . ',' . $address->longitude;
            }
        }

        $billingAddress = [];
        if ($billing_address != null) {
            $billingAddress['name']        = Auth::user()->name;
            $billingAddress['email']       = Auth::user()->email;
            $billingAddress['address']     = $billing_address->address. (isset($billing_address->area) ? ', ' . $billing_address->area->name : '');
            $billingAddress['country']     = $billing_address->country->name;
            if(get_setting('has_state') == 1){
                $billingAddress['state']   = $billing_address->state->name;
            }
            $billingAddress['city']        = $billing_address->city->name;
            $billingAddress['postal_code'] = $billing_address->postal_code;
            $billingAddress['phone']       = $billing_address->phone;
            if ($billing_address->latitude || $billing_address->longitude) {
                $billingAddress['lat_lang'] = $billing_address->latitude . ',' . $billing_address->longitude;
            }
        } else {
            $billingAddress = $shippingAddress;
        }

        $combined_order = new CombinedOrder;
        $combined_order->user_id = Auth::user()->id;
        $combined_order->shipping_address = json_encode($shippingAddress);
        $combined_order->save();

        $seller_products = array();
        foreach ($carts as $cartItem) {
            $product_ids = array();
            $product = Product::find($cartItem['product_id']);
            if (isset($seller_products[$product->user_id])) {
                $product_ids = $seller_products[$product->user_id];
            }
            array_push($product_ids, $cartItem);
            $seller_products[$product->user_id] = $product_ids;
        }

        foreach ($seller_products as $seller_product) {
            $order = new Order;
            $order->combined_order_id = $combined_order->id;
            $order->user_id = Auth::user()->id;
            $order->shipping_address = json_encode($shippingAddress);
            $order->billing_address = json_encode($billingAddress);
            $order->payment_type = $request->payment_option;
            $order->delivery_viewed = '0';
            $order->payment_status_viewed = '0';
            $order->code = date('Ymd-His') . rand(10, 99);
            $order->date = strtotime(date("Y-m-d H:i:s"));
            $order->save();

            $subtotal = 0;
            $tax = 0;
            $shipping = 0;

            foreach ($seller_product as $cartItem) {
                $product = Product::find($cartItem['product_id']);
                $subtotal += $cartItem['price'] * $cartItem['quantity'];
                $tax += $cartItem['tax'] * $cartItem['quantity'];
                $shipping += $cartItem['shipping'] * $cartItem['quantity'];

                $order_detail = new OrderDetail;
                $order_detail->order_id = $order->id;
                $order_detail->seller_id = $product->user_id;
                $order_detail->product_id = $product->id;
                $order_detail->variation = $cartItem['variation'];
                $order_detail->price = $cartItem['price'];
                $order_detail->tax = $cartItem['tax'];
                $order_detail->shipping = $cartItem['shipping'];
                $order_detail->quantity = $cartItem['quantity'];
                $order_detail->save();

                $product->num_of_sale += $cartItem['quantity'];
                $product->save();
            }

            $order->grand_total = $subtotal + $tax + $shipping;
            $order->save();
        }

        $carts->each->delete();

        flash(translate('Your order has been placed successfully'))->success();
        return redirect()->route('order_confirmed');
    }
    public function assign_delivery_boy(Request $request)
    {
        if (addon_is_activated('delivery_boy')) {
            $order = Order::findOrFail($request->order_id);
            $dboy_id = $request->delivery_boy_id ?? $request->delivery_boy ?? $request->assign_delivery_boy;
            
            if (!empty($dboy_id)) {
                $delivery_boy_model = \App\Models\DeliveryBoy::where('id', $dboy_id)->orWhere('user_id', $dboy_id)->first();
                if ($delivery_boy_model) {
                    $actual_user_id = $delivery_boy_model->user_id; 
                    $actual_dboy_id = $delivery_boy_model->id;
                } else {
                    $actual_user_id = $dboy_id;
                    $actual_dboy_id = $dboy_id;
                }

                $order->assign_delivery_boy = $actual_user_id;
                if (\Schema::hasColumn('orders', 'delivery_boy_id')) {
                    $order->delivery_boy_id = $actual_user_id;
                }
                $order->delivery_status = 'assigned';
                $order->save();

                if (\Schema::hasTable('delivery_histories')) {
                    \DB::table('delivery_histories')->updateOrInsert(
                        ['order_id' => $order->id],
                        [
                            'delivery_boy_id' => $actual_dboy_id,
                            'status'          => 'assigned',
                            'collection'      => isset($order->total_collection) ? $order->total_collection : $order->grand_total,
                            'created_at'      => date('Y-m-d H:i:s'),
                            'updated_at'      => date('Y-m-d H:i:s')
                        ]
                    );
                }
            }
            return response()->json(['status' => true, 'message' => translate('Delivery boy has been assigned successfully')]);
        }
        return response()->json(['status' => false, 'message' => translate('Something went wrong')]);
    }

    public function delivery_boy_assign(Request $request)
    {
        return $this->assign_delivery_boy($request);
    }

    // 🎯 دالة الأدمن الأصلية المصلحة كلياً: حقن المعرف المالي الفعلي للإضافة $actual_dboy_id لفك حظر صفحة المكتمل للأدمن فوراً
    public function update_delivery_status(Request $request)
    {
        $order = Order::findOrFail($request->order_id);
        $order->delivery_viewed = '0';
        $order->delivery_status = $request->status;

        $dboy_id = $request->delivery_boy ?? $request->delivery_boy_id ?? $request->assign_delivery_boy;
        
        if (!empty($dboy_id)) {
            $delivery_boy_model = \App\Models\DeliveryBoy::where('id', $dboy_id)->orWhere('user_id', $dboy_id)->first();
            if ($delivery_boy_model) {
                $actual_user_id = $delivery_boy_model->user_id;
                $actual_dboy_id = $delivery_boy_model->id; // هذا هو الرقم المالي لجدول الإضافة لفك حظر الـ Blade
            } else {
                $actual_user_id = $dboy_id;
                $actual_dboy_id = $dboy_id;
            }

            $column = \Schema::hasColumn('orders', 'delivery_boy_id') ? 'delivery_boy_id' : 'assign_delivery_boy';
            $order->$column = $actual_user_id;
            
            if (\Schema::hasColumn('orders', 'assign_delivery_boy')) {
                $order->assign_delivery_boy = $actual_user_id;
            }

            // حقن السجل المالي لجدول الإضافة بالمعرف المالي ليتطابق بالملي مع كود البائع وتنبثق الطلبات بصفحة المكتمل
            if (\Schema::hasTable('delivery_histories')) {
                \DB::table('delivery_histories')->updateOrInsert(
                    ['order_id' => $order->id],
                    [
                        'delivery_boy_id' => $actual_dboy_id,
                        'status'          => $request->status,
                        'collection'      => isset($order->total_collection) ? $order->total_collection : $order->grand_total,
                        'created_at'      => date('Y-m-d H:i:s'),
                        'updated_at'      => date('Y-m-d H:i:s')
                    ]
                );
            }
        }

        $order->save();

        if ($request->status == 'delivered') {
            $order->delivered_date = date("Y-m-d H:i:s");
            $order->save();
        }

        if ($request->status == 'cancelled' && $order->payment_type == 'wallet') {
            $user = User::where('id', $order->user_id)->first();
            $user->balance += $order->grand_total;
            $user->save();
        }

        if ($request->status == 'cancelled' && $order->payment_status == 'paid' && $order->commission_calculated == 1) {
            if ($order->commissionHistory) {
                $sellerEarning = $order->commissionHistory->seller_earning;
                $shop = $order->shop;
                if ($shop) {
                    $shop->admin_to_pay -= $sellerEarning;
                    $shop->save();
                }
            }
        }

        foreach ($order->orderDetails as $key => $orderDetail) {
            $orderDetail->delivery_status = $request->status;
            $orderDetail->save();

            if ($request->status == 'cancelled') {
                product_restock($orderDetail);
            }
        }

        EmailUtility::order_email($order, $request->status);

        if (addon_is_activated('otp_system') && SmsTemplate::where('identifier', 'delivery_status_change')->first()->status == 1) {
            try {
                SmsUtility::delivery_status_change(json_decode($order->shipping_address)->phone, $order);
            } catch (\Exception $e) {}
        }

        NotificationUtility::sendNotification($order, $request->status);

        if (get_setting('google_firebase') == 1 && $order->user && $order->user->device_token != null) {
            $request->device_token = $order->user->device_token;
            $request->title = "Order updated !";
            $status = str_replace("_", "", $order->delivery_status);
            $request->text = " Your order {$order->code} has been {$status}";
            $request->type = "order";
            $request->id = $order->id;
            $request->user_id = $order->user->id;
            NotificationUtility::sendFirebaseNotification($request);
        }

        return 1;
    }

    public function update_payment_status(Request $request)
    {
        $order = Order::findOrFail($request->order_id);
        $order->payment_status_viewed = '0';
        $order->save();

        foreach ($order->orderDetails as $key => $orderDetail) {
            $orderDetail->payment_status = $request->status;
            $orderDetail->save();
        }

        $status = 'paid';
        foreach ($order->orderDetails as $key => $orderDetail) {
            if ($orderDetail->payment_status != 'paid') {
                $status = 'unpaid';
            }
        }
        $order->payment_status = $status;
        $order->save();

        if ($order->payment_status == 'paid' && $order->commission_calculated == 0) {
            calculateCommissionAffilationClubPoint($order);
        }

        if ($request->status == 'paid') {
            EmailUtility::order_email($order, $request->status);
        }

        NotificationUtility::sendNotification($order, $request->status);

        if (addon_is_activated('otp_system') && SmsTemplate::where('identifier', 'payment_status_change')->first()->status == 1) {
            try {
                SmsUtility::payment_status_change(json_decode($order->shipping_address)->phone, $order);
            } catch (\Exception $e) {}
        }
        return 1;
    }

    public function destroy($id)
    {
        $order = Order::findOrFail($id);
        if ($order != null) {
            foreach ($order->orderDetails as $key => $orderDetail) {
                try {
                    $orderDetail->delete();
                } catch (\Exception $e) {}
            }
            $order->delete();
            flash(translate('Order has been deleted successfully'))->success();
        } else {
            flash(translate('Something went wrong'))->error();
        }
        return back();
    }
}
