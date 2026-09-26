<?php

namespace App\Repositories\Contracts;

use App\Models\AccessCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AccessCodeRepositoryInterface
{
    // ---------- Public (URL creation gate) ----------

    public function findValidCode(string $code): ?AccessCode;

    public function markUsed(AccessCode $accessCode): void;

    // ---------- Admin CRUD ----------

    public function findByCode(string $code): ?AccessCode;

    public function findById(int $id): ?AccessCode;

    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function create(array $attributes): AccessCode;

    public function update(AccessCode $accessCode, array $attributes): AccessCode;

    public function delete(AccessCode $accessCode): void;
}
