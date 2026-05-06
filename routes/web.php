<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductSizeController;
use App\Http\Controllers\Admin\OrderController as AdminOrder;
use App\Http\Controllers\Admin\MessageController as AdminMessage;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\AddonController;
use App\Http\Controllers\Admin\KitchenController;
use App\Http\Controllers\Admin\CustomOrderOptionsController;
use App\Http\Controllers\Admin\CustomOrderController as AdminCustomOrder;
use App\Http\Controllers\Admin\DeliveryZoneController;
use App\Http\Controllers\Admin\RiderController as AdminRider;
use App\Http\Controllers\Guest\CatalogController as GuestCatalog;
use App\Http\Controllers\Guest\CheckoutController as GuestCheckout;
use App\Http\Controllers\Guest\CustomOrderController as GuestCustomOrder;
use App\Http\Controllers\Guest\MessageController as GuestMessage;
use App\Http\Controllers\Guest\ReviewController as GuestReview;

// ── Setup ─────────────────────────────────────────────────────────────────
Route::get('/setup',         [SetupController::class, 'show'])->name('setup');
Route::post('/setup',        [SetupController::class, 'store'])->name('setup.store');
Route::post('/setup/verify', [SetupController::class, 'verify'])->name('setup.verify');
Route::get('/setup/back',    [SetupController::class, 'back'])->name('setup.back');

// ── Dev Mode SMS polling (returns + clears session queue) ─────────────────
Route::get('/dev/sms-poll', function () {
    $p = \Illuminate\Support\Facades\DB::table('platform_settings')->first();
    if (empty($p->dev_mode)) return response()->json([]);
    $queue = session('dev_sms_queue', []);
    session(['dev_sms_queue' => []]);
    return response()->json($queue);
})->name('dev.sms.poll');

// ── Root — redirect to catalog ────────────────────────────────────────────
// ── Platform Pages ────────────────────────────────────────────────────────
Route::get('/shops',          [\App\Http\Controllers\PlatformController::class, 'shops'])->name('platform.shops');
Route::get('/shop/{slug?}',   [\App\Http\Controllers\PlatformController::class, 'shopPage'])->name('platform.shop');

Route::get('/', function () {
    // If no admin exists yet, go to setup wizard
    if (!\Illuminate\Support\Facades\DB::table('users')->whereIn('role',['admin','superadmin'])->exists())
        return redirect()->route('setup');
    // Show the welcome/splash page
    return view('welcome');
})->name('platform.home');

// ── Public Catalog (no login needed) ─────────────────────────────────────
Route::get('/catalog',          [GuestCatalog::class, 'index'])->name('catalog');
Route::post('/catalog/select',  [GuestCatalog::class, 'selectProduct'])->name('catalog.select');

// ── Catalog Availability Check (AJAX) ────────────────────────────────────
Route::get('/catalog/availability', [GuestCatalog::class, 'checkAvailability'])->name('catalog.availability');

// ── Geocode: redirect browser directly to Nominatim (no server-side HTTP) ─
Route::get('/api/geocode/reverse', function (\Illuminate\Http\Request $req) {
    $lat = (float) $req->query('lat', 0);
    $lng = (float) $req->query('lng', 0);
    if (!$lat || !$lng) return response()->json(['error' => 'Missing coordinates'], 422);
    $url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1'
         . '&lat=' . $lat . '&lon=' . $lng;
    return redirect()->away($url);
})->name('api.geocode.reverse');

Route::get('/api/geocode/search', function (\Illuminate\Http\Request $req) {
    $q = trim($req->query('q', ''));
    if (!$q) return response()->json([], 200);
    $url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=5&q=' . urlencode($q);
    return redirect()->away($url);
})->name('api.geocode.search');

// ── Guest Checkout ────────────────────────────────────────────────────────
Route::get('/checkout',             [GuestCheckout::class, 'show'])->name('guest.checkout');
Route::post('/checkout/send-otp',   [GuestCheckout::class, 'sendOtp'])->name('guest.checkout.send_otp');
Route::post('/checkout/place',      [GuestCheckout::class, 'placeOrder'])->name('guest.checkout.place');

// ── Guest Custom Order ────────────────────────────────────────────────────
// ── Seller Application (public) ─────────────────────────────────────────────
Route::get('/seller/apply',              [\App\Http\Controllers\Seller\ApplicationController::class, 'show'])->name('seller.apply');
Route::post('/seller/apply/send-otp',   [\App\Http\Controllers\Seller\ApplicationController::class, 'sendOtp'])->name('seller.apply.otp.send');
Route::get('/seller/apply/verify',      [\App\Http\Controllers\Seller\ApplicationController::class, 'showOtp'])->name('seller.apply.otp');
Route::post('/seller/apply/submit',     [\App\Http\Controllers\Seller\ApplicationController::class, 'verifyOtp'])->name('seller.apply.submit');
Route::get('/seller/apply/success',     [\App\Http\Controllers\Seller\ApplicationController::class, 'success'])->name('seller.apply.success');

Route::get('/custom-order',              [GuestCustomOrder::class, 'show'])->name('guest.custom_order');
Route::post('/custom-order/send-otp',    [GuestCustomOrder::class, 'sendOtp'])->name('guest.custom_order.send_otp');
Route::post('/custom-order/place',       [GuestCustomOrder::class, 'store'])->name('guest.custom_order.store');
Route::post('/custom-order/{id}/accept-price', [GuestCustomOrder::class, 'acceptPrice'])->name('guest.custom_order.accept_price');
Route::post('/custom-order/{id}/cancel-price', [GuestCustomOrder::class, 'cancelPrice'])->name('guest.custom_order.cancel_price');

// ── Order Tracking (SMS link) ─────────────────────────────────────────────
Route::get('/track/{trackCode}', [TrackingController::class, 'show'])->name('track.order');

