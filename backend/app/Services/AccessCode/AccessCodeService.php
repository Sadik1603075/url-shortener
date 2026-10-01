<?php

namespace App\Services\AccessCode;

use App\DTOs\AccessCode\CreateAccessCodeData;
use App\DTOs\AccessCode\UpdateAccessCodeData;
use App\Mail\AccessCodeMail;
use App\Models\AccessCode;
use App\Repositories\Contracts\AccessCodeRepositoryInterface;
use App\Support\AccessCodeGenerator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AccessCodeService
{
    public function __construct(
        private readonly AccessCodeRepositoryInterface $repository,
        private readonly AccessCodeGenerator $generator,
    ) {}

    // -----------------------------------------------------------------------
    // Public — URL creation gate
    // -----------------------------------------------------------------------

    /**
     * Validate the supplied access code and return the owning user ID.
     * Throws a ValidationException when the code is invalid/expired.
     */
    public function validateAndConsume(string $code): int
    {
        $accessCode = $this->repository->findValidCode($code);

        if ($accessCode === null) {
            throw ValidationException::withMessages([
                'access_code' => ['The provided access code is invalid or has expired.'],
            ]);
        }

        $this->repository->markUsed($accessCode);

        return $accessCode->user_id;
    }

    // -----------------------------------------------------------------------
    // Admin CRUD
    // -----------------------------------------------------------------------

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }

    public function findOrFail(int $id): AccessCode
    {
        $accessCode = $this->repository->findById($id);

        if ($accessCode === null) {
            throw (new ModelNotFoundException)->setModel(AccessCode::class, [$id]);
        }

        return $accessCode;
    }

    /**
     * Generate a new access code for an email address.
     * Enforces one-code-per-email at the service layer (DB unique also enforces).
     */
    public function generate(CreateAccessCodeData $data, int $adminUserId): AccessCode
    {
        $code = $this->generator->generate();

        return $this->repository->create([
            'user_id' => $adminUserId,
            'code' => $code,
            'email' => $data->email,
            'description' => $data->description,
            'is_active' => true,
            'expires_at' => $data->expiresAt,
        ]);
    }

    public function update(AccessCode $accessCode, UpdateAccessCodeData $data): AccessCode
    {
        $attributes = [];

        if ($data->description !== null) {
            $attributes['description'] = $data->description;
        }

        if ($data->isActive !== null) {
            $attributes['is_active'] = $data->isActive;
        }

        // expires_at is nullable: include it whenever it was supplied, so a null
        // clears the expiry rather than being ignored.
        if ($data->expiresAtProvided) {
            $attributes['expires_at'] = $data->expiresAt;
        }

        if (empty($attributes)) {
            return $accessCode;
        }

        return $this->repository->update($accessCode, $attributes);
    }

    public function delete(AccessCode $accessCode): void
    {
        $this->repository->delete($accessCode);
    }

    /**
     * Send the access code via email to the associated address.
     */
    public function sendEmail(AccessCode $accessCode): void
    {
        Mail::to($accessCode->email)
            ->send(new AccessCodeMail($accessCode));
    }
}
