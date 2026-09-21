<?php

namespace Tests\Feature\Order;

use App\Models\Setting\Setting;
use App\Models\Stock\Category;
use App\Models\Stock\Item;
use App\Models\Stock\ItemPrice;
use App\Models\Stock\ItemStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The cart page lets a customer raise and lower quantities. The server tells it how many can be bought
 * (stock_levels) so the controls stop there — and, whatever the browser does, an order for more than is
 * available is still refused when it is placed.
 */
class CartStockLevelsTest extends TestCase
{
    use RefreshDatabase;

    private function stocked(int $quantity, int $reserved = 0, int $sold = 0, bool $enabled = true, string $size = 'M'): ItemStock
    {
        $item = Item::factory()->create(['category_id' => Category::factory()->create()->id, 'enabled' => $enabled]);
        $price = new ItemPrice();
        $price->item_id = $item->id;
        $price->amount = 1000;
        $price->save();
        $stock = new ItemStock();
        $stock->item_id = $item->id;
        $stock->color = 'Black';
        $stock->size = $size;
        $stock->quantity_stocked = $quantity;
        $stock->reserved = $reserved;
        $stock->sold = $sold;
        $stock->save();

        return $stock;
    }

    private function line(ItemStock $stock, int $quantity): array
    {
        return ['id' => $stock->item_id, 'stock_id' => $stock->id, 'name' => 'Item ' . $stock->id, 'quantity' => $quantity, 'rate' => 1000];
    }

    // ------------------------------------------------------------------ what the cart is told

    public function testValidateCartReportsWhatIsAvailableForEveryLineNotJustTheOnesThatAreShort()
    {
        $plenty = $this->stocked(50);
        $some = $this->stocked(10, reserved: 3, sold: 2);   // 5 can be bought: others have reserved 3, 2 are sold
        $gone = $this->stocked(4, sold: 4);
        $off = $this->stocked(20, enabled: false);           // a disabled product cannot be bought at all

        $r = $this->postJson('/api/order/validate-cart', ['cart_items' => [
            $this->line($plenty, 1), $this->line($some, 2), $this->line($gone, 1), $this->line($off, 1),
        ]])->assertStatus(200);

        $levels = $r->json('stock_levels');
        $this->assertSame(50, $levels[(string) $plenty->id]);
        $this->assertSame(5, $levels[(string) $some->id], 'stocked − reserved − sold');
        $this->assertSame(0, $levels[(string) $gone->id]);
        $this->assertSame(0, $levels[(string) $off->id], 'a disabled product reports nothing available');
        // the existing verdict is unchanged: only the sold-out and disabled lines are flagged
        $this->assertTrue($r->json('limited_stock'));
        $this->assertEqualsCanonicalizing([$gone->id, $off->id], array_column(array_column($r->json('details'), 'updated_item'), 'stock_id'));
    }

    public function testAnEmptyCartStillGetsAnObjectNotAList()
    {
        // (a missing stock row is "0 available", never an error)
        $r = $this->postJson('/api/order/validate-cart', ['cart_items' => [['id' => 99999, 'stock_id' => 99999, 'name' => 'Ghost', 'quantity' => 1, 'rate' => 10]]])->assertStatus(200);
        $this->assertSame(0, $r->json('stock_levels.99999'));
    }

    public function testTheLevelIsPerStockRowSoTwoSizesAreCountedSeparately()
    {
        $small = $this->stocked(3, size: 'S');
        $large = $this->stocked(9, size: 'L');

        $levels = $this->postJson('/api/order/validate-cart', ['cart_items' => [$this->line($small, 1), $this->line($large, 1)]])->json('stock_levels');

        $this->assertSame(3, $levels[(string) $small->id]);
        $this->assertSame(9, $levels[(string) $large->id]);
    }

    public function testStockLevelsFollowReservationsMadeByOtherCustomers()
    {
        $this->seedBaselineSettings();
        Storage::fake('public');
        Mail::fake();
        $stock = $this->stocked(10);
        $cart = [$this->line($stock, 1)];
        $this->assertSame(10, $this->postJson('/api/order/validate-cart', ['cart_items' => $cart])->json('stock_levels.' . $stock->id));

        // someone else places an order for 4: it is reserved straight away
        $this->postJson('/api/order/store', $this->orderPayload($stock, 4))->assertStatus(200)->assertJsonFragment(['message' => 'success']);

        $this->assertSame(6, $this->postJson('/api/order/validate-cart', ['cart_items' => $cart])->json('stock_levels.' . $stock->id));
    }

    // ------------------------------------------------------------------ the server still has the last word

    private function orderPayload(ItemStock $stock, int $quantity, array $overrides = []): array
    {
        return array_merge([
            'order_uniq_id' => (string) Str::uuid(), 'email' => 'guest' . Str::random(4) . '@example.com', 'name' => 'Ada Obi', 'phone' => '08033334444',
            'address' => '1 Test Street', 'nearest_bustop' => 'Test Bustop', 'notes' => '', 'location' => ['Lagos'],
            'delivery_cost' => 0, 'amount' => 1000 * $quantity, 'total' => 1000 * $quantity,
            'receipt_image' => UploadedFile::fake()->image('receipt.jpg'),
            'cart_items' => [$this->line($stock, $quantity)],
        ], $overrides);
    }

