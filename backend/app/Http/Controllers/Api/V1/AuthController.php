<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthenticationService $service,
    ) {}

    /**
     * POST /api/v1/auth/login
     *
     * Authenticate an admin user and return a Sanctum bearer token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->service->login($request->validated());

        return response()->json([
            'data' => [
                'token' => $result['token'],
                'user'  => new UserResource($result['user']),
            ],
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     *
     * Revoke the current bearer token.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->service->logout($request->user());

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
