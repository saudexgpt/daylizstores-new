<?php

namespace Database\Factories;

use App\Laravue\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->unique()->numerify('080########'),
            'password' => 'password', // hashed automatically by User::setPasswordAttribute()
        ];
    }

    /**
     * Indicate that the user is a customer (the default role for new accounts).
     *
     * @return static
     */
    public function customer()
    {
        return $this->afterCreating(function (User $user) {
            $user->role = 'customer';
            $user->save();
        });
    }

    /**
     * Indicate that the user is staff.
     *
     * @return static
     */
    public function staff()
    {
        return $this->afterCreating(function (User $user) {
            $user->role = 'staff';
            $user->save();
        });
    }
}
