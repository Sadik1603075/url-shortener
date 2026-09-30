<?php

namespace Database\Factories;

use App\Models\ShortUrl;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShortUrl>
 */
class ShortUrlFactory extends Factory
{
    protected $model = ShortUrl::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'short_code' => Str::random(7),
            'long_url' => fake()->url(),
            'is_active' => true,
            'click_count' => 0,
            'expires_at' => null,
            'last_accessed_at' => null,
        ];
    }
}
