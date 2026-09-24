<?php

namespace App\Repositories\Eloquent;

use App\Models\AccessCode;
use App\Repositories\Contracts\AccessCodeRepositoryInterface;

class EloquentAccessCodeRepository implements AccessCodeRepositoryInterface
{
    public function findValidCode(string $code): ?AccessCode
    {
        $accessCode = AccessCode::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if (!$accessCode || !$accessCode->isValid()) {
            return null;
        }

        return $accessCode;
    }

    public function markUsed(AccessCode $accessCode): void
    {
        $accessCode->update([
            'last_used_at' => now(),
        ]);
    }
}