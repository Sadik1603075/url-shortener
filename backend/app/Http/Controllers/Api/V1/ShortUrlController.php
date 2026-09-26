<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\ShortUrl\CreateShortUrlData;
use App\DTOs\ShortUrl\UpdateShortUrlData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateShortUrlRequest;
use App\Http\Requests\Api\V1\StoreShortUrlRequest;
use App\Http\Resources\ShortUrlResource;
use App\Services\AccessCode\AccessCodeService;
use App\Services\ShortUrl\ShortUrlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShortUrlController extends Controller
{
    public function __construct(
        private readonly ShortUrlService   $shortUrlService,
        private readonly AccessCodeService $accessCodeService,
    ) {}

    // -------------------------------------------------------------------------
    // Public endpoints
    // -------------------------------------------------------------------------

    /**
     * POST /api/v1/urls
     *
     * Create a new short URL. Requires a valid access code.
     */
    public function store(StoreShortUrlRequest $request): JsonResponse
    {
        $userId = $this->accessCodeService->validateAndConsume(
            $request->validated('access_code')
        );

        $shortUrl = $this->shortUrlService->create(
            userId: $userId,
            data: new CreateShortUrlData(
                longUrl:   $request->validated('long_url'),
                expiresAt: $request->validated('expires_at'),
            ),
        );

        return response()->json([
            'data' => new ShortUrlResource($shortUrl),
        ], 201);
    }

    // -------------------------------------------------------------------------
    // Admin endpoints  (protected by auth:sanctum + admin middleware)
    // -------------------------------------------------------------------------

    /**
     * GET /api/v1/admin/urls
     *
     * Paginated list of all short URLs.
     */
    public function index(): AnonymousResourceCollection
    {
        $paginator = $this->shortUrlService->paginate(
            perPage: (int) request('per_page', 15)
        );

        return ShortUrlResource::collection($paginator);
    }

    /**
     * GET /api/v1/admin/urls/{id}
     *
     * Show a single short URL.
     */
    public function show(int $id): JsonResponse
    {
        $shortUrl = $this->shortUrlService->findOrFail($id);

        return response()->json([
            'data' => new ShortUrlResource($shortUrl->loadMissing('user')),
        ]);
    }

    /**
     * PATCH /api/v1/admin/urls/{id}
     *
     * Update an existing short URL (long_url, is_active, expires_at).
     */
    public function update(UpdateShortUrlRequest $request, int $id): JsonResponse
    {
        $shortUrl = $this->shortUrlService->findOrFail($id);

        $updated = $this->shortUrlService->update(
            shortUrl: $shortUrl,
            data: UpdateShortUrlData::fromArray($request->validated()),
        );

        return response()->json([
            'data' => new ShortUrlResource($updated),
        ]);
    }

    /**
     * DELETE /api/v1/admin/urls/{id}
     *
     * Permanently delete a short URL.
     */
    public function destroy(int $id): JsonResponse
    {
        $shortUrl = $this->shortUrlService->findOrFail($id);

        $this->shortUrlService->delete($shortUrl);

        return response()->json(null, 204);
    }
}
