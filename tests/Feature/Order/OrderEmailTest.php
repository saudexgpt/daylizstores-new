<?php

namespace Tests\Feature\Order;

use App\Mail\CustomerCredentials;
use App\Mail\OrderDetails;
use App\Models\Order\Order;
use App\Models\Stock\Category;
use App\Models\Stock\Item;
use App\Models\Stock\ItemPrice;
use App\Models\Stock\ItemStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The customer gets their order details by email when an order is placed (bank transfer) or paid (card).
 */
class OrderEmailTest extends TestCase
{
    use RefreshDatabase;

    private function makeItemWithStock(int $quantity = 50): array
    {
        $item = Item::factory()->create(['category_id' => Category::factory()->create()->id, 'name' => 'Air Force Black']);
        $price = new ItemPrice();
        $price->item_id = $item->id;
        $price->amount = 1000;
        $price->save();
        $stock = new ItemStock();
        $stock->item_id = $item->id;
        $stock->color = 'Black';
        $stock->size = '42';
        $stock->quantity_stocked = $quantity;
        $stock->save();

        return [$item, $stock];
    }

    private function payload($item, $stock, array $overrides = []): array
    {
        return array_merge([
            'order_uniq_id' => (string) Str::uuid(),
            'email' => 'guest@example.com',
            'name' => 'Ada Obi',
            'phone' => '08033334444',
            'address' => '1 Test Street, Ikeja',
            'nearest_bustop' => 'Test Bustop',
            'notes' => 'Call before delivery',
            'location' => ['Lagos', 'Ikeja'],
            'delivery_cost' => 500,
            'amount' => 2000,
            'total' => 2500,
            'receipt_image' => UploadedFile::fake()->image('receipt.jpg'),
            'cart_items' => [['id' => $item->id, 'stock_id' => $stock->id, 'name' => $item->name, 'quantity' => 2, 'rate' => 1000]],
        ], $overrides);
    }

    private function placeBankOrder(array $overrides = [], int $stockQty = 50): array
    {
        $this->seedBaselineSettings();
        Storage::fake('public');
        [$item, $stock] = $this->makeItemWithStock($stockQty);
        $payload = $this->payload($item, $stock, $overrides);
        $response = $this->postJson('/api/order/store', $payload);

        return [$response, $payload, $item, $stock];
    }

    // ------------------------------------------------------------------ bank transfer

    public function testACustomerIsEmailedTheirOrderDetailsWhenABankTransferOrderIsPlaced()
    {
        Mail::fake();
        [$response, $payload] = $this->placeBankOrder();
        $response->assertStatus(200)->assertJsonFragment(['message' => 'success']);
        $order = Order::where('order_uniq_id', $payload['order_uniq_id'])->firstOrFail();

        Mail::assertSent(OrderDetails::class, 1);
        Mail::assertSent(OrderDetails::class, function (OrderDetails $mail) use ($order) {
            return $mail->hasTo('guest@example.com') && $mail->order->id === $order->id;
        });
    }

    public function testTheEmailCarriesTheOrderNumberItemsTotalsAndDeliveryDetails()
    {
        Mail::fake();
        [, $payload] = $this->placeBankOrder();
        $order = Order::where('order_uniq_id', $payload['order_uniq_id'])->firstOrFail();

        Mail::assertSent(OrderDetails::class, function (OrderDetails $mail) use ($order) {
            $html = $mail->render();
            $this->assertMatchesRegularExpression('/^DS/', $order->order_number);
            foreach ([
                $order->order_number,                       // the number that was just generated
                'Air Force Black - Black - 42',             // what was ordered: product - colour - size
                '₦1,000.00', '₦2,000.00',                   // unit price, line total (2 × 1,000)
                '₦2,000.00',                                // total: items only — no delivery is charged on this order
                'Ada Obi', '08033334444', '1 Test Street, Ikeja', 'Test Bustop', 'Lagos, Ikeja', 'Call before delivery',
                'Bank Deposit/Transfer',
                'Awaiting payment confirmation',
                '/track/order?order_number=' . $order->order_number . '&amp;email=guest%40example.com',
            ] as $expected) {
                $this->assertStringContainsString($expected, $html, "the email should contain: $expected");
            }
            $this->assertStringNotContainsString('Payment confirmed', $html, 'a bank transfer is not yet paid');
            $this->assertStringNotContainsString('Delivery</td>', $html, 'no delivery line when none is charged, so the lines always add up');
            $this->assertStringContainsString($order->order_number, $mail->subject);

            return true;
        });
    }

    public function testADeliveryLineAppearsOnlyWhenDeliveryIsPartOfTheTotal()
    {
        [, $payload] = $this->placeBankOrder();
        $order = Order::where('order_uniq_id', $payload['order_uniq_id'])->firstOrFail();
        $order->total = 2500;   // items 2,000 + delivery 500 actually charged
        $order->save();

        $html = (new OrderDetails($order->customer, $order->fresh(), $order->orderItems))->render();
        $this->assertStringContainsString('Delivery</td>', $html);
        $this->assertStringContainsString('₦500.00', $html);
        $this->assertStringContainsString('₦2,500.00', $html);
    }

