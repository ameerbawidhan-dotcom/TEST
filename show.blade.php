@extends('backend.layouts.app')

@section('content')

    <div class="card">
        <div class="card-header">
            <h1 class="h2 fs-16 mb-0">{{ translate('Order Details') }}</h1>
        </div>
        <div class="card-body">
            <div class="col-12 col-xl-10 ml-auto px-0">
                <div class="row gutters-5 justify-content-end">
                    @php
                        $delivery_status = $order->delivery_status;
                        $payment_status = $order->payment_status;
                        $admin_user_id = get_admin()->id;
                        $first_order = $order->orderDetails->first();
                        $shipping_method = $order->shipping_method ?? null;

                        // ? فحص ذكي ومتقاطع لتحديد معرّف المندوب المختار أياً كان من عينه (الأدمن أو البائع)
                        $assigned_col = \Schema::hasColumn('orders', 'delivery_boy_id') ? 'delivery_boy_id' : 'assign_delivery_boy';
                        $current_dboy_id = $order->$assigned_col;
                        if(empty($current_dboy_id) && \Schema::hasColumn('orders', 'assign_delivery_boy')) {
                            $current_dboy_id = $order->assign_delivery_boy;
                        }
                        
                        // جلب كائن المستخدم الخاص بالمندوب المختار لضمان عرض الاسم للأدمن بسلام
                        $current_delivery_boy = $current_dboy_id ? \App\Models\User::find($current_dboy_id) : null;
                    @endphp

                    <!--Assign Delivery Boy - متاح دائماً للأدمن لمشاهدة وتعديل مناديب البائعين والموقع-->
                    @if (addon_is_activated('delivery_boy'))
                        @if ($shipping_method != 'shiprocket' && $shipping_method != 'steadfast' && $shipping_method != 'pathao' && $shipping_method != 'redx')
                            <div class="col-12 col-md-4 col-xl-4 col-xxl-2 mb-2">
                                <label for="assign_deliver_boy">{{ translate('Assign Deliver Boy') }}</label>
                                @if (($delivery_status == 'pending' || $delivery_status == 'confirmed' || $delivery_status == 'picked_up') && auth()->user()->can('assign_delivery_boy_for_orders'))
                                 
                                                          <select class="form-control aiz-selectpicker" data-live-search="true" data-minimum-results-for-search="Infinity" id="assign_deliver_boy" onchange="assign_delivery_boy_custom(this)">
                                        <option value="">{{ translate('Select Delivery Boy') }}</option>
                                        @foreach ($delivery_boys as $delivery_boy)
                                            <option value="{{ $delivery_boy->id }}"
                                                @if ($current_dboy_id == $delivery_boy->id) selected @endif>
                                                {{ $delivery_boy->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="text" class="form-control" value="{{ $current_delivery_boy ? $current_delivery_boy->name : translate('No Delivery Boy Assigned') }}" disabled>
                                @endif
                            </div>
                        @endif
                    @endif

                    <div class="col-12 col-md-4 col-xl-4 col-xxl-2 mb-2">
                        <label for="update_payment_status">{{ translate('Payment Status') }}</label>
                        @if (auth()->user()->can('update_order_payment_status') && $payment_status == 'unpaid')
                            <select class="form-control aiz-selectpicker" data-minimum-results-for-search="Infinity" id="update_payment_status" onchange="confirm_payment_status()">
                                <option value="unpaid" @if ($payment_status == 'unpaid') selected @endif>
                                    {{ translate('Unpaid') }}
                                </option>
                                <option value="paid" @if ($payment_status == 'paid') selected @endif>
                                    {{ translate('Paid') }}
                                </option>
                            </select>
                        @else
                            <input type="text" class="form-control" value="{{ ucfirst($payment_status) }}" disabled>
                        @endif
                    </div>
                    <div class="col-12 col-md-4 col-xl-4 col-xxl-2 mb-2">
                        <label for="update_delivery_status">{{ translate('Delivery Status') }}</label>
                        @if ($order->shipping_method == 'shiprocket' || $order->shipping_method == 'steadfast' || $order->shipping_method == 'pathao' || $order->shipping_method == 'redx')
                            <input type="text" class="form-control" value="{{ ucfirst(str_replace('_', ' ', $delivery_status)) }}" disabled>
                        @elseif (auth()->user()->can('update_order_delivery_status') && $delivery_status != 'delivered' && $delivery_status != 'cancelled')
                            <select class="form-control aiz-selectpicker" data-minimum-results-for-search="Infinity" id="update_delivery_status">
                                <option value="pending" @if ($delivery_status == 'pending') selected @endif>{{ translate('Pending') }}</option>
                                <option value="confirmed" @if ($delivery_status == 'confirmed') selected @endif>{{ translate('Confirmed') }}</option>
                                <option value="picked_up" @if ($delivery_status == 'picked_up') selected @endif>{{ translate('Picked Up') }}</option>
                                <option value="on_the_way" @if ($delivery_status == 'on_the_way') selected @endif>{{ translate('On The Way') }}</option>
                                <option value="delivered" @if ($delivery_status == 'delivered') selected @endif>{{ translate('Delivered') }}</option>
                                <option value="cancelled" @if ($delivery_status == 'cancelled') selected @endif>{{ translate('Cancel') }}</option>
                            </select>
                        @else
                            <input type="text" class="form-control" value="{{ $delivery_status }}" disabled>
                        @endif
                    </div>

                    @if (addon_is_activated('shiprocket') || addon_is_activated('steadfast') || addon_is_activated('pathao') || addon_is_activated('redx'))
                        @php
                            $addons = [];
                            $availableAddons = ['shiprocket', 'steadfast', 'pathao', 'redx'];
                            foreach ($availableAddons as $addon) {
                                if (addon_is_activated($addon)) { $addons[] = $addon; }
                            }
                            $shipping_systems = App\Models\ShippingSystem::where('active', 1)->whereIn('name', $addons)->get();
                        @endphp
                        <div class="col-12 col-md-4 col-xl-4 col-xxl-2 mb-2">
                            <label for="select_shipping_info">{{ translate('Shipping System') }}</label>
                            @if ($order->delivery_status == 'pending' || $order->delivery_status == 'confirmed')
                                @if ($shipping_method)
                                    <input type="text" class="form-control" value="{{ ucfirst(translate($shipping_method)) }}" disabled>
                                @else
                                    <input type="text" class="form-control" value="{{ translate('N/A') }}" disabled>
                                @endif
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <!-- القالب الكامل الأصلي والمنسق لمعلومات العميل، تفاصيل البائع كاملاً وطريقة الدفع وحالة الطلب -->
            <div class="row mt-4 fs-13 gry-color">
                <div class="col-md-4 text-left">
                    <h5 class="strong mb-2" style="border-bottom: 2px solid #f2f3f8; padding-bottom: 5px;">{{ translate('Shipping Information') }}</h5>
                    @if(json_decode($order->shipping_address))
                        @php $shipping_address_data = json_decode($order->shipping_address); @endphp
                        <address class="fs-13">
                            <strong class="text-main">     {{ translate('Customer name') }} : </strong>{{ $shipping_address_data->name }}<br>
                            <strong class="text-main">     {{ translate('Shipping recipient') }} : </strong>{{ $shipping_address_data->name }}<br>


                            <strong class="text-main">  {{ translate('Email') }} : </strong>{{ $shipping_address_data->email }}<br>
                          <strong class="text-main">   {{ translate('Phone') }} :  </strong>{{ $shipping_address_data->phone }}<br>
                        <strong class="text-main">     {{ translate('Address') }} :  </strong>{{ $shipping_address_data->address ?? '' }}, {{ $shipping_address_data->city ?? '' }}, {{ $shipping_address_data->country ?? '' }}
                        </address>
                    @endif
               
                </div>

                <!-- تفاصيل البائع (Sold By) بشعاره وعنوانه بدقة تامة -->
                <div class="col-md-4 text-left">
                    <h5 class="strong mb-2" style="border-bottom: 2px solid #f2f3f8; padding-bottom: 5px;">{{ translate('Purchased from the seller') }}</h5>
                    <div class="d-flex align-items-center mb-2">
                        @if ($order->shop && $order->shop->logo != null)
                            <img src="{{ uploaded_asset($order->shop->logo) }}" class="size-80px img-fit mr-2" height="80" width="80" style="border-radius: 4px; border: 1px solid #ddd; object-fit: cover;">
                        @else
                            <img src="{{ static_asset('assets/img/logo.png') }}" class="size-80px img-fit mr-2" height="80" width="80" style="border-radius: 4px; border: 1px solid #ddd; object-fit: cover;">
                        @endif
                        <div>
                            <strong class="text-dark d-block">{{ $order->shop->name ?? get_setting('site_name') }}</strong>
                            <small class="text-muted d-block">{{ get_seller_address($order) }}</small>
                        </div>
                    </div>
                    @php $gstin = get_seller_gstin($order); @endphp
                    @if($gstin)
                        <div class="mt-1"><strong class="text-main">{{ translate('GSTIN') }}:</strong> {{ $gstin }}</div>
                    @endif
                </div>

                <!-- ملخص وحالة الطلب التراكمية مع الباركود -->
                <div class="col-md-4 text-right d-flex flex-column align-items-end">
                    <h5 class="strong mb-2" style="border-bottom: 2px solid #f2f3f8; padding-bottom: 5px; width: 100%;">{{ translate('Order Summary') }}</h5>
                    <table class="table table-borderless ml-auto fs-13 mb-2" style="width: 100%;">
	<tbody>

                        <tr>
                            <td class="text-main text-bold p-1 text-left  "   >{{ translate('Order Code') }}:</td>
                            <td class="text-info text-bold text-right p-1">{{ $order->code }}</td>
  <!-- توليد الباركود المعتمد في السكربت -->
<td rowspan="3" style="width: 70px;" >   @php $removedXML = '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
                        {!! str_replace($removedXML, "", QrCode::size(70)->generate($order->code)) !!}
</td>
                        </tr>
                        <tr>
                            <td class="text-main text-bold p-1 text-left">{{ translate('Order Status') }}:</td>
                            <td class="text-right p-1">
                                <span class="badge badge-inline @if($delivery_status == 'delivered') badge-success @else badge-info @endif" style="font-weight: 600; text-transform: uppercase;">
                                    {{ translate(ucfirst(str_replace('_', ' ', $delivery_status))) }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-main text-bold p-1 text-left">{{ translate('Order Date') }}:</td>
                            <td class="text-right p-1">{{ date('d-m-Y h:i A', $order->date) }}</td>


 </tr>
<tr>
 <td class="text-main text-bold p-1 text-left" >{{ translate('Payment Method') }}: </td>
 <td class="text-right p-1">{{ translate(ucfirst(str_replace('_', ' ', $order->payment_type))) }}</td>
    
                        </tr>
	</tbody>

     
 
                    </table>
       
                </div>
            </div>

            <!-- ? إجبار خانة معلومات إضافية / ملاحظات الطلب (Additional Info) على الظهور بشكل دائم وحقن كافة الاحتمالات لمنع الاختفاء -->
            <div class="row mt-3">
                <div class="col-12 text-left bg-light p-3" style="border-radius: 4px; border-left: 4px solid #28a745; background-color: #f8f9fa !important;">
                    <strong class="text-main d-block mb-1" style="color: #28a745;">{{ translate('Additional Info / Order Notes') }}:</strong>
                    <span class="text-dark">
                        @if(!empty($order->additional_info))
                            {{ $order->additional_info }}
                        @elseif(!empty($order->comment))
                            {{ $order->comment }}
                        @elseif(!empty($order->notes))
                            {{ $order->notes }}
                        @else
                            <span class="text-muted italic">{{ translate('No notes or additional information provided for this order.') }}</span>
                        @endif
                    </span>
                </div>
            </div>

            <!-- جدول عرض المنتجات وصورها المعتمدة بالكامل في السكربت -->
            <div class="table-responsive mt-4">
                <table class="table table-bordered aiz-table fs-13">
                    <thead>
                        <tr class="bg-trans-dark gry-color" style="background-color: #f2f3f8">
                            <th width="10%">{{ translate('Image') }}</th>
                            <th width="40%">{{ translate('Product Name') }}</th>
                            <th width="15%">{{ translate('Quantity') }}</th>
                            <th width="15%">{{ translate('Price') }}</th>
                            <th class="text-right" width="20%">{{ translate('Total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order->orderDetails as $key => $orderDetail)
                            @if ($orderDetail->product != null)
                                <tr>
                                    <td>
                                        <img src="{{ uploaded_asset($orderDetail->product->thumbnail_img) }}" class="size-50px img-fit" height="50" width="50" style="border: 1px solid #e2e2e2; border-radius: 4px; object-fit: cover;">
                                    </td>
                                    <td>
                                        <span class="text-dark font-weight-bold d-block">{{ $orderDetail->product->name }}</span>
                                        @if($orderDetail->variation != null) 
                                            <small class="text-muted d-block">({{ $orderDetail->variation }})</small> 
                                        @endif
                                        <small class="text-muted d-block">
                                            @php $product_stock = json_decode($orderDetail->product->stocks->first(), true); @endphp
                                            {{ translate('SKU') }}: {{ $product_stock['sku'] ?? 'N/A' }}
                                        </small>
                                    </td>
                                    <td class="font-weight-medium">{{ $orderDetail->quantity }}</td>
                                    <td>{{ single_price($orderDetail->price / $orderDetail->quantity) }}</td>
                                    <td class="text-right font-weight-bold">{{ single_price($orderDetail->price) }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- جدول الفواتير والحسابات والضرائب التفصيلية مع تنظيف الكلمة الخاطئة نهائياً -->
            <div class="row mt-3 justify-content-end">
                <div class="col-md-4">
                    <table class="table table-bordered text-right fs-13">
                        <tbody>
                            @if(is_numeric($first_order->gst_amount))
                                <tr>
                                    <th>{{ translate('Sub Total') }}</th>
                                    <td>{{ single_price($order->orderDetails->sum('price') + $order->orderDetails->sum('shipping_cost') - $order->orderDetails->sum('coupon_discount')) }}</td>
                                </tr>
                                <tr>
                                    <th>{{ translate('Tax / GST Amount') }}</th>
                                    <td>{{ single_price($order->orderDetails->sum('gst_amount')) }}</td>
                                </tr>
                            @else
                                <tr>
                                    <th>{{ translate('Sub Total') }}</th>
                                    <td>{{ single_price($order->orderDetails->sum('price')) }}</td>
                                </tr>
                                <tr>
                                    <th>{{ translate('Shipping Cost') }}</th>
                                    <td>{{ single_price($order->orderDetails->sum('shipping_cost')) }}</td>
                                </tr>
                                <tr>
                                    <th>{{ translate('Product Tax') }}</th>
                                    <td>{{ single_price($order->orderDetails->sum('tax')) }}</td>
                                </tr>
                                <tr>
                                    <th>{{ translate('Coupon Discount') }}</th>
                                    <td>{{ single_price($order->coupon_discount) }}</td>
                                </tr>
                            @endif
                            <tr style="background-color: #f8f9fa;">
                                <th><strong>{{ translate('Grand Total') }}</strong></th>
                                <td><strong>{{ single_price($order->grand_total) }}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

@endsection

@section('script')
    <script type="text/javascript">
        $(document).ready(function() {
            // مسارات أجاكس صريحة ومباشرة لحفظ المندوب فوراً للأدمن وتخطي الانهيارات
            $('#assign_deliver_boy').on('change', function() {
                var order_id = {{ $order->id }};
                var delivery_boy_id = $('#assign_deliver_boy').val();
                $.post('/admin/delivery-boy/assign-delivery-boy', {
                    _token: '{{ @csrf_token() }}',
                    order_id: order_id,
                    delivery_boy_id: delivery_boy_id
                }, function(data) {
                    AIZ.plugins.notify('success', '{{ translate('Delivery boy has been assigned') }}');
                }).fail(function() {
                    $.post('/admin/orders/assign_delivery_boy', {
                        _token: '{{ @csrf_token() }}',
                        order_id: order_id,
                        delivery_boy_id: delivery_boy_id
                    }, function(data) {
                        AIZ.plugins.notify('success', '{{ translate('Delivery boy has been assigned') }}');
                    });
                });
            });

            $('#update_delivery_status').on('change', function() {
                var order_id = {{ $order->id }};
                var status = $('#update_delivery_status').val();
                $.post('/admin/orders/update_delivery_status', {
                    _token: '{{ @csrf_token() }}',
                    order_id: order_id,
                    status: status
                }, function(data) {
                    AIZ.plugins.notify('success', '{{ translate('Delivery status has been updated') }}');
                });
            });
        });
        
        function confirm_payment_status() {
            var order_id = {{ $order->id }};
            var status = $('#update_payment_status').val();
            $.post('/admin/orders/update_payment_status', {
                _token: '{{ @csrf_token() }}',
                order_id: order_id,
                status: status
            }, function(data) {
                AIZ.plugins.notify('success', '{{ translate('Payment status has been updated') }}');
            });
        }
    </script>
@endsection

