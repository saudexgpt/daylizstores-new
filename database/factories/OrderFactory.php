<?php

namespace Database\Factories;

use App\Laravue\Models\User;
use App\Models\Order\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * Order has no $fillable (it's fully $guarded by default, like the rest
     * of the app), so attributes are set directly on the instance rather
     * than through mass assignment — the same pattern OrdersController uses.
     *
     * @return array
     */
    public function definition()
    {
        return [];
    }

    public function configure()
    {
        return $this->afterMaking(function (Order $order) {
            $amount = $this->faker->randomFloat(2, 2000, 50000);
            $deliveryCost = $this->faker->randomFloat(2, 500, 2000);

            $order->user_id = User::factory()->create()->id;
            $order->order_uniq_id = (string) Str::uuid();
            $order->location = $this->faker->randomElement(['Local Pickup/', 'Lagos/', 'Other States/Oyo/']);
            $order->order_number = 'DS' . $this->faker->unique()->numberBetween(100000, 999999);
            $order->order_status = 'Pending';
            $order->payment_status = 'pending';
            $order->amount = $amount;
            $order->delivery_cost = $deliveryCost;
            $order->total = $amount + $deliveryCost;
            $order->address = $this->faker->address();
            $order->nearest_bustop = $this->faker->streetName();
            $order->valid_till = now()->addHours(504);
        });
    }
}