    public function testCustomerInputIsEscapedInTheEmail()
    {
        Mail::fake();
        [, $payload] = $this->placeBankOrder(['address' => '<script>alert(1)</script>', 'notes' => '<b>bold</b>', 'name' => 'Eve <i>Hacker</i>']);

        Mail::assertSent(OrderDetails::class, function (OrderDetails $mail) {
            $html = $mail->render();
            $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
            $this->assertStringNotContainsString('<b>bold</b>', $html);
            $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);

            return true;
        });
    }

    public function testAnExistingCustomerIsEmailedToo()
    {
        Mail::fake();
        $this->seedBaselineSettings();
        Storage::fake('public');
        [$item, $stock] = $this->makeItemWithStock();
        $this->postJson('/api/order/store', $this->payload($item, $stock))->assertStatus(200);
        $this->postJson('/api/order/store', $this->payload($item, $stock))->assertStatus(200);   // same email, a second order

        Mail::assertSent(OrderDetails::class, 2);
        // the login details go out only once: the account already existed the second time (that mail is queued, so it is counted as queued)
        $this->assertSame(1, Mail::sent(CustomerCredentials::class)->count() + Mail::queued(CustomerCredentials::class)->count());
    }

    public function testAResubmittedOrderIsNotEmailedTwice()
    {
        Mail::fake();
        $this->seedBaselineSettings();
        Storage::fake('public');
        [$item, $stock] = $this->makeItemWithStock();
        $payload = $this->payload($item, $stock);

        $this->postJson('/api/order/store', $payload)->assertStatus(200);
        $this->postJson('/api/order/store', $payload)->assertJsonFragment(['message' => 'order_made_already']);

        Mail::assertSent(OrderDetails::class, 1);
        $this->assertSame(1, Order::where('order_uniq_id', $payload['order_uniq_id'])->count());
    }

    public function testNoEmailWhenTheOrderCouldNotBePlaced()
    {
        Mail::fake();
        [$response] = $this->placeBankOrder([], 1);   // one in stock, two ordered

        $response->assertJsonFragment(['message' => 'check_cart']);
        Mail::assertNotSent(OrderDetails::class);
        $this->assertSame(0, Order::count());
    }

    public function testAMailServerFailureNeverBreaksTheOrder()
    {
        $this->seedBaselineSettings();
        Storage::fake('public');
        [$item, $stock] = $this->makeItemWithStock();
        $payload = $this->payload($item, $stock);
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP server unavailable'));

        $this->postJson('/api/order/store', $payload)->assertStatus(200)->assertJsonFragment(['message' => 'success']);

        $this->assertDatabaseHas('orders', ['order_uniq_id' => $payload['order_uniq_id']]);
        $this->assertDatabaseHas('order_items', ['stock_id' => $stock->id, 'quantity' => 2]);
    }

    public function testAnOrderForACustomerWithoutAUsableEmailIsSkippedQuietly()
    {
        Mail::fake();
        [, $payload] = $this->placeBankOrder();
        $order = Order::where('order_uniq_id', $payload['order_uniq_id'])->firstOrFail();
        $order->customer->email = 'not-an-email';
        $order->customer->save();

        $this->assertFalse(app(\App\Services\Orders\OrderEmails::class)->send($order->id));
        Mail::assertSent(OrderDetails::class, 1);   // still only the original one
    }

    // ------------------------------------------------------------------ card payments (Paystack)

    private function initializePaystackOrder(): array
    {
        $this->seedBaselineSettings();
        [$item, $stock] = $this->makeItemWithStock();
        Http::fake(['api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/abc']], 200)]);
        $payload = $this->payload($item, $stock, ['receipt_image' => null, 'email' => 'card@example.com']);
        unset($payload['receipt_image']);
        $this->postJson('/api/order/paystack/initialize', $payload)->assertStatus(200);

        return [Order::where('order_uniq_id', $payload['order_uniq_id'])->firstOrFail(), $payload];
    }

    private function paystackConfirms(Order $order): void
    {
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => ['status' => 'success', 'amount' => (int) round($order->total * 100)]], 200)]);
    }

    public function testACardOrderIsEmailedOnlyOnceThePaymentIsConfirmedAndNotBeforeAndNotTwice()
    {
        Mail::fake();
        [$order] = $this->initializePaystackOrder();
        Mail::assertNotSent(OrderDetails::class);   // an unpaid card order is not "placed" yet

        $this->paystackConfirms($order);
        $this->get('/api/order/paystack/callback?reference=' . $order->payment_reference)->assertRedirect();
        Mail::assertSent(OrderDetails::class, 1);
        Mail::assertSent(OrderDetails::class, function (OrderDetails $mail) {
            $html = $mail->render();
            $this->assertStringContainsString('Payment confirmed', $html);
            $this->assertStringNotContainsString('Awaiting payment confirmation', $html);
            $this->assertStringContainsString('Payment received for order', $mail->subject);

            return $mail->hasTo('card@example.com');
        });

        // the browser callback and the webhook can both fire for one payment: still one email
        $this->get('/api/order/paystack/callback?reference=' . $order->payment_reference);
        $this->assertSame('paid', $order->fresh()->payment_status);
        Mail::assertSent(OrderDetails::class, 1);
    }

    public function testNoEmailWhenPaystackDoesNotConfirmThePayment()
    {
        Mail::fake();
        [$order] = $this->initializePaystackOrder();
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => ['status' => 'failed', 'amount' => 0]], 200)]);

        $this->get('/api/order/paystack/callback?reference=' . $order->payment_reference);

        $this->assertSame('pending', $order->fresh()->payment_status);
        Mail::assertNotSent(OrderDetails::class);
    }
}
