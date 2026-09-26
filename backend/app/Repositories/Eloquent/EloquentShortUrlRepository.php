<?php

namespace App\Repositories\Eloquent;

use App\Models\ShortUrl;
use App\Repositories\Contracts\ShortUrlRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentShortUrlRepository implements ShortUrlRepositoryInterface
{
    public function findByShortCode(string $shortCode): ?ShortUrl
    {
        return ShortUrl::query()
            ->where('short_code', $shortCode)
            ->where('is_active', true)
            ->first();
    }

    public function findById(int $id): ?ShortUrl
    {
        return ShortUrl::query()->find($id);
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return ShortUrl::query()
            ->with('user')
            ->latest()
            ->paginate($perPage);
    }

    public function create(
        int     $userId,
        string  $shortCode,
        string  $longUrl,
        ?string $expiresAt = null,
    ): ShortUrl {
        return ShortUrl::create([
            'user_id'    => $userId,
            'short_code' => $shortCode,
            'long_url'   => $longUrl,
            'expires_at' => $expiresAt,
        ]);
    }

    public function update(ShortUrl $shortUrl, array $attributes): ShortUrl
    {
        $shortUrl->update($attributes);

        return $shortUrl->fresh();
    }

    public function delete(ShortUrl $shortUrl): void
    {
        $shortUrl->delete();
    }

    public function incrementClickCount(ShortUrl $shortUrl): void
    {
        $shortUrl->increment('click_count');

        $shortUrl->update([
            'last_accessed_at' => now(),
        ]);
    }
}
