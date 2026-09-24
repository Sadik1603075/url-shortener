<?php

namespace App\Repositories\Contracts;

use App\Models\AccessCode;

interface AccessCodeRepositoryInterface
{
    public function findValidCode(string $code): ?AccessCode;

    public function markUsed(AccessCode $accessCode): void;
}