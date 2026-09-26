<?php

namespace App\Repositories\Eloquent;

use App\Models\AccessCode;
use App\Repositories\Contracts\AccessCodeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentAccessCodeRepository implements AccessCodeRepositoryInterface
{
    // -----------------------------------------------------------------------
    // Public (URL creation gate)
    // -----------------------------------------------------------------------

    public function findValidCode(string $code): ?AccessCode
    {
        $accessCode = AccessCode::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if (! $accessCode || ! $accessCode->isValid()) {
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

    // -----------------------------------------------------------------------
    // Admin CRUD
    // -----------------------------------------------------------------------

    public function findByCode(string $code): ?AccessCode
    {
        return AccessCode::query()->where('code', $code)->first();
    }

    public function findById(int $id): ?AccessCode
    {
        return AccessCode::query()->find($id);
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return AccessCode::query()
            ->with('user')
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $attributes): AccessCode
    {
        return AccessCode::query()->create($attributes);
    }

    public function update(AccessCode $accessCode, array $attributes): AccessCode
    {
        $accessCode->update($attributes);

        return $accessCode->refresh();
    }

    public function delete(AccessCode $accessCode): void
    {
        $accessCode->delete();
    }
}
