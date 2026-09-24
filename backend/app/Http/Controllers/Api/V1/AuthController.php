<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Services\Auth\AuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthenticationService $authenticationService
    ) {
    }

    /**
     * Authenticate an admin user.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authenticationService->login(
            $request->validated()
        );

        return response()->json($result);
    }

    /**
     * Logout the authenticated admin user.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authenticationService->logout(
            $request->user()
        );

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Get the authenticated admin user.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }
}