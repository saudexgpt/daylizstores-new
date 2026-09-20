<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\ChangeOrderStatusRequest;
use App\Services\Costing\Costing;
use App\Http\Requests\Order\InitializePaystackPaymentRequest;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\ValidateCartRequest;
use App\Laravue\Models\User;
use App\Models\Order\Order;
use App\Models\Order\OrderStatus;
use App\Models\Order\OrderItem;
use App\Mail\CustomerCredentials;
use App\Services\Orders\OrderEmails;
use App\Models\ItemDiscount;
use App\Models\Stock\ItemPrice;
use App\Models\Stock\ItemSizePrice;
use App\Models\Stock\ItemStock;
use App\Services\PaystackService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class OrdersController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        //
        $user = $this->getUser();
        $location_id = $request->location_id;
        $condition = [];
        $condition2 = [];
        // if ($location_id !== 'all') {

        //     $condition = ['location_id' => $location_id];
        // }
        if (isset($request->status) && $request->status != '') {
            ////// query by status //////////////
            $status = $request->status;
            $condition2 = ['order_status' => $status];
        }
        $orders = Order::with([
            'customer',
            'orderItems.item',
            'orderItems.stock'
        ])->where($condition)->where($condition2)->orderBy('id', 'DESC')
            // bounded: the page size comes from the client
            ->paginate(max(1, min((int) ($request->limit ?: 15), 100)));
        return response()->json(compact('orders'));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function assignOrderToWarehouse(Request $request, Order $order)
    {

        $location_id = $request->location_id;
        $order->location_id = $location_id;
        $order->save();

        return $this->show($order);
    }

    private function registerCustomer($data)
    {
        $user = User::where('email', $data->email)->first();
        if (!$user) {
            $user = new User();
            $user->name = $data->name;
            $user->phone = $data->phone;
            $user->email = $data->email;
            $password = randomPassword();
            $user->password = $password;
            $user->password_status = 'default';
            $user->address = $data->address;
            $user->nearest_bustop = $data->nearest_bustop;
            $user->save();

            // send login credentials email to the newly registered guest-checkout customer
            try {
                Mail::to($user)->send(new CustomerCredentials($user, $password));
            } catch (\Throwable $th) {
                //throw $th;
            }
            return $user;
        }
        // An existing account is left exactly as it is: this endpoint is
        // open to guests, so whoever types an email must not be able to
        // rewrite that account's saved address. The order itself carries
        // the delivery address it was placed with.
        return $user;
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    public function getInvoiceNo($prefix, $next_no)
    {
        $no_of_digits = 5;

        $digit_of_next_no = strlen($next_no);
        $unused_digit = $no_of_digits - $digit_of_next_no;
        $zeros = '';
        for ($i = 1; $i <= $unused_digit; $i++) {
            $zeros .= '0';
        }

        return $prefix . $zeros . $next_no;
    }
    /**
     * Checks every cart line against live stock. A line is unavailable (and
     * reported with a balance of 0) when its stock row no longer exists, its
     * product has been disabled or deleted, or nothing is left; otherwise
     * it's reported with whatever balance remains. Quantities are tracked per
     * stock row, so lines can never collectively claim more than exists.
     *
     * Pass $lock = true from inside a transaction to lock the stock rows
     * (SELECT ... FOR UPDATE) so two concurrent orders can't both pass the
     * check for the last units.
     *
     * @param array $cart validated cart lines (stock_id, quantity, name)
     * @return array [bool $limited_stock, array $details]
     */
    private function checkStockBeforeOrdering(array $cart, $lock = false)
    {
        $stockQuery = ItemStock::with('item')->whereIn('id', array_column($cart, 'stock_id'));
        if ($lock) {
            $stockQuery->lockForUpdate();
        }
        $stocks = $stockQuery->get()->keyBy('id');

        $limited_stock = false;
        $details = [];
        $remaining = [];
        foreach ($cart as $line) {
            $stockId = (int) $line['stock_id'];
            if (!isset($remaining[$stockId])) {
                $stock = $stocks->get($stockId);
                $sellable = $stock && $stock->item && $stock->item->enabled;
                $remaining[$stockId] = $sellable
                    ? max(0, $stock->quantity_stocked - $stock->reserved - $stock->sold)
                    : 0;
            }
            $wanted = (int) $line['quantity'];
            if ($wanted > $remaining[$stockId]) {
                $limited_stock = true;
                $line['quantity'] = $remaining[$stockId];
                $details[] = [
                    'product' => $line['name'] ?? '',
                    'balance' => $remaining[$stockId],
                    'updated_item' => $line,
                ];
                $remaining[$stockId] = 0;
            } else {
                $remaining[$stockId] -= $wanted;
            }
        }
        return array($limited_stock, $details);
    }
    /**
     * Stores the (already validated jpg/png) payment evidence. Receipts live
     * inside the web root, so: the extension comes from the file's actual
     * content — never the client-supplied filename — and the name is random
     * and unguessable, since anyone who can guess a URL can read a customer's
     * bank slip.
     */
    private function storeReceiptImage($image)
    {
        $folder = 'storage/receipt';
        $this->makeReceiptFolderInert($folder);
        $name = Str::random(40) . '.' . $image->extension();
        $path = $image->storeAs($folder, $name, 'public');
        return '/' . $path;
    }
    /**
     * Defence in depth on Apache: even if a script file ever landed in the
     * receipts folder, it must not be executable or listable from the web.
     */
    private function makeReceiptFolderInert($folder)
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        if (!$disk->exists($folder . '/.htaccess')) {
            $disk->put($folder . '/.htaccess', "Options -Indexes -ExecCGI\nRemoveHandler .php .phtml .php3 .php4 .php5 .php7 .phar\nRemoveType .php .phtml .php3 .php4 .php5 .php7 .phar\n<FilesMatch \"\\.(php|phtml|php[0-9]|phar)$\">\n    Require all denied\n</FilesMatch>\n");
        }
    }
    /**
     * Re-check live stock for the customer's current cart without placing
     * an order — lets the cart/checkout UI flag items that have sold out or
     * dropped below the requested quantity since they were added, rather
     * than the customer only finding out after clicking Submit Order.
     */
    public function validateCart(ValidateCartRequest $request)
    {
        list($limited_stock, $details) = $this->checkStockBeforeOrdering($request->validated()['cart_items']);
        return response()->json(compact('limited_stock', 'details'), 200);
    }
    public function store(StoreOrderRequest $request)
    {
        if (!$this->settingEnabled('can_make_order')) {
            return response()->json(['message' => 'Order placement is disabled for now'], 500);
        }
        $result = $this->placeOrder($request, $request->validated()['cart_items'], function () use ($request) {
            return [
                'receipt_image' => $this->storeReceiptImage($request->file('receipt_image')),
                'payment_method' => 'Bank Deposit/Transfer',
                'payment_status' => 'pending',
            ];
        });
        if ($result['status'] === 'success') {
            // a bank-transfer order is "placed" now: the customer gets their order number and details by email
            $this->emailOrderDetails($result['order']);
            return response()->json(['order_details' => $result['order'], 'message' => 'success'], 200);
        }
        return $this->orderNotPlacedResponse($result);
    }

    /**
     * Emails the customer their order details AFTER the response has gone out, so checkout is never held up
     * by (or fails because of) the mail server, and no queue worker is needed. The order is already
     * committed by the time this runs. Never throws — see OrderEmails.
     */
    private function emailOrderDetails($order)
    {
        $orderId = (int) $order->id;
        $done = false;   // terminating callbacks live as long as the app instance, so make each one fire exactly once
        app()->terminating(function () use ($orderId, &$done) {
            if (!$done) {
                $done = true;
                app(OrderEmails::class)->send($orderId);
            }
        });
    }

    /**
     * Places an order for an already-validated request. Shared by every
     * checkout path (bank transfer + receipt upload, Paystack).
     *
     *  - Serialised per order_uniq_id, so a double-submit (double click,
     *    scripted replay) can't create two orders for one cart.
     *  - The stock check, order creation and stock reservation all run in
     *    ONE transaction with the stock rows locked, so two customers can't
     *    both pass the check for the last units, and a failure part-way
     *    can't leave a half-created order or stranded reservation.
     *
     * $paymentDetails is called inside the transaction and returns
     * [receipt_image, payment_method, payment_status] — the only things that
     * differ between payment methods.
     *
     * @return array ['status' => busy|already|check_cart|success, ...]
     */
    private function placeOrder($request, array $cart, callable $paymentDetails)
    {
        $lock = $this->acquireSubmissionLock($request->order_uniq_id);
        if ($lock === false) {
            return ['status' => 'busy'];
        }
        try {
            $order_made = Order::where('order_uniq_id', $request->order_uniq_id)->first();
            if ($order_made) {
                return ['status' => 'already', 'order' => $order_made];
            }
            return DB::transaction(function () use ($request, $cart, $paymentDetails) {
                list($limited_stock, $details) = $this->checkStockBeforeOrdering($cart, true);
                if ($limited_stock) {
                    return ['status' => 'check_cart', 'details' => $details];
                }
                $user = $this->registerCustomer($request);
                $order = $this->finalizeOrder($request, $cart, $user, $paymentDetails());
                return ['status' => 'success', 'order' => $order];
            });
        } finally {
            if ($lock) {
                $lock->release();
            }
        }
    }

    /**
     * @return \Illuminate\Cache\Lock|false|null false when another request
     *         for the same order is already in flight; null when the cache
     *         driver can't lock (proceeds unserialised rather than failing).
     */
    private function acquireSubmissionLock($orderUniqId)
    {
        try {
            $lock = Cache::lock('order-submit:' . $orderUniqId, 30);
        } catch (\Throwable $e) {
            return null;
        }
        return $lock->get() ? $lock : false;
    }

    private function orderNotPlacedResponse(array $result)
    {
        switch ($result['status']) {
            case 'busy':
                return response()->json(['message' => 'Your order is already being processed. Please wait a moment.'], 409);
            case 'already':
                return response()->json(['message' => 'order_made_already', 'order_details' => $result['order']], 200);
            case 'check_cart':
                return response()->json(['details' => $result['details'], 'message' => 'check_cart'], 200);
        }
        return response()->json(['message' => 'Could not place order'], 500);
    }

    /**
     * Creates the order row, its line items and reserves the ordered stock.
     * Amount and total are always computed here from the cart — never taken
     * from the client.
     */
    private function finalizeOrder($request, array $cart, $user, $paymentDetails)
    {
        $order = new Order();
        $order->location = implode('/', $request->location) . '/';
        $order->user_id = $user->id;
        $order->order_uniq_id = $request->order_uniq_id;
        $order->receipt_image = $paymentDetails['receipt_image'] ?? null;
        $order->payment_method = $paymentDetails['payment_method'];
        $order->payment_status = $paymentDetails['payment_status'];
        $order->delivery_cost = $request->delivery_cost ?? 0;
        $order->amount = 0;
        $order->total = 0;
        $order->nearest_bustop = $request->nearest_bustop;
        $order->address = $request->address;
        $order->notes = $request->notes;
        $order->valid_till = date('Y-m-d H:i:s', strtotime('+504 hours')); // 21 days
        $order->save();
        $order->order_number = $this->getInvoiceNo('DS', $order->id);
        $order->save();
        $order = $this->createOrderItems($order, $cart);

        $this->reserveProduct($order->id);
        return $order;
    }

    /**
     * Starts a Paystack transaction for the customer's cart. Creates the
     * order the same way the manual bank-transfer path does (so stock is
     * reserved immediately either way), but with no receipt image and
     * payment_status left 'pending' — it only becomes 'paid' once
     * paystackCallback()/paystackWebhook() independently verify it below.
     */
    public function initializePaystackPayment(InitializePaystackPaymentRequest $request)
    {
        if (!$this->settingEnabled('can_make_order')) {
            return response()->json(['message' => 'Order placement is disabled for now'], 500);
        }
        // Server-side enforcement, not just a hidden UI tab — a request
        // straight to this endpoint must be rejected the same way while
        // online payments are switched off in settings.
        if (!$this->settingEnabled('online_payment_enabled')) {
            return response()->json(['message' => 'Online payments are currently unavailable. Please choose Bank Transfer at checkout.'], 500);
        }
        $result = $this->placeOrder($request, $request->validated()['cart_items'], function () {
            return [
                'receipt_image' => null,
                'payment_method' => 'Paystack',
                'payment_status' => 'pending',
            ];
        });
        if ($result['status'] !== 'success') {
            return $this->orderNotPlacedResponse($result);
        }

        $order = $result['order'];
        $order->payment_reference = $order->order_number;
        $order->save();

        // The order (and its stock reservation) already exists, so if the
        // gateway can't be reached or refuses, undo it straight away instead
        // of leaving stock locked until the stale-reservation job runs.
        try {
            $paystack = new PaystackService();
            $amountInKobo = (int) round($order->total * 100);
            $callbackUrl = url('/api/order/paystack/callback');
            $paystackResponse = $paystack->initializeTransaction($request->email, $amountInKobo, $order->payment_reference, $callbackUrl);
        } catch (\Throwable $e) {
            $this->cancelUnpaidOrder($order);
            return response()->json(['message' => 'Could not reach the payment provider. Please try again or use Bank Transfer.'], 500);
        }

        if (!($paystackResponse['status'] ?? false)) {
            $this->cancelUnpaidOrder($order);
            return response()->json(['message' => $paystackResponse['message'] ?? 'Could not start Paystack payment'], 500);
        }

        return response()->json([
            'message' => 'success',
            'authorization_url' => $paystackResponse['data']['authorization_url'],
            'order_details' => $order,
        ], 200);
    }

    /**
     * Cancels an order that never got as far as being payable and hands its
     * reserved stock back.
     */
    private function cancelUnpaidOrder($order)
    {
        $this->releasePendingUnpaidOrderQuantities($order);
        $order->order_status = 'Cancelled';
        $order->payment_status = 'cancelled';
        $order->save();
    }

    /**
     * Where Paystack redirects the customer's browser back to after they
     * complete (or abandon) payment on Paystack's hosted page. Independently
     * re-verifies the transaction server-side before trusting it — the
     * presence of a `reference` query param proves nothing on its own.
     */
    public function paystackCallback(Request $request)
    {
        $reference = $request->query('reference') ?? $request->query('trxref');
        $order = Order::where('payment_reference', $reference)->first();
        if (!$order) {
            return redirect(config('app.url') . '/track/order?payment=failed');
        }

        $this->confirmPaystackPayment($order, $reference);

        $order->refresh();
        $status = $order->payment_status === 'paid' ? 'success' : 'failed';
        $email = urlencode(optional($order->customer)->email ?? '');
        return redirect(config('app.url') . "/track/order?order_number={$order->order_number}&email={$email}&payment={$status}");
    }

    /**
     * Paystack's server-to-server notification — the authoritative
     * confirmation path (unlike the callback redirect above, this fires
     * even if the customer closes their browser before being redirected
     * back). Every payload must have its signature verified before any of
     * its contents are trusted, since the URL itself is publicly reachable.
     */
    public function paystackWebhook(Request $request)
    {
        $paystack = new PaystackService();
        $signature = $request->header('x-paystack-signature');
        if (!$paystack->verifyWebhookSignature($request->getContent(), $signature)) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payload = json_decode($request->getContent(), true);
        if (($payload['event'] ?? null) === 'charge.success') {
            $reference = $payload['data']['reference'] ?? null;
            $order = Order::where('payment_reference', $reference)->first();
            if ($order) {
                $this->confirmPaystackPayment($order, $reference);
            }
        }

        // Paystack expects a 200 to acknowledge receipt regardless of
        // outcome — anything else is treated as a delivery failure and
        // retried repeatedly.
        return response()->json(['message' => 'received'], 200);
    }

    /**
     * Independently re-verifies a Paystack transaction and marks the order
     * paid only if Paystack confirms success AND the paid amount matches
     * the order's own total — idempotent, since both the callback and the
     * webhook can each end up calling this for the same order.
     */
    private function confirmPaystackPayment($order, $reference)
    {
        if ($order->payment_status === 'paid') {
            return;
        }
        $paystack = new PaystackService();
        $verification = $paystack->verifyTransaction($reference);
        $data = $verification['data'] ?? [];
        $verifiedSuccess = ($data['status'] ?? null) === 'success';
        $amountMatches = isset($data['amount']) && (int) $data['amount'] === (int) round($order->total * 100);
        if ($verifiedSuccess && $amountMatches) {
            // atomic: the callback and the webhook can arrive together, and only the one that actually
            // flips the order to paid may send the "payment received" email
            $flipped = Order::whereKey($order->id)->where('payment_status', '!=', 'paid')->update(['payment_status' => 'paid']);
            $order->payment_status = 'paid';
            if ($flipped) {
                $this->emailOrderDetails($order);
            }
        }
    }

    /**
     * Sizes are the only pricing dimension — a stock's size-specific price
     * (if one was set by an admin) wins, otherwise the item's single base
     * price. Re-resolved server-side rather than trusting the cart's rate.
     */
    private function resolvePriceForStock($itemId, $stockId)
    {
        $size = $stockId ? optional(ItemStock::find($stockId))->size : null;
        $sizePrice = $size ? ItemSizePrice::where(['item_id' => $itemId, 'size' => $size])->first() : null;
        if ($sizePrice) {
            return $sizePrice->amount;
        }
        $item_price = ItemPrice::where('item_id', $itemId)->first();
        return $item_price ? $item_price->amount : 0;
    }

    private function calculateDiscountedAmount($itemId, $quantity, $stockId = null)
    {
        $amount = $this->resolvePriceForStock($itemId, $stockId);
        $item_discounts = ItemDiscount::where('item_id', $itemId)->orderBy('minimum_order_quantity')->get();
        if ($item_discounts->isNotEmpty()) {
            foreach ($item_discounts as $item_discount) {
                $moq = $item_discount->minimum_order_quantity;
                if ($quantity >= $moq) {
                    $amount = $item_discount->amount;
                }
            }
        }
        return $amount;
    }

    /**
     * Discount minimum-order-quantity tiers are per (item, size) — color is
     * cosmetic and never splits a discount bucket. Pools quantities across
     * every order line for the same item+size (e.g. two colors of the same
     * size) so the tier is decided on the combined amount, not each line's
     * own quantity in isolation.
     */
    /**
     * Builds the order's line items entirely from server-side data. The
     * client only ever says *which stock row* and *how many* — the item, its
     * name, its price and any discount tier come from the database, so a
     * crafted cart can't pair a cheap item's price with an expensive item's
     * stock row, or rename what's being bought.
     */
    private function createOrderItems($order, array $cart)
    {
        $stocks = ItemStock::with('item')->whereIn('id', array_column($cart, 'stock_id'))->get()->keyBy('id');

        // Discount tiers are per (item, size): pool quantities across lines
        // of the same item+size (e.g. two colours) so the tier is decided on
        // the combined amount.
        $groupedQuantities = [];
        foreach ($cart as $line) {
            $stock = $stocks[$line['stock_id']];
            $key = $stock->item_id . '_' . $stock->size;
            $groupedQuantities[$key] = ($groupedQuantities[$key] ?? 0) + (int) $line['quantity'];
        }

        $total = 0;
        foreach ($cart as $line) {
            $stock = $stocks[$line['stock_id']];
            $quantity = (int) $line['quantity'];
            $rate = $this->calculateDiscountedAmount($stock->item_id, $groupedQuantities[$stock->item_id . '_' . $stock->size], $stock->id);

            $order_item_obj = new OrderItem();
            $order_item_obj->order_id = $order->id;
            $order_item_obj->stock_id = $stock->id;
            $order_item_obj->item_id = $stock->item_id;
            $order_item_obj->product_name = implode(' - ', array_filter(
                [$stock->item->name, $stock->color, $stock->size],
                function ($part) {
                    return $part !== null && $part !== '';
                }
            ));

            $order_item_obj->quantity = $quantity;
            $order_item_obj->price = $rate;
            $order_item_obj->total = round($quantity * $rate, 2);
            $total += $order_item_obj->total;
            $order_item_obj->save();
        }
        $order->amount = $total;
        $order->total = $total;
        $order->save();
        return $order;
    }
    public function adminSearchOrder(Request $request)
    {
        $keyword = $request->search_query;
        $orderQuery = Order::query();
        $condition2 = [];
        if (isset($request->status) && $request->status != '') {
            ////// query by status //////////////
            $status = $request->status;
            $condition2 = ['order_status' => $status];
        }
        if ($keyword !== '') {
            $orderQuery->where(function ($q) use ($keyword) {
                $q->where('order_number', 'LIKE', '%' . $keyword . '%');
                $q->orWhere(function ($q2) use ($keyword) {
                    $q2->whereHas('customer', function ($q3) use ($keyword) {
                        $q3->where('name', 'LIKE', '%' . $keyword . '%');
                        $q3->orWhere('email', 'LIKE', '%' . $keyword . '%');
                        $q3->orWhere('phone', 'LIKE', '%' . $keyword . '%');
                    });
                });
            });
        }
        $orders = $orderQuery->where($condition2)->with('customer', 'orderItems.item', 'orderItems.stock')
            ->orderBy('id', 'DESC')
            ->limit(100) // a search never needs to return the whole table
            ->get();
        return response()->json(compact('orders'), 200);
    }
    /**
     * Guest order tracking: an order is only returned to someone who knows
     * both its number and the email/phone of the customer it belongs to.
     * Matched on the order itself (not "the first user with this phone"), so
     * customers who happen to share a phone number can still find their own
     * orders. Rate-limited in routes/api.php to make guessing impractical.
     */
    public function search(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string', 'max:190'],
            'order_number' => ['required', 'string', 'max:50'],
        ]);
        $username = $request->username;
        $message = 'failed';
        $order = Order::with('customer', 'orderItems.item')
            ->where('order_number', $request->order_number)
            ->whereHas('customer', function ($q) use ($username) {
                $q->where('email', $username)->orWhere('phone', $username);
            })
            ->first();
        if ($order) {
            $message = 'success';
            return response()->json(compact('message', 'order'), 200);
        }
        return response()->json(compact('message'), 200);
    }
    public function myOrders()
    {
        //
        $user = $this->getUser();
        $orders = Order::with(['customer', 'orderItems.item'])->where('user_id', $user->id)->orderBy('id', 'DESC')->paginate(10);
        return response()->json(compact('orders'));
    }
    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Order\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function show(Order $order)
    {
        // A signed-in customer may only open their own orders; staff need the
        // order-viewing permission (admins pass every permission check).
        $viewer = Auth::user();
        if ((int) $order->user_id !== (int) $viewer->id && !$viewer->can('view order')) {
            return response()->json(['message' => 'You are not allowed to view this order'], 403);
        }
        $order = $order->with([
            'customer',
            'orderItems.item',
            'orderItems.stock',
        ])->find($order->id);
        return response()->json(compact('order'), 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Order\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function changeOrderStatus(ChangeOrderStatusRequest $request, Order $order)
    {
        $user = $this->getUser();
        $status = $request->status;
        $paymentJustMade = false;

        // Serialised on the order row. Stock only moves on the first transition
        // out of Pending/CARP: previously every "On Transit" call moved another
        // batch of reserved stock to sold (taking other orders' reservations),
        // and cancelling an already-delivered order released stock that had
        // already been sold.
        $error = DB::transaction(function () use ($request, $order, $status, &$paymentJustMade) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $current = $order->order_status;
            $open = in_array($current, ['Pending', 'CARP'], true);

            if ($current === 'Cancelled' && $status !== 'Cancelled') {
                return 'A cancelled order can only be restored with "Reverse cancellation"';
            }
            if ($current === 'Delivered' && $status === 'Cancelled') {
                return 'A delivered order cannot be cancelled';
            }

            if ($open && in_array($status, ['On Transit', 'Delivered'], true)) {
                $this->sellOutFromStock($order->id);
            }
            if ($open && $status === 'Cancelled') {
                $this->releasePendingUnpaidOrderQuantities($order);
            }

            if ($status === 'On Transit' || $status === 'Delivered') {
                $order->order_status = 'Delivered';
            } else {
                $order->order_status = $status;
            }
            // Only touch the payment status when one was sent (omitting it used to null it out).
            if ($request->filled('payment_status')) {
                $paymentJustMade = $request->payment_status === 'paid' && $order->payment_status !== 'paid';
                $order->payment_status = $request->payment_status;
            }
            $order->save();
            return null;
        });

        if ($error) {
            return response()->json(['message' => $error], 422);
        }

        $order->refresh();
        if ($paymentJustMade) {
            $description = "Order ($order->order_number) has been paid for. Payment logged by: $user->name ($user->email)";
            $title = "Order Payment Made";
            $this->logUserActivity($title, $description);
        }
        $description = "Order ($order->order_number) status changed to " . strtoupper($order->order_status) . " by: $user->name ($user->email)";
        $title = "Order Status Updated";
        $this->logUserActivity($title, $description);
        return response()->json(['message' => 'success'], 200);
    }
    private function releasePendingUnpaidOrderQuantities($order)
    {
        $orderItems = $order->orderItems;
        foreach ($orderItems as $orderItem) {
            $stock = $orderItem->stock;
            $order_quantity = $orderItem->quantity;
            $reserved = $stock->reserved;

            if ($reserved >= $order_quantity) {
                $stock->reserved -= $order_quantity;
                $stock->save();
            }
        }
    }
    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Order\Order  $order
     * @return \Illuminate\Http\Response
     */
    /**
     * Reserves the order's quantities against their stock rows. Runs inside
     * placeOrder()'s transaction, after the (row-locked) availability check,
     * so availability is already guaranteed — an atomic increment is used
     * rather than the old "reserve only if it still fits, otherwise silently
     * skip", which could leave an order with no stock actually held for it.
     */
    private function reserveProduct($orderId)
    {
        $orderItems = OrderItem::where('order_id', $orderId)->get();
        foreach ($orderItems->groupBy('stock_id') as $stockId => $lines) {
            ItemStock::where('id', $stockId)->increment('reserved', (int) $lines->sum('quantity'));
        }
    }
    private function sellOutFromStock($orderId)
    {
        $orderItems = OrderItem::with('stock')->where('order_id', $orderId)->get();
        foreach ($orderItems as $orderItem) {
            $stock = $orderItem->stock;
            $order_quantity = $orderItem->quantity;
            $reserved = $stock->reserved;

            if ($reserved >= $order_quantity) {
                $stock->reserved -= $order_quantity;
                $stock->sold += $order_quantity;
                $stock->save();
                // the goods have left the shelf: freeze what they cost (FIFO) on this order line
                // (no-op until product costing is switched on; runs inside the caller's transaction)
                app(Costing::class)->costLine($orderItem);
            }
        }
    }

    /**
     * Restores a cancelled order (route requires the cancel/approve order
     * permission). Only a cancelled order can be reversed — and once it is,
     * its status changes, so the same call can't be repeated to keep
     * inflating reserved stock.
     */
    public function reverseOrderCancellation(Request $request, Order $order)
    {
        if ($order->order_status !== 'Cancelled') {
            return response()->json(['message' => 'Only a cancelled order can be reversed'], 422);
        }
        DB::transaction(function () use ($order) {
            $this->reserveOrderQuantities($order->orderItems);
            $this->reverseOrderStatus($order);
        });
        return response()->json(['message' => 'success'], 200);
    }
    private function reserveOrderQuantities($orderItems)
    {
        // $orderItems = $order->orderItems;
        foreach ($orderItems as $orderItem) {
            $stock = $orderItem->stock;
            $order_quantity = $orderItem->quantity;
            // $reserved = $stock->reserved;
            $stock_balance = $stock->quantity_stocked - $stock->reserved - $stock->sold;

            if ($stock_balance >= $order_quantity) {
                $stock->reserved += $order_quantity;
            }
            $stock->cancelled_quantity_reserved += $order_quantity;
            $stock->save();
        }
    }
    private function reverseOrderStatus($order)
    {
        $order->order_status = 'CARP';
        $order->payment_status = 'carp';
        $order->cancelled_status_reversed = 1;
        $order->save();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Order\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function destroy(Order $order)
    {
        //
    }
}