// ── Guest Messaging (on tracking page) ───────────────────────────────────
Route::get('/track/{trackCode}/messages',  [GuestMessage::class, 'poll'])->name('guest.messages.poll');
Route::post('/track/{trackCode}/messages', [GuestMessage::class, 'send'])->name('guest.messages.send');

// ── Guest Review ──────────────────────────────────────────────────────────
Route::post('/track/{trackCode}/review', [GuestReview::class, 'store'])->name('guest.review.store');
Route::post('/track/{trackCode}/cancel-request', [TrackingController::class, 'requestCancel'])->name('guest.cancel_request');
Route::post('/track/{trackCode}/set-deposit', [\App\Http\Controllers\Guest\PaymentController::class, 'setDeposit'])->name('guest.set_deposit');

// ── Guest GCash Payment ───────────────────────────────────────────────────
// ── Guest GCash Payment ───────────────────────────────────────────────────
Route::get('/track/{trackCode}/pay-gcash',       [\App\Http\Controllers\Guest\PaymentController::class, 'payGcash'])->name('guest.pay_gcash');
Route::get('/track/{trackCode}/payment-return',  [\App\Http\Controllers\Guest\PaymentController::class, 'paymentReturn'])->name('guest.payment_return');
Route::get('/track/{trackCode}/pay-deposit',     [\App\Http\Controllers\Guest\PaymentController::class, 'payDeposit'])->name('guest.pay_deposit');
Route::get('/track/{trackCode}/deposit-return',  [\App\Http\Controllers\Guest\PaymentController::class, 'depositReturn'])->name('guest.deposit_return');
Route::get('/track/{trackCode}/pay-remaining',   [\App\Http\Controllers\Guest\PaymentController::class, 'payRemaining'])->name('guest.pay_remaining');
Route::get('/track/{trackCode}/remaining-return',[\App\Http\Controllers\Guest\PaymentController::class, 'remainingReturn'])->name('guest.remaining_return');

// ── Rider Portal (login with phone + PIN) ─────────────────────────────────
Route::get('/rider/login',  [\App\Http\Controllers\RiderController::class, 'loginPage'])->name('rider.login');
Route::post('/rider/login', [\App\Http\Controllers\RiderController::class, 'loginVerify'])->name('rider.login.verify');

// ── Rider PIN access from catalog sidebar ─────────────────────────────────
Route::post('/rider/pin',    [\App\Http\Controllers\RiderController::class, 'accessByPin'])->name('rider.pin');

// ── Rider access via pasted code (legacy) ─────────────────────────────────
Route::post('/rider/access', [\App\Http\Controllers\RiderController::class, 'accessByCode'])->name('rider.access');

// ── Rider Delivery Page (no login) ────────────────────────────────────────
Route::get('/rider/{orderId}/{token}',           [\App\Http\Controllers\RiderController::class, 'show'])->name('rider.show');
Route::post('/rider/{orderId}/{token}/delivered', [\App\Http\Controllers\RiderController::class, 'markDelivered'])->name('rider.delivered');
Route::post('/rider/{orderId}/{token}/issue',     [\App\Http\Controllers\RiderController::class, 'reportIssue'])->name('rider.issue');

// ── Customer Panel ────────────────────────────────────────────────────────
use App\Http\Controllers\Customer\CatalogController as CustomerCatalog;
use App\Http\Controllers\Customer\CheckoutController as CustomerCheckout;
use App\Http\Controllers\Customer\OrderController as CustomerOrder;
use App\Http\Controllers\Customer\CustomOrderController as CustomerCustomOrder;
use App\Http\Controllers\Customer\MessageController as CustomerMessage;
use App\Http\Controllers\Customer\ProfileController as CustomerProfile;
use App\Http\Controllers\Customer\AddressController as CustomerAddress;
use App\Http\Controllers\Customer\ReviewController as CustomerReview;
use App\Http\Controllers\Customer\DashboardController as CustomerDashboard;
use App\Http\Controllers\Customer\PaymentController as CustomerPayment;
use App\Http\Controllers\Auth\RegisterController;

// ── Customer Auth ─────────────────────────────────────────────────────────
Route::get('/register',  [RegisterController::class, 'show'])->name('register');
Route::post('/register', [RegisterController::class, 'store'])->name('register.post');
Route::get('/register/verify-otp',  [RegisterController::class, 'showVerify'])->name('register.verify');
Route::post('/register/verify-otp', [RegisterController::class, 'verifyOtp'])->name('register.verify.post');
Route::get('/register/back',        [RegisterController::class, 'back'])->name('register.back');

