// ignore_for_file: library_private_types_in_public_api

import 'dart:async';

import 'package:active_ecommerce_cms_demo_app/l10n/app_localizations.dart';
import 'package:active_ecommerce_cms_demo_app/middlewares/auth_middleware.dart';
import 'package:active_ecommerce_cms_demo_app/providers/checkout_provider.dart';
import 'package:active_ecommerce_cms_demo_app/providers/todays_deal_provider.dart';
import 'package:active_ecommerce_cms_demo_app/screens/auth/login.dart';
import 'package:active_ecommerce_cms_demo_app/screens/filter.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_downloader/flutter_downloader.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:go_router/go_router.dart';
import 'package:one_context/one_context.dart';
import 'package:provider/provider.dart';
import 'package:shared_value/shared_value.dart';
import 'package:store_box/store_box.dart';
import 'app_config.dart';
import 'custom/aiz_route.dart';
import 'helpers/main_helpers.dart';
import 'lang_config.dart';
import 'my_theme.dart';
import 'other_config.dart';
import 'presenter/cart_counter.dart';
import 'presenter/cart_provider.dart';
import 'presenter/currency_presenter.dart';
import 'presenter/home_presenter.dart';
import 'presenter/unRead_notification_counter.dart';
import 'providers/blog_provider.dart';
import 'providers/locale_provider.dart';
import 'screens/auction/auction_bidded_products.dart';
import 'screens/auction/auction_products.dart';
import 'screens/auction/auction_products_details.dart';
import 'screens/auction/auction_purchase_history.dart';
import 'screens/auth/registration.dart';
import 'screens/brand_products.dart';
import 'screens/category_list_n_product/category_list.dart';
import 'screens/category_list_n_product/category_products.dart';
import 'screens/checkout/cart.dart';
import 'screens/classified_ads/classified_ads.dart';
import 'screens/classified_ads/classified_product_details.dart';
import 'screens/classified_ads/my_classified_ads.dart';
import 'screens/coupon/coupons.dart';
import 'screens/flash_deal/flash_deal_list.dart';
import 'screens/flash_deal/flash_deal_products.dart';
import 'screens/followed_sellers.dart';
import 'screens/index.dart';
import 'screens/orders/order_details.dart';
import 'screens/orders/order_list.dart';
import 'screens/package/packages.dart';
import 'screens/product/product_details/product_details.dart';
import 'screens/product/todays_deal_products.dart';
import 'screens/profile.dart';
import 'screens/seller_details.dart';
import 'services/push_notification_service.dart';
import 'single_banner/photo_provider.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Firebase.initializeApp();
  await StoreBox.init();
  await FlutterDownloader.initialize(debug: true, ignoreSsl: true);
  SystemChrome.setPreferredOrientations([
    DeviceOrientation.portraitUp,
    DeviceOrientation.portraitDown,
  ]);
  SystemChrome.setSystemUIOverlayStyle(
    SystemUiOverlayStyle(
      statusBarColor: Colors.transparent,
      systemNavigationBarDividerColor: Colors.transparent,
    ),
  );

  runApp(SharedValue.wrapApp(MyApp()));
}

