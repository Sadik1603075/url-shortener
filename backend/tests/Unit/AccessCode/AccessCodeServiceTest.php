<?php

namespace Tests\Unit\AccessCode;

use App\DTOs\AccessCode\CreateAccessCodeData;
use App\DTOs\AccessCode\UpdateAccessCodeData;
use App\Models\AccessCode;
use App\Repositories\Contracts\AccessCodeRepositoryInterface;
use App\Services\AccessCode\AccessCodeService;
use App\Support\AccessCodeGenerator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Mockery;
use Tests\TestCase;

class AccessCodeServiceTest extends TestCase
{
    private AccessCodeRepositoryInterface $repository;

    private AccessCodeGenerator $generator;

    private AccessCodeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(AccessCodeRepositoryInterface::class);
        $this->generator = Mockery::mock(AccessCodeGenerator::class);
        $this->service = new AccessCodeService($this->repository, $this->generator);
    }

    public function test_generate_builds_an_active_code_and_delegates_to_the_repository(): void
    {
        $model = new AccessCode(['code' => 'USR-AAAA-BBBB']);

        $this->generator->shouldReceive('generate')->once()->andReturn('USR-AAAA-BBBB');
        $this->repository->shouldReceive('create')
            ->once()
            ->with([
                'user_id' => 7,
                'code' => 'USR-AAAA-BBBB',
                'email' => 'grantee@example.com',
                'description' => 'desc',
                'is_active' => true,
                'expires_at' => '2026-12-31T00:00:00+00:00',
            ])
            ->andReturn($model);

        $result = $this->service->generate(
            new CreateAccessCodeData(
                email: 'grantee@example.com',
                description: 'desc',
                expiresAt: '2026-12-31T00:00:00+00:00',
            ),
            adminUserId: 7,
        );

        $this->assertSame($model, $result);
    }

    public function test_paginate_delegates_to_the_repository(): void
    {
        $paginator = Mockery::mock(LengthAwarePaginator::class);
        $this->repository->shouldReceive('paginate')->once()->with(25)->andReturn($paginator);

        $this->assertSame($paginator, $this->service->paginate(25));
    }

    public function test_update_sends_only_provided_fields(): void
    {
        $accessCode = new AccessCode(['code' => 'USR-AAAA-BBBB']);
        $updated = new AccessCode(['code' => 'USR-AAAA-BBBB']);

        $this->repository->shouldReceive('update')
            ->once()
            ->with($accessCode, ['description' => 'new note', 'is_active' => false])
            ->andReturn($updated);

        $result = $this->service->update(
            $accessCode,
            new UpdateAccessCodeData(description: 'new note', isActive: false),
        );

        $this->assertSame($updated, $result);
    }

    public function test_update_clears_expires_at_when_explicitly_provided_as_null(): void
    {
        $accessCode = new AccessCode(['code' => 'USR-AAAA-BBBB']);

        $this->repository->shouldReceive('update')
            ->once()
            ->with($accessCode, ['expires_at' => null])
            ->andReturn($accessCode);

        $this->service->update($accessCode, new UpdateAccessCodeData(expiresAtProvided: true));
    }

    public function test_update_is_a_noop_when_nothing_provided(): void
    {
        $accessCode = new AccessCode(['code' => 'USR-AAAA-BBBB']);

        // A strict mock with no `update` expectation fails if update is called.
        $result = $this->service->update($accessCode, new UpdateAccessCodeData);

        $this->assertSame($accessCode, $result);
    }

    public function test_delete_delegates_to_the_repository(): void
    {
        $accessCode = new AccessCode(['code' => 'USR-AAAA-BBBB']);
        $this->repository->shouldReceive('delete')->once()->with($accessCode);

        $this->service->delete($accessCode);
    }

    public function test_find_or_fail_returns_the_model_when_found(): void
    {
        $model = new AccessCode(['code' => 'USR-AAAA-BBBB']);
        $this->repository->shouldReceive('findById')->once()->with(1)->andReturn($model);

        $this->assertSame($model, $this->service->findOrFail(1));
    }

    public function test_find_or_fail_throws_when_missing(): void
    {
        $this->repository->shouldReceive('findById')->once()->with(42)->andReturnNull();

        $this->expectException(ModelNotFoundException::class);

        $this->service->findOrFail(42);
    }
}