Route::prefix('customer')->name('customer.')->middleware('auth.customer')->group(function () {
    Route::get('/dashboard',   [CustomerDashboard::class, 'index'])->name('dashboard');

    Route::get('/catalog',          [CustomerCatalog::class, 'index'])->name('catalog');
    Route::post('/catalog/order',   [CustomerCatalog::class, 'order'])->name('catalog.order');

    Route::get('/checkout',         [CustomerCheckout::class, 'show'])->name('checkout');
    Route::post('/checkout/place',  [CustomerCheckout::class, 'placeOrder'])->name('checkout.place');

    Route::get('/orders',                          [CustomerOrder::class, 'index'])->name('orders');
    Route::post('/orders/{id}/cancel-request',     [CustomerOrder::class, 'requestCancel'])->name('orders.cancel_request');
    Route::post('/orders/{id}/review',             [CustomerReview::class, 'store'])->name('orders.review');

    Route::get('/custom-order',        [CustomerCustomOrder::class, 'show'])->name('custom_order');
    Route::post('/custom-order/place', [CustomerCustomOrder::class, 'store'])->name('custom_order.store');
    Route::post('/custom-orders/{id}/accept-price',    [CustomerOrder::class, 'acceptPrice'])->name('custom_orders.accept_price');
    Route::post('/custom-orders/{id}/cancel-price',    [CustomerOrder::class, 'cancelAfterPrice'])->name('custom_orders.cancel_price');
    Route::post('/custom-orders/{id}/set-deposit',     [CustomerOrder::class, 'setCustomDeposit'])->name('custom_orders.set_deposit');
    Route::get('/custom-orders/{id}/pay-deposit',      [\App\Http\Controllers\Guest\PaymentController::class, 'payCustomDeposit'])->name('custom_orders.pay_deposit');
    Route::get('/custom-orders/{id}/deposit-return',   [\App\Http\Controllers\Guest\PaymentController::class, 'customDepositReturn'])->name('custom_orders.deposit_return');

    Route::get('/messages',                           [CustomerMessage::class, 'index'])->name('messages');
    Route::get('/messages/thread/{orderId}',          [CustomerMessage::class, 'thread'])->name('messages.thread');
    Route::post('/messages/thread/{orderId}/send',    [CustomerMessage::class, 'send'])->name('messages.thread.send');
    Route::get('/messages/popup-data',                [CustomerMessage::class, 'popupData'])->name('messages.popup_data');
    Route::post('/messages/popup-send',               [CustomerMessage::class, 'popupSend'])->name('messages.popup_send');
    Route::post('/messages/mark-read-msg/{id}',       [CustomerMessage::class, 'markReadMsg'])->name('messages.mark_read_msg');
    Route::post('/messages/mark-order-read/{orderId}',[CustomerMessage::class, 'markOrderRead'])->name('messages.mark_order_read');

    Route::get('/profile',         [CustomerProfile::class, 'show'])->name('profile');
    Route::post('/profile/update', [CustomerProfile::class, 'update'])->name('profile.update');

    Route::get('/addresses',          [CustomerAddress::class, 'index'])->name('addresses');
    Route::post('/addresses',         [CustomerAddress::class, 'store'])->name('addresses.store');
    Route::post('/addresses/{id}/set-default', [CustomerAddress::class, 'setDefault'])->name('addresses.set_default');
    Route::post('/addresses/{id}/delete',      [CustomerAddress::class, 'destroy'])->name('addresses.destroy');

    Route::get('/pay-gcash',            [CustomerPayment::class, 'payGcash'])->name('pay_gcash');
    Route::get('/payment-return',       [CustomerPayment::class, 'paymentReturn'])->name('payment_return');
    Route::get('/pay-deposit/{id}',     [CustomerPayment::class, 'payDeposit'])->name('pay_deposit');
    Route::get('/deposit-return',       [CustomerPayment::class, 'depositReturn'])->name('deposit_return');
    Route::get('/pay-remaining/{id}',   [CustomerPayment::class, 'payRemaining'])->name('pay_remaining');
    Route::get('/remaining-return',     [CustomerPayment::class, 'remainingReturn'])->name('remaining_return');
});

// ── Admin Auth ────────────────────────────────────────────────────────────
// ── Super Admin Secret Portal (not linked publicly) ──────────────────────
Route::get('/superadmin/portal',  [LoginController::class, 'showSuperAdmin'])->name('superadmin.login');
Route::post('/superadmin/portal', [LoginController::class, 'loginSuperAdmin'])->name('superadmin.login.post');

