<?php

namespace App\Services\Auth;

use App\DTOs\Auth\LoginData;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticationService
{
    /**
     * Authenticate an administrator and create a Sanctum token.
     */
    public function login(array $data): array
    {
        $loginData = LoginData::fromArray($data);

        $user = User::where('email', $loginData->email)->first();

        if (
            !$user ||
            !Hash::check($loginData->password, $user->password)
        ) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->isAdmin()) {
            throw ValidationException::withMessages([
                'email' => ['You are not authorized to access the admin area.'],
            ]);
        }

        $token = $user->createToken(
            'admin-web'
        )->plainTextToken;

        return [
            'token' => $token,
            'user' => $user,
        ];
    }

    /**
     * Revoke the current Sanctum token.
     */
    public function logout(?User $user): void
    {
        if (!$user) {
            return;
        }

        $user->currentAccessToken()?->delete();
    }
}