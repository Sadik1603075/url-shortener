<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\ShortUrl\CreateShortUrlData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateShortUrlRequest;
use App\Http\Resources\Api\V1\ShortUrlResource;
use App\Services\ShortUrl\ShortUrlService;
use Illuminate\Http\JsonResponse;

class ShortUrlController extends Controller
{
    public function __construct(
        private readonly ShortUrlService $service,
    ) {}

    public function store(
        CreateShortUrlRequest $request,
    ): JsonResponse {
        $data = new CreateShortUrlData(
            longUrl: $request->string('long_url')->toString(),
            expiresAt: $request->input('expires_at'),
        );

        $shortUrl = $this->service->create(
            userId: $request->user()->id,
            data: $data,
        );

        return response()->json([
            'data' => new ShortUrlResource($shortUrl),
        ], 201);
    }
}