<?php

namespace App\Services\ShortUrl;

use App\Cache\Contracts\ShortUrlCacheInterface;
use App\DTOs\ShortUrl\CreateShortUrlData;
use App\DTOs\ShortUrl\UpdateShortUrlData;
use App\Models\ShortUrl;
use App\Repositories\Contracts\ShortUrlRepositoryInterface;
use App\Support\ShortCodeGenerator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ShortUrlService
{
    public function __construct(
        private readonly ShortUrlRepositoryInterface $repository,
        private readonly ShortCodeGenerator          $codeGenerator,
        private readonly ShortUrlCacheInterface      $cache,
    ) {}

    /**
     * Create a new shortened URL owned by the given user.
     */
    public function create(int $userId, CreateShortUrlData $data): ShortUrl
    {
        return DB::transaction(function () use ($userId, $data) {
            $shortCode = $this->codeGenerator->generate();

            return $this->repository->create(
                userId:    $userId,
                shortCode: $shortCode,
                longUrl:   $data->longUrl,
                expiresAt: $data->expiresAt,
            );
        });
    }

    /**
     * Return a paginated listing of all short URLs (admin).
     *
     * @return LengthAwarePaginator<ShortUrl>
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }

    /**
     * Find a single short URL by its primary key (admin).
     */
    public function findOrFail(int $id): ShortUrl
    {
        $shortUrl = $this->repository->findById($id);

        if ($shortUrl === null) {
            abort(404, 'Short URL not found.');
        }

        return $shortUrl;
    }

    /**
     * Update an existing short URL. Busts the Redis cache on success.
     */
    public function update(ShortUrl $shortUrl, UpdateShortUrlData $data): ShortUrl
    {
        return DB::transaction(function () use ($shortUrl, $data) {
            $attributes = array_filter([
                'long_url'   => $data->longUrl,
                'is_active'  => $data->isActive,
                'expires_at' => $data->expiresAt,
            ], fn ($v) => $v !== null);

            $updated = $this->repository->update($shortUrl, $attributes);

            // Bust the cache so redirects pick up the new destination / status
            $this->cache->forget($shortUrl->short_code);

            return $updated;
        });
    }

    /**
     * Permanently delete a short URL and evict it from the cache.
     */
    public function delete(ShortUrl $shortUrl): void
    {
        DB::transaction(function () use ($shortUrl) {
            $this->cache->forget($shortUrl->short_code);
            $this->repository->delete($shortUrl);
        });
    }
}