// ── Seller Login (linked from homepage/catalog) ───────────────────────────
Route::get('/login',  [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');

// ── Logout ────────────────────────────────────────────────────────────────
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// ── Old admin login → redirect to secret portal ───────────────────────────
Route::get('/admin/login', fn() => redirect()->route('superadmin.login'));

Route::get('/admin/forgot-password',             [ForgotPasswordController::class, 'show'])->name('forgot.show');
Route::post('/admin/forgot-password/send-otp',   [ForgotPasswordController::class, 'sendOtp'])->name('forgot.send_otp');
Route::post('/admin/forgot-password/verify-otp', [ForgotPasswordController::class, 'verifyOtp'])->name('forgot.verify_otp');
Route::post('/admin/forgot-password/reset',      [ForgotPasswordController::class, 'reset'])->name('forgot.reset');
Route::get('/admin/forgot-password/back',        [ForgotPasswordController::class, 'back'])->name('forgot.back');

// ── Admin Panel ───────────────────────────────────────────────────────────
// ── Seller Routes ────────────────────────────────────────────────────────────
Route::prefix('seller')->name('seller.')->middleware('auth.seller')->group(function () {
    // Dashboard
    Route::get('/dashboard', [\App\Http\Controllers\Seller\DashboardController::class, 'index'])->name('dashboard');

    // Products
    Route::get('/products',                  [\App\Http\Controllers\Seller\ProductController::class, 'index'])->name('products');
    Route::post('/products',                 [\App\Http\Controllers\Seller\ProductController::class, 'store'])->name('products.store');
    Route::post('/products/{id}/update',     [\App\Http\Controllers\Seller\ProductController::class, 'update'])->name('products.update');
    Route::post('/products/{id}/archive',    [\App\Http\Controllers\Seller\ProductController::class, 'archive'])->name('products.archive');
    Route::post('/products/{id}/restore',    [\App\Http\Controllers\Seller\ProductController::class, 'restore'])->name('products.restore');
    Route::post('/products/{id}/toggle',     [\App\Http\Controllers\Seller\ProductController::class, 'toggleAvailable'])->name('products.toggle');
    Route::post('/products/{id}/discount',   [\App\Http\Controllers\Seller\ProductController::class, 'saveDiscount'])->name('products.discount');
    Route::post('/products/{id}/sizes',      [\App\Http\Controllers\Seller\ProductController::class, 'storeSize'])->name('products.sizes.store');
    Route::post('/products/sizes/{id}/archive', [\App\Http\Controllers\Seller\ProductController::class, 'archiveSize'])->name('products.sizes.archive');
    Route::post('/products/sizes/{id}/restore', [\App\Http\Controllers\Seller\ProductController::class, 'restoreSize'])->name('products.sizes.restore');

    // Orders
    Route::get('/orders',                    [\App\Http\Controllers\Seller\OrderController::class, 'index'])->name('orders');
    Route::post('/orders/{id}/status',       [\App\Http\Controllers\Seller\OrderController::class, 'updateStatus'])->name('orders.status');
    Route::post('/orders/{id}/assign-rider', [\App\Http\Controllers\Seller\OrderController::class, 'assignRider'])->name('orders.assign_rider');

    // Settings
    Route::get('/settings',          [\App\Http\Controllers\Seller\SettingsController::class, 'index'])->name('settings');
    Route::post('/settings/shop',    [\App\Http\Controllers\Seller\SettingsController::class, 'updateShop'])->name('settings.shop');
    Route::post('/settings/password',[\App\Http\Controllers\Seller\SettingsController::class, 'updatePassword'])->name('settings.password');
    Route::post('/settings/daily-capacity',  [\App\Http\Controllers\Seller\SettingsController::class, 'saveDailyCapacity'])->name('settings.daily_capacity');
    Route::post('/settings/shop-location',   [\App\Http\Controllers\Seller\SettingsController::class, 'saveShopLocation'])->name('settings.shop_location');
    Route::post('/zones/shop-location',      [\App\Http\Controllers\Seller\SettingsController::class, 'saveShopLocation'])->name('zones.shop_location');
    Route::get('/settings/shop-location',    fn() => redirect()->route('seller.settings'));
    Route::post('/settings/delivery-calc',   [\App\Http\Controllers\Seller\SettingsController::class, 'saveDeliveryCalc'])->name('settings.delivery_calc');
    Route::get('/settings/delivery-calc',    fn() => redirect()->route('seller.settings'));
    Route::post('/settings/appearance',      [\App\Http\Controllers\Seller\SettingsController::class, 'saveAppearance'])->name('settings.appearance');
    Route::post('/upgrade-request',          [\App\Http\Controllers\Seller\SettingsController::class, 'requestUpgrade'])->name('upgrade_request');

    // Kitchen
    Route::get('/kitchen',                             [\App\Http\Controllers\Seller\KitchenController::class, 'index'])->name('kitchen');
    Route::post('/kitchen/{id}/update',                [\App\Http\Controllers\Seller\KitchenController::class, 'update'])->name('kitchen.update');
    Route::post('/kitchen/{orderId}/assign-rider',     [\App\Http\Controllers\Seller\KitchenController::class, 'assignRiderAndDone'])->name('kitchen.assign_rider');
    Route::post('/kitchen/{orderId}/resend-rider-sms', [\App\Http\Controllers\Seller\KitchenController::class, 'resendRiderSms'])->name('kitchen.resend_sms');

    // Messages
    Route::get('/messages',                              [\App\Http\Controllers\Seller\MessageController::class, 'index'])->name('messages');
    Route::get('/messages/thread/{orderId}',             [\App\Http\Controllers\Seller\MessageController::class, 'thread'])->name('messages.thread');
    Route::post('/messages/thread/{orderId}/send',       [\App\Http\Controllers\Seller\MessageController::class, 'send'])->name('messages.send');
    Route::get('/messages/popup-data',                   [\App\Http\Controllers\Seller\MessageController::class, 'popupData'])->name('messages.popup_data');
    Route::post('/messages/popup-send',                  [\App\Http\Controllers\Seller\MessageController::class, 'popupSend'])->name('messages.popup_send');
    Route::post('/messages/mark-read-msg/{id}',          [\App\Http\Controllers\Seller\MessageController::class, 'markReadMsg'])->name('messages.mark_read_msg');
    Route::post('/messages/mark-order-read/{orderId}',   [\App\Http\Controllers\Seller\MessageController::class, 'markOrderRead'])->name('messages.mark_order_read');

    // Custom Orders
    Route::get('/custom-orders',                   [\App\Http\Controllers\Seller\CustomOrderController::class, 'index'])->name('custom_orders');
    Route::post('/custom-orders/{id}/approve',     [\App\Http\Controllers\Seller\CustomOrderController::class, 'approve'])->name('custom_orders.approve');
    Route::post('/custom-orders/{id}/reject',      [\App\Http\Controllers\Seller\CustomOrderController::class, 'reject'])->name('custom_orders.reject');
    Route::post('/custom-orders/{id}/progress',    [\App\Http\Controllers\Seller\CustomOrderController::class, 'sendProgress'])->name('custom_orders.progress');

    // Add-ons
    Route::get('/addons',                            [\App\Http\Controllers\Seller\AddonController::class, 'index'])->name('addons');
    Route::post('/addons/category',                  [\App\Http\Controllers\Seller\AddonController::class, 'storeCategory'])->name('addons.store_category');
    Route::post('/addons/category/{id}/update',      [\App\Http\Controllers\Seller\AddonController::class, 'updateCategory'])->name('addons.update_category');
    Route::post('/addons/category/{id}/toggle',      [\App\Http\Controllers\Seller\AddonController::class, 'toggleCategory'])->name('addons.toggle_category');
    Route::post('/addons/category/{id}/archive',     [\App\Http\Controllers\Seller\AddonController::class, 'archiveCategory'])->name('addons.archive_category');
    Route::post('/addons/category/{id}/restore',     [\App\Http\Controllers\Seller\AddonController::class, 'restoreCategory'])->name('addons.restore_category');
    Route::post('/addons',                           [\App\Http\Controllers\Seller\AddonController::class, 'store'])->name('addons.store');
    Route::post('/addons/{id}/update',               [\App\Http\Controllers\Seller\AddonController::class, 'update'])->name('addons.update');
    Route::post('/addons/{id}/toggle',               [\App\Http\Controllers\Seller\AddonController::class, 'toggle'])->name('addons.toggle');
    Route::post('/addons/{id}/archive',              [\App\Http\Controllers\Seller\AddonController::class, 'archive'])->name('addons.archive');
    Route::post('/addons/{id}/restore',              [\App\Http\Controllers\Seller\AddonController::class, 'restore'])->name('addons.restore');

    // Custom Options
    Route::get('/custom-options',                [\App\Http\Controllers\Seller\CustomOptionController::class, 'index'])->name('custom_options');
    Route::post('/custom-options',               [\App\Http\Controllers\Seller\CustomOptionController::class, 'store'])->name('custom_options.store');
    Route::post('/custom-options/{id}/update',   [\App\Http\Controllers\Seller\CustomOptionController::class, 'update'])->name('custom_options.update');
    Route::post('/custom-options/{id}/toggle',   [\App\Http\Controllers\Seller\CustomOptionController::class, 'toggle'])->name('custom_options.toggle');
    Route::post('/custom-options/{id}/archive',  [\App\Http\Controllers\Seller\CustomOptionController::class, 'archive'])->name('custom_options.archive');
    Route::post('/custom-options/{id}/restore',  [\App\Http\Controllers\Seller\CustomOptionController::class, 'restore'])->name('custom_options.restore');
    Route::post('/custom-options/{id}/sort-up',  [\App\Http\Controllers\Seller\CustomOptionController::class, 'sortUp'])->name('custom_options.sort_up');
    Route::post('/custom-options/{id}/sort-down',[\App\Http\Controllers\Seller\CustomOptionController::class, 'sortDown'])->name('custom_options.sort_down');

    // Delivery Zones
    Route::get('/zones',                   [\App\Http\Controllers\Seller\DeliveryZoneController::class, 'index'])->name('zones');
    Route::post('/zones',                  [\App\Http\Controllers\Seller\DeliveryZoneController::class, 'store'])->name('zones.store');
    Route::post('/zones/{id}/update',      [\App\Http\Controllers\Seller\DeliveryZoneController::class, 'update'])->name('zones.update');
    Route::post('/zones/{id}/toggle',      [\App\Http\Controllers\Seller\DeliveryZoneController::class, 'toggle'])->name('zones.toggle');
    Route::post('/zones/{id}/archive',     [\App\Http\Controllers\Seller\DeliveryZoneController::class, 'archive'])->name('zones.archive');
    Route::post('/zones/{id}/restore',     [\App\Http\Controllers\Seller\DeliveryZoneController::class, 'restore'])->name('zones.restore');

    // Riders
    Route::get('/riders',              [\App\Http\Controllers\Seller\RiderController::class, 'index'])->name('riders');
    Route::post('/riders',             [\App\Http\Controllers\Seller\RiderController::class, 'store'])->name('riders.store');
    Route::post('/riders/{id}/update', [\App\Http\Controllers\Seller\RiderController::class, 'update'])->name('riders.update');
    Route::post('/riders/{id}/toggle', [\App\Http\Controllers\Seller\RiderController::class, 'toggle'])->name('riders.toggle');

    // Reviews
    Route::get('/reviews', [\App\Http\Controllers\Seller\ReviewController::class, 'index'])->name('reviews');
});

// ── Super Admin Routes ───────────────────────────────────────────────────────
Route::prefix('admin')->name('superadmin.')->middleware('auth.superadmin')->group(function () {
    Route::get('/sellers',                     [\App\Http\Controllers\SuperAdmin\SellerController::class, 'index'])->name('sellers');
    Route::post('/sellers/{id}/approve',              [\App\Http\Controllers\SuperAdmin\SellerController::class, 'approve'])->name('sellers.approve');
    Route::post('/sellers/{id}/reject',               [\App\Http\Controllers\SuperAdmin\SellerController::class, 'reject'])->name('sellers.reject');
    Route::post('/sellers/{id}/suspend',              [\App\Http\Controllers\SuperAdmin\SellerController::class, 'suspend'])->name('sellers.suspend');
    Route::post('/sellers/{id}/toggle-commission',    [\App\Http\Controllers\SuperAdmin\SellerController::class, 'toggleCommission'])->name('sellers.toggle_commission');
    Route::post('/sellers/commission-bulk',           [\App\Http\Controllers\SuperAdmin\SellerController::class, 'bulkCommission'])->name('sellers.commission_bulk');
    Route::post('/sellers/{id}/commission-rate',      [\App\Http\Controllers\SuperAdmin\SellerController::class, 'updateCommissionRate'])->name('sellers.commission_rate');
    Route::post('/sellers/commission-rate-bulk',      [\App\Http\Controllers\SuperAdmin\SellerController::class, 'bulkCommissionRate'])->name('sellers.commission_rate_bulk');
    Route::post('/sellers/{id}/approve-upgrade',      [\App\Http\Controllers\SuperAdmin\SellerController::class, 'approveUpgrade'])->name('sellers.approve_upgrade');
    Route::post('/sellers/{id}/reject-upgrade',       [\App\Http\Controllers\SuperAdmin\SellerController::class, 'rejectUpgrade'])->name('sellers.reject_upgrade');
    Route::get('/platform-dashboard',          [\App\Http\Controllers\SuperAdmin\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/commission-analytics',        [\App\Http\Controllers\SuperAdmin\DashboardController::class, 'commissions'])->name('commissions');
    Route::get('/platform-settings',           [\App\Http\Controllers\SuperAdmin\PlatformSettingsController::class, 'index'])->name('settings');
    Route::post('/platform-settings',                     [\App\Http\Controllers\SuperAdmin\PlatformSettingsController::class, 'update'])->name('settings.update');
    Route::post('/platform-settings/paymongo',  [\App\Http\Controllers\SuperAdmin\PlatformSettingsController::class, 'savePaymongo'])->name('settings.paymongo');
    Route::post('/platform-settings/unisms',    [\App\Http\Controllers\SuperAdmin\PlatformSettingsController::class, 'saveUnisms'])->name('settings.unisms');
    Route::post('/platform-settings/dev-mode',  [\App\Http\Controllers\SuperAdmin\PlatformSettingsController::class, 'saveDevMode'])->name('settings.dev_mode');
    Route::post('/platform-settings/backup',    [\App\Http\Controllers\SuperAdmin\PlatformSettingsController::class, 'createBackup'])->name('settings.backup');
    Route::get('/platform-settings/restore',    [\App\Http\Controllers\SuperAdmin\PlatformSettingsController::class, 'restore'])->name('settings.restore');
    Route::get('/platform-settings/delete-backup', [\App\Http\Controllers\SuperAdmin\PlatformSettingsController::class, 'deleteBackup'])->name('settings.delete_backup');
});

Route::prefix('admin')->name('admin.')->middleware('auth.admin')->group(function () {
    Route::get('/dashboard', [AdminDashboard::class, 'index'])->name('dashboard');

    Route::get('/products',                        [ProductController::class, 'index'])->name('products.index');
    Route::post('/products',                       [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{id}/edit',              [ProductController::class, 'edit'])->name('products.edit');
    Route::post('/products/{id}/update',           [ProductController::class, 'update'])->name('products.update');
    Route::post('/products/{id}/delete',           [ProductController::class, 'destroy'])->name('products.destroy');
    Route::post('/products/{id}/toggle-available', [ProductController::class, 'toggleAvailable'])->name('products.toggle_available');
    Route::post('/products/{id}/discount',         [ProductController::class, 'saveDiscount'])->name('products.discount');
    Route::post('/products/{id}/sizes',            [ProductSizeController::class, 'store'])->name('products.sizes.store');
    Route::post('/products/sizes/{id}/delete',     [ProductSizeController::class, 'destroy'])->name('products.sizes.destroy');

    Route::get('/delivery-zones',              [DeliveryZoneController::class, 'index'])->name('delivery_zones.index');
    Route::post('/delivery-zones',             [DeliveryZoneController::class, 'store'])->name('delivery_zones.store');
    Route::post('/delivery-zones/{id}/update',[DeliveryZoneController::class, 'update'])->name('delivery_zones.update');
    Route::post('/delivery-zones/{id}/toggle',[DeliveryZoneController::class, 'toggle'])->name('delivery_zones.toggle');
    Route::post('/delivery-zones/{id}/delete',[DeliveryZoneController::class, 'destroy'])->name('delivery_zones.destroy');

    Route::get('/orders',                     [AdminOrder::class, 'index'])->name('orders.index');
    Route::post('/orders/{id}/update-status', [AdminOrder::class, 'updateStatus'])->name('orders.update_status');
    Route::post('/orders/{id}/confirm',       [AdminOrder::class, 'confirmOrder'])->name('orders.confirm');
    Route::post('/orders/{id}/request-deposit',[AdminOrder::class, 'requestDeposit'])->name('orders.request_deposit');
    Route::post('/orders/{id}/assign-rider',  [AdminOrder::class, 'assignRider'])->name('orders.assign_rider');
    Route::post('/orders/{id}/accept-cancel', [AdminOrder::class, 'acceptCancel'])->name('orders.accept_cancel');
    Route::post('/orders/{id}/reject-cancel', [AdminOrder::class, 'rejectCancel'])->name('orders.reject_cancel');
    Route::post('/orders/{id}/send-to-kitchen',[AdminOrder::class, 'sendToKitchen'])->name('orders.send_to_kitchen');
    Route::post('/orders/{id}/resolve-issue', [\App\Http\Controllers\Admin\RiderController::class, 'resolveIssue'])->name('orders.resolve_issue');
    Route::post('/orders/{id}/mark-settled',  [\App\Http\Controllers\Admin\RiderController::class, 'markSettled'])->name('orders.mark_settled');

    Route::get('/riders',              [\App\Http\Controllers\Admin\RiderController::class, 'index'])->name('riders.index');
    Route::post('/riders',             [\App\Http\Controllers\Admin\RiderController::class, 'store'])->name('riders.store');
    Route::post('/riders/{id}/update', [\App\Http\Controllers\Admin\RiderController::class, 'update'])->name('riders.update');
    Route::post('/riders/{id}/toggle', [\App\Http\Controllers\Admin\RiderController::class, 'toggle'])->name('riders.toggle');

    Route::get('/messages',                              [AdminMessage::class, 'index'])->name('messages.index');
    Route::get('/messages/popup-data',                   [AdminMessage::class, 'popupData'])->name('messages.popup_data');
    Route::post('/messages/popup-send',                  [AdminMessage::class, 'popupSend'])->name('messages.popup_send');
    Route::post('/messages/mark-read-msg/{id}',          [AdminMessage::class, 'markReadMsg'])->name('messages.mark_read_msg');
    Route::post('/messages/mark-order-read/{order_id}',  [AdminMessage::class, 'markOrderRead'])->name('messages.mark_order_read');
    Route::get('/messages/thread/{order_id}',            [AdminMessage::class, 'thread'])->name('messages.thread');
    Route::post('/messages/thread/{order_id}/send',      [AdminMessage::class, 'send'])->name('messages.send');

    Route::get('/settings',                             [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/site',                       [SettingsController::class, 'saveSite'])->name('settings.site');
    Route::post('/settings/paymongo',                   [SettingsController::class, 'savePaymongo'])->name('settings.paymongo');
    Route::post('/settings/shop-location',              [SettingsController::class, 'saveShopLocation'])->name('settings.shop_location');
    Route::post('/settings/profile',                    [SettingsController::class, 'saveProfile'])->name('settings.profile');
    Route::post('/settings/password/send-otp',          [SettingsController::class, 'changePasswordSendOtp'])->name('settings.password.send_otp');
    Route::get('/settings/password/back',               [SettingsController::class, 'changePasswordBack'])->name('settings.password.back');
    Route::post('/settings/password/verify-otp',        [SettingsController::class, 'changePasswordVerifyOtp'])->name('settings.password.verify_otp');
    Route::post('/settings/password',                   [SettingsController::class, 'changePassword'])->name('settings.password');
    Route::post('/settings/daily-capacity',             [SettingsController::class, 'saveDailyCapacity'])->name('settings.daily_capacity');
    Route::post('/settings/backup',                     [SettingsController::class, 'createBackup'])->name('settings.backup');
    Route::get('/settings/restore',                     [SettingsController::class, 'restore'])->name('settings.restore');
    Route::get('/settings/delete-backup',               [SettingsController::class, 'deleteBackup'])->name('settings.delete_backup');

    Route::get('/addons',                            [AddonController::class, 'index'])->name('addons.index');
    Route::post('/addons/category',                  [AddonController::class, 'storeCategory'])->name('addons.store_category');
    Route::post('/addons/category/{id}/update',      [AddonController::class, 'updateCategory'])->name('addons.update_category');
    Route::post('/addons/category/{id}/toggle',      [AddonController::class, 'toggleCategory'])->name('addons.toggle_category');
    Route::post('/addons/category/{id}/delete',      [AddonController::class, 'destroyCategory'])->name('addons.destroy_category');
    Route::post('/addons',                           [AddonController::class, 'store'])->name('addons.store');
    Route::post('/addons/{id}/update',               [AddonController::class, 'update'])->name('addons.update');
    Route::post('/addons/{id}/toggle',               [AddonController::class, 'toggle'])->name('addons.toggle');
    Route::post('/addons/{id}/delete',               [AddonController::class, 'destroy'])->name('addons.destroy');

    Route::get('/custom-options',               [CustomOrderOptionsController::class, 'index'])->name('custom_options.index');
    Route::post('/custom-options',              [CustomOrderOptionsController::class, 'store'])->name('custom_options.store');
    Route::post('/custom-options/{id}/update',  [CustomOrderOptionsController::class, 'update'])->name('custom_options.update');
    Route::post('/custom-options/{id}/toggle',  [CustomOrderOptionsController::class, 'toggle'])->name('custom_options.toggle');
    Route::post('/custom-options/{id}/delete',  [CustomOrderOptionsController::class, 'destroy'])->name('custom_options.destroy');
    Route::post('/custom-options/{id}/sort-up', [CustomOrderOptionsController::class, 'sortUp'])->name('custom_options.sort_up');
    Route::post('/custom-options/{id}/sort-down',[CustomOrderOptionsController::class, 'sortDown'])->name('custom_options.sort_down');

    Route::get('/custom-orders',                   [AdminCustomOrder::class, 'index'])->name('custom_orders.index');
    Route::post('/custom-orders/{id}/approve',     [AdminCustomOrder::class, 'approve'])->name('custom_orders.approve');
    Route::post('/custom-orders/{id}/reject',      [AdminCustomOrder::class, 'reject'])->name('custom_orders.reject');
    Route::post('/custom-orders/{id}/progress',    [AdminCustomOrder::class, 'sendProgress'])->name('custom_orders.progress');

    Route::get('/kitchen',                             [KitchenController::class, 'index'])->name('kitchen.index');
    Route::post('/kitchen/{id}/update',                [KitchenController::class, 'update'])->name('kitchen.update');
    Route::post('/kitchen/{orderId}/assign-rider',     [KitchenController::class, 'assignRiderAndDone'])->name('kitchen.assign_rider');
    Route::post('/kitchen/{orderId}/resend-rider-sms', [KitchenController::class, 'resendRiderSms'])->name('kitchen.resend_sms');

    Route::get('/logs', [LogController::class, 'index'])->name('logs.index');
    Route::get('/notifications/mark-read', [NotificationController::class, 'markReadAdmin'])->name('notifications.mark_read');
});

// ── Admin Panel (Shop Admin) ──────────────────────────────────────────────
Route::prefix('admin')->name('admin.')->middleware('auth.admin')->group(function () {
    Route::get('/dashboard', [AdminDashboard::class, 'index'])->name('dashboard');

    Route::get('/products',                        [ProductController::class, 'index'])->name('products.index');
    Route::post('/products',                       [ProductController::class, 'store'])->name('products.store');
    Route::post('/products/{id}/update',           [ProductController::class, 'update'])->name('products.update');
    Route::post('/products/{id}/delete',           [ProductController::class, 'destroy'])->name('products.destroy');
    Route::post('/products/{id}/toggle-available', [ProductController::class, 'toggleAvailable'])->name('products.toggle_available');
    Route::post('/products/{id}/sizes',            [ProductSizeController::class, 'store'])->name('products.sizes.store');
    Route::post('/products/sizes/{id}/delete',     [ProductSizeController::class, 'destroy'])->name('products.sizes.destroy');

    Route::get('/delivery-zones',              [DeliveryZoneController::class, 'index'])->name('delivery_zones.index');
    Route::post('/delivery-zones',             [DeliveryZoneController::class, 'store'])->name('delivery_zones.store');
    Route::post('/delivery-zones/{id}/update', [DeliveryZoneController::class, 'update'])->name('delivery_zones.update');
    Route::post('/delivery-zones/{id}/toggle', [DeliveryZoneController::class, 'toggle'])->name('delivery_zones.toggle');
    Route::post('/delivery-zones/{id}/delete', [DeliveryZoneController::class, 'destroy'])->name('delivery_zones.destroy');

    Route::get('/orders',                      [AdminOrder::class, 'index'])->name('orders.index');
    Route::post('/orders/{id}/update-status',  [AdminOrder::class, 'updateStatus'])->name('orders.update_status');
    Route::post('/orders/{id}/confirm',        [AdminOrder::class, 'confirmOrder'])->name('orders.confirm');
    Route::post('/orders/{id}/request-deposit',[AdminOrder::class, 'requestDeposit'])->name('orders.request_deposit');
    Route::post('/orders/{id}/assign-rider',   [AdminOrder::class, 'assignRider'])->name('orders.assign_rider');
    Route::post('/orders/{id}/accept-cancel',  [AdminOrder::class, 'acceptCancel'])->name('orders.accept_cancel');
    Route::post('/orders/{id}/reject-cancel',  [AdminOrder::class, 'rejectCancel'])->name('orders.reject_cancel');
    Route::post('/orders/{id}/send-to-kitchen',[AdminOrder::class, 'sendToKitchen'])->name('orders.send_to_kitchen');
    Route::post('/orders/{id}/resolve-issue',  [AdminRider::class, 'resolveIssue'])->name('orders.resolve_issue');
    Route::post('/orders/{id}/mark-settled',   [AdminRider::class, 'markSettled'])->name('orders.mark_settled');

    Route::get('/riders',              [AdminRider::class, 'index'])->name('riders.index');
    Route::post('/riders',             [AdminRider::class, 'store'])->name('riders.store');
    Route::post('/riders/{id}/update', [AdminRider::class, 'update'])->name('riders.update');
    Route::post('/riders/{id}/toggle', [AdminRider::class, 'toggle'])->name('riders.toggle');

    Route::get('/messages',                             [AdminMessage::class, 'index'])->name('messages.index');
    Route::get('/messages/popup-data',                  [AdminMessage::class, 'popupData'])->name('messages.popup_data');
    Route::post('/messages/popup-send',                 [AdminMessage::class, 'popupSend'])->name('messages.popup_send');
    Route::post('/messages/mark-read-msg/{id}',         [AdminMessage::class, 'markReadMsg'])->name('messages.mark_read_msg');
    Route::post('/messages/mark-order-read/{order_id}', [AdminMessage::class, 'markOrderRead'])->name('messages.mark_order_read');
    Route::get('/messages/thread/{order_id}',           [AdminMessage::class, 'thread'])->name('messages.thread');
    Route::post('/messages/thread/{order_id}/send',     [AdminMessage::class, 'send'])->name('messages.send');

    Route::get('/settings',                              [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/site',                        [SettingsController::class, 'saveSite'])->name('settings.site');
    Route::post('/settings/paymongo',                    [SettingsController::class, 'savePaymongo'])->name('settings.paymongo');
    Route::post('/settings/shop-location',               [SettingsController::class, 'saveShopLocation'])->name('settings.shop_location');
    Route::post('/settings/profile',                     [SettingsController::class, 'saveProfile'])->name('settings.profile');
    Route::post('/settings/password/send-otp',           [SettingsController::class, 'changePasswordSendOtp'])->name('settings.password.send_otp');
    Route::get('/settings/password/back',                [SettingsController::class, 'changePasswordBack'])->name('settings.password.back');
    Route::post('/settings/password/verify-otp',         [SettingsController::class, 'changePasswordVerifyOtp'])->name('settings.password.verify_otp');
    Route::post('/settings/password',                    [SettingsController::class, 'changePassword'])->name('settings.password');
    Route::post('/settings/daily-capacity',              [SettingsController::class, 'saveDailyCapacity'])->name('settings.daily_capacity');
    Route::post('/settings/backup',                      [SettingsController::class, 'createBackup'])->name('settings.backup');
    Route::get('/settings/restore',                      [SettingsController::class, 'restore'])->name('settings.restore');
    Route::get('/settings/delete-backup',                [SettingsController::class, 'deleteBackup'])->name('settings.delete_backup');

    Route::get('/addons',                           [AddonController::class, 'index'])->name('addons.index');
    Route::post('/addons/category',                 [AddonController::class, 'storeCategory'])->name('addons.store_category');
    Route::post('/addons/category/{id}/update',     [AddonController::class, 'updateCategory'])->name('addons.update_category');
    Route::post('/addons/category/{id}/toggle',     [AddonController::class, 'toggleCategory'])->name('addons.toggle_category');
    Route::post('/addons/category/{id}/delete',     [AddonController::class, 'destroyCategory'])->name('addons.destroy_category');
    Route::post('/addons',                          [AddonController::class, 'store'])->name('addons.store');
    Route::post('/addons/{id}/update',              [AddonController::class, 'update'])->name('addons.update');
    Route::post('/addons/{id}/toggle',              [AddonController::class, 'toggle'])->name('addons.toggle');
    Route::post('/addons/{id}/delete',              [AddonController::class, 'destroy'])->name('addons.destroy');

    Route::get('/custom-options',                [CustomOrderOptionsController::class, 'index'])->name('custom_options.index');
    Route::post('/custom-options',               [CustomOrderOptionsController::class, 'store'])->name('custom_options.store');
    Route::post('/custom-options/{id}/update',   [CustomOrderOptionsController::class, 'update'])->name('custom_options.update');
    Route::post('/custom-options/{id}/toggle',   [CustomOrderOptionsController::class, 'toggle'])->name('custom_options.toggle');
    Route::post('/custom-options/{id}/delete',   [CustomOrderOptionsController::class, 'destroy'])->name('custom_options.destroy');
    Route::post('/custom-options/{id}/sort-up',  [CustomOrderOptionsController::class, 'sortUp'])->name('custom_options.sort_up');
    Route::post('/custom-options/{id}/sort-down',[CustomOrderOptionsController::class, 'sortDown'])->name('custom_options.sort_down');

    Route::get('/custom-orders',                [AdminCustomOrder::class, 'index'])->name('custom_orders.index');
    Route::post('/custom-orders/{id}/approve',  [AdminCustomOrder::class, 'approve'])->name('custom_orders.approve');
    Route::post('/custom-orders/{id}/reject',   [AdminCustomOrder::class, 'reject'])->name('custom_orders.reject');
    Route::post('/custom-orders/{id}/progress', [AdminCustomOrder::class, 'sendProgress'])->name('custom_orders.progress');

    Route::get('/kitchen',                             [KitchenController::class, 'index'])->name('kitchen.index');
    Route::post('/kitchen/{id}/update',                [KitchenController::class, 'update'])->name('kitchen.update');
    Route::post('/kitchen/{orderId}/assign-rider',     [KitchenController::class, 'assignRiderAndDone'])->name('kitchen.assign_rider');
    Route::post('/kitchen/{orderId}/resend-rider-sms', [KitchenController::class, 'resendRiderSms'])->name('kitchen.resend_sms');

    Route::get('/logs', [LogController::class, 'index'])->name('logs.index');
    Route::get('/notifications/mark-read', [NotificationController::class, 'markReadAdmin'])->name('notifications.mark_read');
});
