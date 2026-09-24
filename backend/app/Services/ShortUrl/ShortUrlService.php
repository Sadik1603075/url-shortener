<?php

namespace App\Services\ShortUrl;

use App\DTOs\ShortUrl\CreateShortUrlData;
use App\Models\ShortUrl;
use App\Repositories\Contracts\ShortUrlRepositoryInterface;
use App\Support\ShortCodeGenerator;
use Illuminate\Support\Facades\DB;

class ShortUrlService
{
    public function __construct(
        private readonly ShortUrlRepositoryInterface $repository,
        private readonly ShortCodeGenerator $codeGenerator,
    ) {}

    public function create(
        int $userId,
        CreateShortUrlData $data,
    ): ShortUrl {
        return DB::transaction(function () use ($userId, $data) {
            $shortCode = $this->codeGenerator->generate();

            return $this->repository->create(
                userId: $userId,
                shortCode: $shortCode,
                longUrl: $data->longUrl,
                expiresAt: $data->expiresAt,
            );
        });
    }
}