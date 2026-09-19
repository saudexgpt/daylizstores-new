<?php

// use Illuminate\Support\Facades\Route;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
// api to fetch all registered products for external requests
// $router->get('hello-message', function () {
//     return response()->json(['message' => 'Hello World'], 200);
// });
// $router->get('get-articles', [ArticlesController::class, 'index']);

$router->get('fetch-location', 'Location\LocationsController@fetchAllLocations');

// Public (guest) order endpoints. Each is throttled per client: placing an
// order or tracking one is never needed more than a few times a minute, and
// the limit is what makes brute-forcing order numbers / spamming orders (and
// the account-creation + email that comes with a new customer) impractical.
$router->post('order/store', 'Order\OrdersController@store')->middleware('throttle:order-submit');
$router->post('order/validate-cart', 'Order\OrdersController@validateCart');
$router->post('order/paystack/initialize', 'Order\OrdersController@initializePaystackPayment')->middleware('throttle:order-submit');
$router->get('order/paystack/callback', 'Order\OrdersController@paystackCallback');
$router->post('order/paystack/webhook', 'Order\OrdersController@paystackWebhook');

$router->post('order/search', 'Order\OrdersController@search')->middleware('throttle:order-tracking');
$router->post('auth/login', 'AuthController@login');
$router->post('auth/recover-password', 'AuthController@recoverPassword');
$router->post('auth/reset-password', 'AuthController@resetPassword');
$router->post('auth/confirm-password-reset-token', 'AuthController@confirmPasswordResetToken');

$router->get('/menu-category', 'Stock\CategoriesController@index');
$router->get('get-items', 'Stock\ItemsController@index');
$router->get('item-show/{item}', 'Stock\ItemsController@show');
$router->get('item-details', 'Stock\ItemsController@itemDetails');
$router->get('latest-products', 'Stock\ItemsController@fetchLatestProducts');
$router->get('search-product', 'Stock\ItemsController@searchProduct');

$router->get('item-reviews', 'Stock\ItemsController@itemReviews');
$router->get('all-items', 'Stock\ItemsController@fetchAllItems');
$router->post('give-product-review', 'Stock\ItemsController@giveReview')->middleware('throttle:review-submit');

$router->get('fetch-necessary-params', 'Controller@fetchNecessayParams');

