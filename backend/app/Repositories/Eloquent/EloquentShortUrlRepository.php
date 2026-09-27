<?php

namespace App\Repositories\Eloquent;

use App\Models\ShortUrl;
use App\Repositories\Contracts\ShortUrlRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    public function totalCount(): int
    {
        return ShortUrl::query()->count();
    }

    public function totalClicks(): int
    {
        return (int) ShortUrl::query()->sum('click_count');
    }

    public function createdCountByDay(int $days): array
    {
        $since = now()->startOfDay()->subDays($days - 1);

        return ShortUrl::query()
            ->where('created_at', '>=', $since)
            ->get(['created_at'])
            ->groupBy(fn (ShortUrl $u) => $u->created_at->toDateString())
            ->map->count()
            ->toArray();
    }

    public function topByClicks(int $limit): Collection
    {
        return ShortUrl::query()
            ->orderByDesc('click_count')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return ShortUrl::query()
            ->with('user')
            ->latest()
            ->paginate($perPage);
    }

    public function create(
        int $userId,
        string $shortCode,
        string $longUrl,
        ?string $expiresAt = null,
    ): ShortUrl {
        return ShortUrl::create([
            'user_id' => $userId,
            'short_code' => $shortCode,
            'long_url' => $longUrl,
            'expires_at' => $expiresAt,
        ])->refresh();
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

    public function incrementClickCountByCode(string $shortCode): void
    {
        ShortUrl::query()
            ->where('short_code', $shortCode)
            ->update([
                'click_count' => DB::raw('click_count + 1'),
                'last_accessed_at' => now(),
            ]);
    }
}
