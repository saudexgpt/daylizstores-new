<?php

namespace Tests\Feature\Order;

use App\Laravue\Models\User;
use App\Mail\CustomerCredentials;
use App\Mail\OrderDetails;
use App\Mail\ResetPassword;
use App\Models\Order\Order;
use App\Models\Stock\Category;
use App\Models\Stock\Item;
use App\Models\Stock\ItemPrice;
use App\Models\Stock\ItemStock;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * What went wrong on the live server: a slow mail server held the order request open (inside its transaction),
 * the customer clicked again, and a second order — with no email — was created.
 *
 *   1. one checkout attempt can never become two orders, whatever the timing (a unique index, not a lock)
 *   2. checkout never talks to the mail server: every email is a queued job, encrypted where it carries a secret
 */
class OrderQueueAndIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function stockedItem(): array
    {
        $item = Item::factory()->create(['category_id' => Category::factory()->create()->id, 'name' => 'Runner']);
        $price = new ItemPrice();
        $price->item_id = $item->id;
        $price->amount = 1000;
        $price->save();
        $stock = new ItemStock();
        $stock->item_id = $item->id;
        $stock->color = 'Black';
        $stock->size = '42';
        $stock->quantity_stocked = 50;
        $stock->save();

        return [$item, $stock];
    }

    private function payload($item, $stock, string $uuid): array
    {
        return [
            'order_uniq_id' => $uuid, 'email' => 'guest@example.com', 'name' => 'Ada Obi', 'phone' => '08033334444',
            'address' => '1 Test Street', 'nearest_bustop' => 'Test Bustop', 'notes' => '', 'location' => ['Lagos'],
            'delivery_cost' => 0, 'amount' => 2000, 'total' => 2000, 'receipt_image' => UploadedFile::fake()->image('receipt.jpg'),
            'cart_items' => [['id' => $item->id, 'stock_id' => $stock->id, 'name' => $item->name, 'quantity' => 2, 'rate' => 1000]],
        ];
    }

    // ------------------------------------------------------------------ one attempt, one order

    public function testTheDatabaseRefusesASecondOrderWithTheSameCheckoutId()
    {
        $uuid = (string) Str::uuid();
        $first = Order::factory()->create();
        DB::table('orders')->where('id', $first->id)->update(['order_uniq_id' => $uuid]);
        $second = Order::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('orders')->where('id', $second->id)->update(['order_uniq_id' => $uuid]);
    }

    public function testOldOrdersWithoutACheckoutIdAreUnaffected()
    {
        // 44,000 historical orders have no id at all: any number of NULLs must be allowed
        $a = Order::factory()->create();
        $b = Order::factory()->create();
        DB::table('orders')->whereIn('id', [$a->id, $b->id])->update(['order_uniq_id' => null]);

        $this->assertSame(2, DB::table('orders')->whereNull('order_uniq_id')->whereIn('id', [$a->id, $b->id])->count());
        $indexes = collect(Schema::getIndexes('orders'))->filter(fn ($i) => $i['columns'] === ['order_uniq_id']);
        $this->assertCount(1, $indexes, 'one index on the column, and it is the unique one');
        $this->assertTrue($indexes->first()['unique']);
    }

    public function testARetryThatSlipsPastTheFirstCheckStillGetsTheExistingOrderNotASecond()
    {
        $this->seedBaselineSettings();
        Storage::fake('public');
        [$item, $stock] = $this->stockedItem();
        $uuid = (string) Str::uuid();

        // Simulate the live race: the request looks for an existing order, finds none — and, before it inserts its
        // own, the OTHER request for the same checkout attempt commits its order.
        $fired = false;
        DB::listen(function ($query) use (&$fired, $uuid) {
            if (!$fired && str_contains($query->sql, 'from `orders`') && str_contains($query->sql, '`order_uniq_id` =') && ($query->bindings[0] ?? null) === $uuid) {
                $fired = true;
                $winner = Order::factory()->create();
                DB::table('orders')->where('id', $winner->id)->update(['order_uniq_id' => $uuid]);
            }
        });
        Mail::fake();

        $response = $this->postJson('/api/order/store', $this->payload($item, $stock, $uuid));

        $this->assertTrue($fired, 'the race was simulated');
        $response->assertStatus(200)->assertJsonFragment(['message' => 'order_made_already']);
        $this->assertSame(1, Order::where('order_uniq_id', $uuid)->count(), 'still exactly one order for this checkout attempt');
        $this->assertSame(0, (int) $stock->fresh()->reserved, 'the losing request reserved nothing');
        $this->assertSame(0, User::where('email', 'guest@example.com')->count(), 'and left no stray customer behind');
        Mail::assertNotQueued(OrderDetails::class);        // the winning request sends its own
        Mail::assertNotQueued(CustomerCredentials::class);
    }

    public function testAnOtherUniqueClashIsNotSwallowed()
    {
        // only a clash on the checkout id means "already placed"; anything else must still be an error
        $this->seedBaselineSettings();
        Storage::fake('public');
        [$item, $stock] = $this->stockedItem();
        $existing = Order::factory()->create();
        DB::table('orders')->where('id', $existing->id)->update(['order_number' => 'DS777777']);

        Order::creating(function (Order $order) {
            if ($order->order_uniq_id !== null) {
                throw new UniqueConstraintViolationException('mysql', 'insert', [], new \PDOException('Duplicate entry for orders_order_number_unique'));
            }
        });

        $this->postJson('/api/order/store', $this->payload($item, $stock, (string) Str::uuid()))->assertStatus(500);
        $this->assertSame(0, (int) $stock->fresh()->reserved);
    }

    // ------------------------------------------------------------------ mail: queued, never inline

    private function pointMailAtAServerThatIsDownAndUseTheDatabaseQueue(): void
    {
        config(['queue.default' => 'database', 'mail.driver' => 'smtp', 'mail.host' => '127.0.0.1', 'mail.port' => 1, 'mail.timeout' => 1]);
        app('mail.manager')->purge();
    }

    public function testCheckoutOnlyQueuesItsEmailsAndNeverTouchesTheMailServer()
    {
        $this->seedBaselineSettings();
        Storage::fake('public');
        [$item, $stock] = $this->stockedItem();
        $this->pointMailAtAServerThatIsDownAndUseTheDatabaseQueue();

        $response = $this->postJson('/api/order/store', $this->payload($item, $stock, (string) Str::uuid()));

        $response->assertStatus(200)->assertJsonFragment(['message' => 'success']);
        $this->assertSame(1, Order::count());
        $jobs = DB::table('jobs')->pluck('payload');
        $this->assertCount(2, $jobs, 'the login details and the order details are waiting in the queue, not sent inline');
        $this->assertSame(1, $jobs->filter(fn ($p) => str_contains($p, 'OrderDetails'))->count());
    }

    public function testTheTemporaryPasswordIsEncryptedInTheQueueTable()
    {
        $this->seedBaselineSettings();
        Storage::fake('public');
        [$item, $stock] = $this->stockedItem();
        $this->pointMailAtAServerThatIsDownAndUseTheDatabaseQueue();

        $this->postJson('/api/order/store', $this->payload($item, $stock, (string) Str::uuid()))->assertStatus(200);

        $commands = DB::table('jobs')->pluck('payload')->map(fn ($p) => json_decode($p, true));
        $credentials = $commands->firstWhere('displayName', CustomerCredentials::class);
        $this->assertNotNull($credentials, 'the login details are waiting in the queue');

        // the job's command (which holds the password) is encrypted: not a readable PHP-serialised object
        $command = $credentials['data']['command'];
        $this->assertFalse(str_starts_with($command, 'O:'), 'not stored as readable serialised data');
        $this->assertStringNotContainsString('password', $command);
        // ...but the worker, which has the app key, can still open it
        $this->assertStringContainsString('s:8:"password"', \Illuminate\Support\Facades\Crypt::decrypt($command));

        // the order details hold no secret, so they are not encrypted
        $this->assertTrue(str_starts_with($commands->firstWhere('displayName', OrderDetails::class)['data']['command'], 'O:'));
        $this->assertSame('default', User::where('email', 'guest@example.com')->firstOrFail()->password_status);
    }

    public function testThePasswordResetEmailIsQueuedAndItsTokenEncrypted()
    {
        $user = User::factory()->customer()->create(['email' => 'reset@example.com']);
        $this->pointMailAtAServerThatIsDownAndUseTheDatabaseQueue();

        $this->postJson('/api/auth/recover-password', ['email' => 'reset@example.com'])->assertStatus(200);

        $token = DB::table('password_resets')->where('email', 'reset@example.com')->value('token');
        $this->assertNotEmpty($token);
        $payloads = DB::table('jobs')->pluck('payload');
        $this->assertCount(1, $payloads);
        $this->assertStringNotContainsString($token, $payloads->first(), 'the reset code is not stored in the clear');
        $this->assertNotNull($user);
    }

    public function testEveryEmailIsQueuedWithRetriesAndSecretsAreEncrypted()
    {
        foreach ([new OrderDetails(null, null, null), new CustomerCredentials(null, 'x'), new ResetPassword(null, 'x')] as $mailable) {
            $class = get_class($mailable);
            $this->assertInstanceOf(ShouldQueue::class, $mailable, "$class must be queued");
            $this->assertGreaterThan(1, $mailable->tries, "$class is retried");
            $this->assertNotEmpty($mailable->backoff, "$class backs off between retries");
            $this->assertTrue($mailable->afterCommit, "$class is only queued once the surrounding transaction has committed");
            $this->assertLessThanOrEqual(60, $mailable->timeout);
        }
        $this->assertInstanceOf(ShouldBeEncrypted::class, new CustomerCredentials(null, 'x'));
        $this->assertInstanceOf(ShouldBeEncrypted::class, new ResetPassword(null, 'x'));
    }

    public function testTheMailServerGivesUpQuicklyInsteadOfHanging()
    {
        $this->assertLessThanOrEqual(30, (int) config('mail.timeout'), 'an unreachable SMTP port must not hold a worker for minutes');
        $this->assertGreaterThan(0, (int) config('mail.timeout'));
    }
}