$router->group(['middleware' => 'auth:api'], function () use ($router) {

    $router->get('auth/user', 'AuthController@user');
    $router->post('auth/logout', 'AuthController@logout');
    $router->get('users', 'UserController@index')->middleware('permission:' . \App\Laravue\Acl::PERMISSION_USER_MANAGE);
    $router->get('user-notifications', 'UserController@userNotifications');

    $router->post('users', 'UserController@store')->middleware('permission:' . \App\Laravue\Acl::PERMISSION_USER_MANAGE);

    $router->get('users/{user}', 'UserController@show')->middleware('permission:' . \App\Laravue\Acl::PERMISSION_USER_MANAGE);
    $router->put('users/{user}', 'UserController@update');
    // Role changes are admin-only; the controller re-checks isAdmin() (admins pass every permission check).
    $router->put('users/assign-role/{user}', 'UserController@assignRole')->middleware('permission:' . \App\Laravue\Acl::PERMISSION_USER_MANAGE);

    $router->post('users/update-password', 'UserController@updatePassword');
    $router->put('users/reset-password/{user}', 'UserController@adminResetUserPassword')->middleware('permission:' . \App\Laravue\Acl::PERMISSION_USER_MANAGE);

    $router->delete('users/{user}', 'UserController@destroy')->middleware('permission:' . \App\Laravue\Acl::PERMISSION_USER_MANAGE);
    $router->get('users/{user}/permissions', 'UserController@permissions')->middleware('permission:' . \App\Laravue\Acl::PERMISSION_PERMISSION_MANAGE);
    $router->put('users/{user}/permissions', 'UserController@updatePermissions')->middleware('permission:' . \App\Laravue\Acl::PERMISSION_PERMISSION_MANAGE);
    $router->apiResource('roles', 'RoleController')->middleware('permission:' . \App\Laravue\Acl::PERMISSION_PERMISSION_MANAGE);
    $router->get('roles/{role}/permissions', 'RoleController@permissions')->middleware('permission:' . \App\Laravue\Acl::PERMISSION_PERMISSION_MANAGE);
    $router->apiResource('permissions', 'PermissionController')->middleware('permission:' . \App\Laravue\Acl::PERMISSION_PERMISSION_MANAGE);


    // Product images only — any signed-in customer used to be able to upload here.
    $router->post('upload-file', 'Controller@uploadFile')->middleware('permission:create menu');
    $router->group(['prefix' => 'dashboard'], function () use ($router) {
        //customer
        $router->group(['prefix' => 'admin'], function () use ($router) {
            $router->get('/', 'DashboardController@adminDashboard')->middleware('permission:view admin dashboard');
            $router->get('running-out-of-stock-products', 'DashboardController@itemsRunningOutOfStock')->middleware('permission:view admin dashboard');
        });
    });
    /////////////////////////STOCKS MODULE////////////////////////////
    $router->group(['prefix' => 'stock', 'namespace' => 'Stock'], function () use ($router) {
        /////////////////////////////general stock////////////////////////
        $router->group(['prefix' => 'general-items'], function () use ($router) {

            $router->get('/', 'ItemsController@index');
            $router->group(['middleware' => ['permission:create menu']], function () use ($router) {

                $router->post('store', 'ItemsController@store');
                $router->put('update/{item}', 'ItemsController@update');
                $router->put('stockup/{item}', 'ItemsController@stock');

                $router->delete('delete/{item}', 'ItemsController@destroy');
                $router->put('toggle-status/{item}', 'ItemsController@toggleStatus');

                $router->group(['prefix' => 'prices'], function () use ($router) {
                    $router->post('store', 'ItemPricesController@store');
                    $router->put('update/{item_price}', 'ItemPricesController@update');
                    $router->delete('delete/{item_price}', 'ItemPricesController@destroy');
                });

                $router->put('approve/{review}', 'ItemsController@approveReview');
            });
        });

        // out-of-stock / low-stock list with sales rate and suggested restock quantity (+ ?format=csv)
        $router->get('restock', 'RestockController@index')->middleware('permission:create menu');

        ///////////////////create menu//////////////////////////////////
        $router->group(['prefix' => 'item-category'], function () use ($router) {
            $router->get('/', 'CategoriesController@index');
            $router->group(['middleware' => ['permission:create menu']], function () use ($router) {
                $router->post('store', 'CategoriesController@store');
                $router->put('update/{category}', 'CategoriesController@update');
                $router->delete('delete/{category}', 'CategoriesController@destroy');
            });
        });
    });
    ////////////////////////////////////STOCK ENDS/////////////////////////////////////////////
    //////////////////////////////REPORTS//////////////////////////////
    $router->group(['prefix' => 'reports'], function () use ($router) {
        $router->get('audit-trails', 'ReportsController@auditTrails')->middleware('permission:view audit trail');
        $router->get('notification/mark-as-read', 'ReportsController@markAsRead');
        $router->get('backups', 'ReportsController@backUps')->middleware('permission:backup database');

        // the report centre: every report in App\Services\Reports\ReportRegistry through one set of endpoints
        $router->group(['middleware' => 'permission:view reports|view accounting'], function () use ($router) {
            $router->get('catalog', 'Reports\ReportCenterController@catalog');
            $router->get('run/{key}', 'Reports\ReportCenterController@run');
            $router->get('export/{key}', 'Reports\ReportCenterController@export');
        });
    });
    ////////////////////////////////////////////////////////////////////////////////////////
    $router->group(['prefix' => 'order', 'namespace' => 'Order'], function () use ($router) {
        $router->group(['prefix' => 'general'], function () use ($router) {

            $router->get('/', 'OrdersController@index')->middleware('permission:view order');
            $router->get('show/{order}', 'OrdersController@show');
            $router->get('my-orders', 'OrdersController@myOrders');

            $router->get('search-order', 'OrdersController@adminSearchOrder')->middleware('permission:view order');

            $router->put('change-status/{order}', 'OrdersController@changeOrderStatus')->middleware('permission:approve order|cancel order');

            $router->put('reverse-cancellation/{order}', 'OrdersController@reverseOrderCancellation')->middleware('permission:approve order|cancel order');

            $router->put('assign-order-to-location/{order}', 'OrdersController@assignOrderToLocation')->middleware('permission:assign order to location');
        });
    });
    ////////////////////////////////////LOCATION/////////////////////////////////////////////
    $router->group(['prefix' => 'location', 'namespace' => 'Location'], function () use ($router) {

        $router->group(['middleware' => 'permission:manage location'], function () use ($router) {

            $router->get('/', 'LocationsController@index');
            $router->get('/assignable-users', 'LocationsController@assignableUsers');

            $router->post('store', 'LocationsController@store');
            $router->put('update/{location}', 'LocationsController@update');
            $router->delete('delete/{location}', 'LocationsController@destroy');

            $router->post('add-user-to-location', 'LocationsController@addUserToLocation');
        });
    });

    ////////////////////////////////////PRODUCT COSTING//////////////////////////////////////
    // Receiving stock with its cost, stock adjustments, and switching costing on. Recording is for whoever
    // manages products ("create menu"); reading costs needs "view cost"; the cut-over needs "manage accounting".
    $router->group(['prefix' => 'costing', 'namespace' => 'Costing'], function () use ($router) {
        $router->get('state', 'CostingController@state')->middleware('permission:create menu|manage accounting|view cost');

        $router->get('receipts', 'ReceiptsController@index')->middleware('permission:view cost');
        $router->get('receipts/products', 'ReceiptsController@products')->middleware('permission:create menu');
        $router->get('payment-accounts', 'ReceiptsController@paymentAccounts')->middleware('permission:create menu');
        $router->get('receipts/{receipt}', 'ReceiptsController@show')->middleware('permission:view cost');
        $router->post('receipts', 'ReceiptsController@store')->middleware('permission:create menu');
        $router->post('receipts/{receipt}/void', 'ReceiptsController@void')->middleware('permission:manage accounting');

        $router->get('adjustments', 'AdjustmentsController@index')->middleware('permission:create menu');
        $router->post('adjustments', 'AdjustmentsController@store')->middleware('permission:create menu');

        $router->get('reconcile', 'CostingController@reconcile')->middleware('permission:manage accounting|view cost');
        $router->get('shelf', 'CostingController@shelf')->middleware('permission:manage accounting');
        $router->post('check', 'CostingController@check')->middleware('permission:manage accounting');
        $router->post('go-live', 'CostingController@goLive')->middleware('permission:manage accounting');
    });

    ////////////////////////////////////ACCOUNTING///////////////////////////////////////////
    // Reading the books needs "view accounting"; recording / correcting / voiding and changing
    // the chart or settings needs "manage accounting" (admins hold both).
    $router->group(['prefix' => 'accounting', 'namespace' => 'Accounting'], function () use ($router) {
        $router->group(['middleware' => 'permission:view accounting'], function () use ($router) {
            $router->get('overview', 'StatementsController@overview');
            $router->get('statements/profit-loss', 'StatementsController@profitLoss');
            $router->get('statements/balance-sheet', 'StatementsController@balanceSheet');
            $router->get('statements/trial-balance', 'StatementsController@trialBalance');
            $router->get('statements/ledger', 'StatementsController@ledger');
            $router->get('accounts', 'AccountsController@index');
            $router->get('transactions', 'TransactionsController@index');
            $router->get('transactions/{entry}', 'TransactionsController@show');
            $router->get('settings', 'SettingsController@show');
        });
        $router->group(['middleware' => 'permission:manage accounting'], function () use ($router) {
            $router->post('accounts', 'AccountsController@store');
            $router->put('accounts/{account}', 'AccountsController@update');
            $router->delete('accounts/{account}', 'AccountsController@destroy');
            $router->post('transactions', 'TransactionsController@store');
            $router->put('transactions/{entry}', 'TransactionsController@update');
            $router->post('transactions/{entry}/void', 'TransactionsController@void');
            $router->put('settings', 'SettingsController@update');
            $router->post('sync-sales', 'SettingsController@syncSales');
        });
    });
});