    public function testAnOrderForMoreThanIsAvailableIsRefusedWhateverTheCartPageAllowed()
    {
        $this->seedBaselineSettings();
        Storage::fake('public');
        Mail::fake();
        $stock = $this->stocked(5);

        // a tampered or stale browser asks for 6 when 5 exist
        $response = $this->postJson('/api/order/store', $this->orderPayload($stock, 6));

        $response->assertStatus(200)->assertJsonFragment(['message' => 'check_cart']);
        $this->assertSame(5, $response->json('details.0.balance'), 'and is told what is really left');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(0, (int) $stock->fresh()->reserved, 'nothing was reserved');
    }

    public function testTheLastUnitsCanBeBoughtButNotOneMore()
    {
        $this->seedBaselineSettings();
        Storage::fake('public');
        Mail::fake();
        $stock = $this->stocked(5);

        $this->postJson('/api/order/store', $this->orderPayload($stock, 5))->assertStatus(200)->assertJsonFragment(['message' => 'success']);
        $this->assertSame(5, (int) $stock->fresh()->reserved);

        $this->postJson('/api/order/store', $this->orderPayload($stock, 1))->assertJsonFragment(['message' => 'check_cart']);
        $this->assertDatabaseCount('orders', 1);
    }

    public function testCraftedQuantitiesAreRejectedBeforeTheyReachTheStockCheck()
    {
        $stock = $this->stocked(5);
        foreach ([0, -3, 1.5, 'lots', null] as $bad) {
            $this->postJson('/api/order/validate-cart', ['cart_items' => [array_merge($this->line($stock, 1), ['quantity' => $bad])]])
                ->assertStatus(422);
        }
    }

    public function testTwoLinesForTheSameStockAreRefusedSoTheyCannotAddUpToMoreThanExists()
    {
        $this->seedBaselineSettings();
        Storage::fake('public');
        $stock = $this->stocked(5);
        $split = [$this->line($stock, 3), $this->line($stock, 3)];   // 6 across two lines, when 5 exist

        $this->postJson('/api/order/validate-cart', ['cart_items' => $split])->assertStatus(422);
        $this->postJson('/api/order/store', $this->orderPayload($stock, 3, ['cart_items' => $split]))->assertStatus(422);
        $this->assertDatabaseCount('orders', 0);
    }

    // ------------------------------------------------------------------ the pickup note

    public function testThePickupNoteMigrationRewritesOnlyTheOldPhraseAndOnlyOnce()
    {
        $migration = require database_path('migrations/2026_09_23_100000_pickup_note_now_includes_tuesdays.php');
        $note = "Goods left unpicked after 7 days is at owner's risk. Also we want to state that there will be NO PICKUPS ON THURSDAYS. Thank you.";
        $setting = new Setting();
        $setting->key = 'pickup_warning';
        $setting->value = $note;
        $setting->save();

        $migration->up();
        $updated = Setting::where('key', 'pickup_warning')->value('value');
        $this->assertSame(str_replace('NO PICKUPS ON THURSDAYS', 'NO PICKUPS ON TUESDAYS & THURSDAYS', $note), $updated);
        $this->assertStringNotContainsString('ON THURSDAYS', str_replace('TUESDAYS & THURSDAYS', '', $updated));

        $migration->up();   // running it again is a no-op
        $this->assertSame($updated, Setting::where('key', 'pickup_warning')->value('value'));

        // ...and it reaches the checkout page through the public params endpoint
        foreach (['company_name' => 'Test Store', 'company_contact' => 'c', 'currency' => '₦', 'account_details' => 'BANK', 'terms_and_conditions' => 't', 'online_payment_enabled' => 'false', 'can_make_order' => 'true'] as $key => $value) {
            DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value]);   // (some are seeded by migrations)
        }
        $this->assertSame($updated, $this->getJson('/api/fetch-necessary-params')->json('params.pickup_warning'));
    }

    public function testAnOwnerWrittenNoteWithoutTheOldPhraseIsLeftAlone()
    {
        $migration = require database_path('migrations/2026_09_23_100000_pickup_note_now_includes_tuesdays.php');
        $setting = new Setting();
        $setting->key = 'pickup_warning';
        $setting->value = 'Pickups are Monday to Wednesday only.';
        $setting->save();

        $migration->up();
        $this->assertSame('Pickups are Monday to Wednesday only.', Setting::where('key', 'pickup_warning')->value('value'));

        Setting::where('key', 'pickup_warning')->delete();
        $migration->up();   // no note at all: nothing to do, no error
        $this->assertNull(Setting::where('key', 'pickup_warning')->first());
    }
}
