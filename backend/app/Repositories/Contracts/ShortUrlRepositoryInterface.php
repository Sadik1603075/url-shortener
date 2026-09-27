<?php

namespace App\Repositories\Contracts;

use App\Models\ShortUrl;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ShortUrlRepositoryInterface
{
    public function findByShortCode(string $shortCode): ?ShortUrl;

    public function findById(int $id): ?ShortUrl;

    // ---------- Analytics (read model) ----------

    public function totalCount(): int;

    public function totalClicks(): int;

    /**
     * Count of URLs created per calendar day for the last $days days.
     *
     * @return array<string,int> keyed by Y-m-d
     */
    public function createdCountByDay(int $days): array;

    /**
     * @return Collection<int,ShortUrl>
     */
    public function topByClicks(int $limit): Collection;

    /**
     * Paginated listing for the admin panel.
     *
     * @return LengthAwarePaginator<ShortUrl>
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function create(
        int $userId,
        string $shortCode,
        string $longUrl,
        ?string $expiresAt = null,
    ): ShortUrl;

    public function update(ShortUrl $shortUrl, array $attributes): ShortUrl;

    public function delete(ShortUrl $shortUrl): void;

    public function incrementClickCount(ShortUrl $shortUrl): void;

    /**
     * Increment click_count + touch last_accessed_at by short code, without
     * needing a hydrated model. Used by the click projector.
     */
    public function incrementClickCountByCode(string $shortCode): void;
}
