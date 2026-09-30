<?php

namespace Database\Factories;

use App\Models\AccessCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AccessCode>
 */
class AccessCodeFactory extends Factory
{
    protected $model = AccessCode::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'code' => Str::upper(Str::random(10)),
            'email' => fake()->unique()->safeEmail(),
            'description' => null,
            'is_active' => true,
            'expires_at' => null,
            'last_used_at' => null,
        ];
    }

    /** Active but past its expiry — `findValidCode` must reject it. */
    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    /** Deactivated — `findValidCode` must reject it. */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