var routes = GoRouter(
  overridePlatformDefaultLocation: false,
  navigatorKey: OneContext().key,
  initialLocation: "/",
  routes: [
    GoRoute(
      path: "/product/:slug",
      pageBuilder: (BuildContext context, GoRouterState state) => MaterialPage(
        child: ProductDetails(slug: getParameter(state, "slug")),
      ),
    ),
    GoRoute(
      path: '/',
      name: "Home",
      pageBuilder: (BuildContext context, GoRouterState state) =>
          MaterialPage(child: Index()),
      routes: [
        GoRoute(
          path: "customer_products",
          pageBuilder: (BuildContext context, GoRouterState state) =>
              MaterialPage(child: MyClassifiedAds()),
        ),
        GoRoute(
          path: "customer-products",
          pageBuilder: (BuildContext context, GoRouterState state) =>
              MaterialPage(child: ClassifiedAds()),
        ),
        GoRoute(
          path: "customer-product/:slug",
          pageBuilder: (BuildContext context, GoRouterState state) =>
              MaterialPage(
                child: ClassifiedAdsDetails(slug: getParameter(state, "slug")),
              ),
        ),
        GoRoute(
          path: "customer-packages",
          pageBuilder: (BuildContext context, GoRouterState state) =>
              MaterialPage(child: UpdatePackage()),
        ),
        GoRoute(
          path: "auction_product_bids",
          pageBuilder: (BuildContext context, GoRouterState state) =>
              MaterialPage(
                child: AuthMiddleware(AuctionBiddedProducts()).next(),
              ),
        ),
        GoRoute(
          path: "users/login",
          pageBuilder: (BuildContext context, GoRouterState state) =>
              MaterialPage(child: Login()),
        ),
        GoRoute(
          path: "users/registration",
          pageBuilder: (BuildContext context, GoRouterState state) =>
              MaterialPage(child: Registration()),
        ),
        GoRoute(
          path: "dashboard",
          name: "Profile",
          pageBuilder: (BuildContext context, GoRouterState state) =>
              AIZRoute.rightTransition(Profile()),
        ),
        GoRoute(
          path: "auction-products",
          pageBuilder: (BuildContext context, GoRouterState state) =>
              MaterialPage(child: AuctionProducts()),
        ),
        GoRoute(
          path: "auction-product/:slug",
          pageBuilder: (BuildContext context, GoRouterState state) =>
              MaterialPage(
                child: AuctionProductsDetails(
                  slug: getParameter(state, "slug"),
                ),
              ),
        ),
        GoRoute(
          path: "auction/purchase_history",
          pageBuilder: (BuildContext context, GoRouterState state) =>
              MaterialPage(
                child: AuthMiddleware(AuctionPurchaseHistory()).next(),
              ),
        ),
        GoRoute(
          path: "brand/:slug",
          pageBuilder: (BuildContext context, GoRouterState state) =>
              MaterialPage(
                child: (BrandProducts(slug: getParameter(state, "slug"))),
              ),
        ),
        GoRoute(
          path: "brands",
          name: "Brands",
          pageBuilder: (BuildContext context, GoRouterState state) =>
              MaterialPage(child: Filter(selectedFilter: "brands")),
        ),
        GoRoute(
          path: "cart",
          pageBuilder: (BuildContext context, GoRouterState state) =>
              MaterialPage(child: AuthMiddleware(Cart()).next()),
        ),
        GoRoute(
          path: "categories",
          pageBuilder: (BuildContext context, GoRouterState state) =>
              MaterialPage(
                child: (CategoryList(slug: getParameter(state, "slug"))),
              ),
        ),
        GoRoute(
          path: "category/:slug",
          pageBuilder: (BuildContext context, GoRouterState state) =>
              MaterialPage(
                child: (CategoryProducts(slug: getParameter(state, "slug"))),
              ),
        ),
        GoRoute(
          path: "flash-deals",
          pageBuilder: (BuildContext context, GoRouterState state) =>
              MaterialPage(child: (FlashDealList())),
        ),
        GoRoute(
          path: "flash-deal/:slug",
          pageBuilder: (BuildContext context, GoRouterState state) =>
              MaterialPage(
                child: (FlashDealProducts(slug: getParameter(state, "slug"))),
              ),
        ),
        GoRoute(
          path: "followed-seller",
          pageBuilder: (BuildContext context, GoRouterState state) =>
              MaterialPage(child: (FollowedSellers())),
        ),
        GoRoute(
          path: "purchase_history",
          pageBuilder: (BuildContext context, GoRouterState state) =>
              MaterialPage(child: (OrderList())),
        ),
        GoRoute(
          path: "purchase_history/details/:id",
          pageBuilder: (BuildContext context, GoRouterState state) =>
              MaterialPage(
                child: (OrderDetails(id: int.parse(getParameter(state, "id")))),
              ),
        ),
        GoRoute(
          path: "sellers",
          pageBuilder: (BuildContext context, GoRouterState state) =>
              MaterialPage(child: (Filter(selectedFilter: "sellers"))),
        ),
        GoRoute(
          path: "shop/:slug",
          pageBuilder: (BuildContext context, GoRouterState state) =>
              MaterialPage(
                child: (SellerDetails(slug: getParameter(state, "slug"))),
              ),
        ),
        GoRoute(
          path: "todays-deal",
          pageBuilder: (BuildContext context, GoRouterState state) =>
              MaterialPage(child: (TodaysDealProducts())),
        ),
        GoRoute(
          path: "coupons",
          pageBuilder: (BuildContext context, GoRouterState state) =>
              MaterialPage(child: (Coupons())),
        ),
      ],
    ),
  ],
);

class MyApp extends StatefulWidget {
  const MyApp({super.key});

  @override
  _MyAppState createState() => _MyAppState();
}

class _MyAppState extends State<MyApp> {
  @override
  void initState() {
    super.initState();
    Future.microtask(() async {
      await Firebase.initializeApp();
      if (OtherConfig.USE_PUSH_NOTIFICATION) {
        PushNotificationService().initialise();
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => LocaleProvider()),
        ChangeNotifierProvider(create: (_) => CartCounter()),
        ChangeNotifierProvider(create: (_) => CartProvider()),
        ChangeNotifierProvider(create: (_) => UnReadNotificationCounter()),
        ChangeNotifierProvider(create: (_) => CurrencyPresenter()),
        ChangeNotifierProvider(create: (_) => HomePresenter()),
        ChangeNotifierProvider(create: (_) => BlogProvider()),
        ChangeNotifierProvider(create: (_) => PhotoProvider()),
        ChangeNotifierProvider(create: (_) => TodaysDealProvider()),
        ChangeNotifierProvider(create: (_) => CheckoutProvider()),

        // ChangeNotifierProvider(
        //   create: (_) => FlashDealProvider()..fetchFlashDeals(),
        // ),
      ],
      child: Consumer<LocaleProvider>(
        builder: (context, provider, snapshot) {
          return ScreenUtilInit(
            designSize: const Size(392.7, 850.9),
            minTextAdapt: true,
            splitScreenMode: true,
            builder: (context, child) => MaterialApp.router(
              builder: OneContext().builder,
              routerConfig: routes,
              title: AppConfig.app_name,
              debugShowCheckedModeBanner: false,
              theme: ThemeData(
                primaryColor: MyTheme.white,
                scaffoldBackgroundColor: MyTheme.white,
                visualDensity: VisualDensity.adaptivePlatformDensity,
                fontFamily: "Inter",
                textTheme: MyTheme.textTheme2,
                fontFamilyFallback: ['Inter'],
                scrollbarTheme: ScrollbarThemeData(
                  thumbVisibility: WidgetStateProperty.all<bool>(false),
                ),
              ),
              localizationsDelegates: [
                GlobalMaterialLocalizations.delegate,
                GlobalWidgetsLocalizations.delegate,
                GlobalCupertinoLocalizations.delegate,
                AppLocalizations.delegate,
              ],
              locale: provider.locale,
              supportedLocales: LangConfig().supportedLocales(),
              localeResolutionCallback: (deviceLocale, supportedLocales) {
                if (AppLocalizations.delegate.isSupported(deviceLocale!)) {
                  return deviceLocale;
                }
                return const Locale('en');
              },
            ),
          );
        },
      ),
    );
  }
}
