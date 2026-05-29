<?php

namespace App\Http\Controllers\Seller;

use App\Models\Order;
use App\Models\ProductStock;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Utility\NotificationUtility;
use App\Utility\SmsUtility;
use Illuminate\Http\Request;
use App\Models\OrdersExport;
use App\Utility\EmailUtility;
use Maatwebsite\Excel\Facades\Excel;
use Auth;
use DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource to seller.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $order_from = '';
        $order_types = translate('All Orders');
        return view('seller.orders.index', compact('order_from', 'order_types'));
    }

    public function show($id)
    {
        $order = Order::findOrFail(decrypt($id));
        $order_shipping_address = json_decode($order->shipping_address);
        
        $delivery_boys = User::where('city', isset($order_shipping_address->city) ? $order_shipping_address->city : '')
            ->where('user_type', 'delivery_boy')
            ->get();

        $order->viewed = 1;
        $order->save();
        return view('seller.orders.show', compact('order', 'delivery_boys'));
    }

    // الدالة المخصصة لتمكين البائع من اختيار وتعيين المندوب بنجاح
    public function assign_delivery_boy(Request $request)
    {
        if (addon_is_activated('delivery_boy')) {
            $order = Order::findOrFail($request->order_id);
            
            $column = \Schema::hasColumn('orders', 'delivery_boy_id') ? 'delivery_boy_id' : 'assign_delivery_boy';
            $order->$column = $request->delivery_boy_id;
            
            if(\Schema::hasColumn('orders', 'assign_delivery_boy')) {
                $order->assign_delivery_boy = $request->delivery_boy_id;
            }
            
            $order->save();

            flash(translate('Delivery boy has been assigned successfully'))->success();
            return back();
        }
        
        flash(translate('Delivery boy addon is not activated'))->error();
        return back();
    }

    // Update Delivery Status
    public function update_delivery_status(Request $request)
    {   
        $authUser = Auth::user();
        $order = Order::findOrFail($request->order_id);
        $order->delivery_viewed = '0';
        $order->delivery_status = $request->status;
        $order->save();

        if($request->status == 'delivered'){
            $order->delivered_date = date("Y-m-d H:i:s");
            $order->save();
        }

        if ($request->status == 'cancelled' && $order->payment_type == 'wallet') {
            $user = User::where('id', $order->user_id)->first();
            $user->balance += $order->grand_total;
            $user->save();
        }

        if($request->status == 'cancelled' && $order->payment_status == 'paid' && $order->commission_calculated == 1){
            $sellerEarning = $order->commissionHistory->seller_earning;
            $shop = $order->shop;
            $shop->admin_to_pay -= $sellerEarning;
            $shop->save();
        }

        foreach ($order->orderDetails->where('seller_id', $authUser->id) as $key => $orderDetail) {
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

        if (get_setting('google_firebase') == 1 && $order->user->device_token != null) {
            $request->device_token = $order->user->device_token;
            $request->title = "Order updated !";
            $status = str_replace("_", "", $order->delivery_status);
            $request->text = " Your order {$order->code} has been {$status}";

            $request->type = "order";
            $request->id = $order->id;
            $request->user_id = $order->user->id;

            NotificationUtility::sendFirebaseNotification($request);
        }

        if (addon_is_activated('delivery_boy')) {
            if ($authUser->user_type == 'delivery_boy') {
                $deliveryBoyController = new \App\Http\Controllers\DeliveryBoyController;
                $deliveryBoyController->store_delivery_history($order);
            }
        }

        return 1;
    }

    // Update Payment Status
    public function update_payment_status(Request $request)
    {
        $order = Order::findOrFail($request->order_id);
        $order->payment_status_viewed = '0';
        $order->save();

        foreach ($order->orderDetails->where('seller_id', Auth::user()->id) as $key => $orderDetail) {
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

        if($request->status == 'paid'){
            EmailUtility::order_email($order, $request->status);  
        }

        NotificationUtility::sendNotification($order, $request->status);
        if (get_setting('google_firebase') == 1 && $order->user->device_token != null) {
            $request->device_token = $order->user->device_token;
            $request->title = "Order updated !";
            $status = str_replace("_", "", $order->payment_status);
            $request->text = " Your order {$order->code} has been {$status}";

            $request->type = "order";
            $request->id = $order->id;
            $request->user_id = $order->user->id;

            NotificationUtility::sendFirebaseNotification($request);
        }

        if (addon_is_activated('otp_system') && SmsTemplate::where('identifier', 'payment_status_change')->first()->status == 1) {
            try {
                SmsUtility::payment_status_change(json_decode($order->shipping_address)->phone, $order);
            } catch (\Exception $e) {}
        }
        return 1;
    }

    public function orderBulkExport(Request $request)
    {
        if($request->id){
          return Excel::download(new OrdersExport($request->id), 'orders.xlsx');
        }
        return back();
    }

    // ?? استدعاء ملف جدول البائع الأصلي والصحيح التابع للـ Blade (orders_table) وإرساله كـ HTML صريح للأجاكس
    public function get_filter_orders(Request $request)
    {
        $date = $request->date;
        $sort_search = $request->search;
        $order_from = $request->order_from ?? '';
        
        $order_types = translate('All Orders');

        // جلب معرفات الطلبات الخاصة بمنتجات البائع لمنع تعارض الـ Global Scopes
        $order_ids = DB::table('order_details')
                        ->where('seller_id', Auth::user()->id)
                        ->pluck('order_id')
                        ->unique()
                        ->toArray();

        $orders = Order::whereIn('id', $order_ids)->orderBy('id', 'desc');
        
        if ($order_from == 'pos') {
            $orders = $orders->where('order_from', 'pos');
            $order_types = translate('POS Orders');
        }
        
        if ($sort_search != null) {
            $orders = $orders->where(function ($query) use ($sort_search) {
                $query->where('code', 'like', '%' . $sort_search . '%')
                    ->orWhereHas('user', function ($q) use ($sort_search) {
                        $q->where('name', 'like', '%' . $sort_search . '%');
                });
            });
        }

        // فلترة دقيقة وآمنة للتواريخ لمنع أخطاء الـ Array
        if ($date != null) {
            $date_parts = explode(" to ", $date);
            if (count($date_parts) == 2) {
                $orders = $orders->whereDate('created_at', '>=', date('Y-m-d', strtotime($date_parts[0])))
                                 ->whereDate('created_at', '<=', date('Y-m-d', strtotime($date_parts[1])));
            } else {
                $orders = $orders->whereDate('created_at', date('Y-m-d', strtotime($date)));
            }
        }

        if ($request->payment_status != null) {
            $payment_status = $request->payment_status;
            if (strpos($payment_status, ',') !== false) {
                $status_parts = explode(",", $payment_status);
                $orders = $orders->where('payment_status', end($status_parts));
            } else {
                $orders = $orders->where('payment_status', $payment_status);
            }
        }

        $filters = $request->selected_filter ?? [];
        if (!empty($filters)) {
            $orders->whereIn('delivery_status', $filters);
        }

        $orders = $orders->paginate(15);
        $type = $request->seller_type;
        
        // ? الربط الذهبي بملف الجدول الحقيقي التابع لنسخة السكربت الأصلية واستخراجه كـ HTML فرعي
        $view_html = view('seller.orders.orders_table', compact('orders', 'sort_search', 'date', 'type', 'order_from', 'order_types'))->render();
        
        return response()->json(['html' => $view_html]);
    }
}
