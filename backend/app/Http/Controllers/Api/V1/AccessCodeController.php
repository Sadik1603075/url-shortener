<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\AccessCode\CreateAccessCodeData;
use App\DTOs\AccessCode\UpdateAccessCodeData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAccessCodeRequest;
use App\Http\Requests\Admin\UpdateAccessCodeRequest;
use App\Http\Resources\AccessCodeResource;
use App\Services\AccessCode\AccessCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccessCodeController extends Controller
{
    public function __construct(
        private readonly AccessCodeService $service,
    ) {}

    /**
     * GET /api/v1/admin/access-codes
     */
    public function index(): AnonymousResourceCollection
    {
        $paginator = $this->service->paginate(
            perPage: (int) request('per_page', 15)
        );

        return AccessCodeResource::collection($paginator);
    }

    /**
     * POST /api/v1/admin/access-codes
     */
    public function store(StoreAccessCodeRequest $request): JsonResponse
    {
        $accessCode = $this->service->generate(
            data: new CreateAccessCodeData(
                email: $request->validated('email'),
                description: $request->validated('description'),
                expiresAt: $request->validated('expires_at'),
            ),
            adminUserId: $request->user()->id,
        );

        return response()->json([
            'data' => new AccessCodeResource($accessCode->loadMissing('user')),
        ], 201);
    }

    /**
     * GET /api/v1/admin/access-codes/{id}
     */
    public function show(int $id): JsonResponse
    {
        $accessCode = $this->service->findOrFail($id);

        return response()->json([
            'data' => new AccessCodeResource($accessCode->loadMissing('user')),
        ]);
    }

    /**
     * PATCH /api/v1/admin/access-codes/{id}
     */
    public function update(UpdateAccessCodeRequest $request, int $id): JsonResponse
    {
        $accessCode = $this->service->findOrFail($id);

        $updated = $this->service->update(
            accessCode: $accessCode,
            data: UpdateAccessCodeData::fromArray($request->validated()),
        );

        return response()->json([
            'data' => new AccessCodeResource($updated->loadMissing('user')),
        ]);
    }

    /**
     * DELETE /api/v1/admin/access-codes/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $accessCode = $this->service->findOrFail($id);

        $this->service->delete($accessCode);

        return response()->json(null, 204);
    }

    /**
     * POST /api/v1/admin/access-codes/{id}/send
     *
     * Dispatch the access code email to the associated address.
     */
    public function send(int $id): JsonResponse
    {
        $accessCode = $this->service->findOrFail($id);

        $this->service->sendEmail($accessCode);

        return response()->json([
            'message' => "Access code sent to {$accessCode->email}.",
        ]);
    }
}
