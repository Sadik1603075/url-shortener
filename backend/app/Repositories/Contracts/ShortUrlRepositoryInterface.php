<?php

namespace App\Repositories\Contracts;

use App\Models\ShortUrl;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ShortUrlRepositoryInterface
{
    public function findByShortCode(string $shortCode): ?ShortUrl;

    public function findById(int $id): ?ShortUrl;

    /**
     * Paginated listing for the admin panel.
     *
     * @return LengthAwarePaginator<ShortUrl>
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function create(
        int     $userId,
        string  $shortCode,
        string  $longUrl,
        ?string $expiresAt = null,
    ): ShortUrl;

    public function update(ShortUrl $shortUrl, array $attributes): ShortUrl;

    public function delete(ShortUrl $shortUrl): void;

    public function incrementClickCount(ShortUrl $shortUrl): void;
}
