<?php

namespace Database\Factories;

use App\Models\Stock\Category;
use App\Models\Stock\Item;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Item::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $name = ucfirst($this->faker->unique()->words(3, true));

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . $this->faker->unique()->numberBetween(1000, 9999),
            'package_type' => $this->faker->randomElement(['Carton', 'Piece', 'Pack', 'Bag']),
            'description' => $this->faker->sentence(),
            'enabled' => true,
        ];
    }
}
