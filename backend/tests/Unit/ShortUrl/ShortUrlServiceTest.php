<?php

namespace Tests\Unit\ShortUrl;

use App\Cache\Contracts\ShortUrlCacheInterface;
use App\DTOs\ShortUrl\CreateShortUrlData;
use App\DTOs\ShortUrl\UpdateShortUrlData;
use App\Models\ShortUrl;
use App\Repositories\Contracts\ShortUrlRepositoryInterface;
use App\Services\ShortUrl\ShortUrlService;
use App\Support\ShortCodeGenerator;
use Illuminate\Support\Facades\DB;
use Mockery;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ShortUrlServiceTest extends TestCase
{
    private ShortUrlRepositoryInterface $repository;

    private ShortCodeGenerator $generator;

    private ShortUrlCacheInterface $cache;

    private ShortUrlService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(ShortUrlRepositoryInterface::class);
        $this->generator = Mockery::mock(ShortCodeGenerator::class);
        $this->cache = Mockery::mock(ShortUrlCacheInterface::class);

        $this->service = new ShortUrlService(
            $this->repository,
            $this->generator,
            $this->cache,
        );

        // Run the transactional closure inline — no real DB in a unit test.
        DB::shouldReceive('transaction')->andReturnUsing(fn ($callback) => $callback());
    }

    public function test_create_generates_a_code_and_delegates_to_the_repository(): void
    {
        $model = new ShortUrl(['short_code' => 'abc1234']);

        $this->generator->shouldReceive('generate')->once()->andReturn('abc1234');
        $this->repository->shouldReceive('create')
            ->once()
            ->with(5, 'abc1234', 'https://example.com', null)
            ->andReturn($model);

        $result = $this->service->create(5, new CreateShortUrlData('https://example.com'));

        $this->assertSame($model, $result);
    }

    public function test_update_sends_only_non_null_attributes_and_busts_cache(): void
    {
        $shortUrl = new ShortUrl(['short_code' => 'code123']);
        $updated = new ShortUrl(['short_code' => 'code123']);

        $this->repository->shouldReceive('update')
            ->once()
            ->with($shortUrl, ['long_url' => 'https://new.example.com'])
            ->andReturn($updated);
        $this->cache->shouldReceive('forget')->once()->with('code123');

        $result = $this->service->update(
            $shortUrl,
            new UpdateShortUrlData(longUrl: 'https://new.example.com'),
        );

        $this->assertSame($updated, $result);
    }

    public function test_update_keeps_a_false_is_active_flag(): void
    {
        // Guards the array_filter($v !== null) branch: `false` must not be dropped.
        $shortUrl = new ShortUrl(['short_code' => 'code123']);

        $this->repository->shouldReceive('update')
            ->once()
            ->with($shortUrl, ['is_active' => false])
            ->andReturn($shortUrl);
        $this->cache->shouldReceive('forget')->once()->with('code123');

        $this->service->update($shortUrl, new UpdateShortUrlData(isActive: false));
    }

    public function test_update_clears_expires_at_when_explicitly_provided_as_null(): void
    {
        // Regression: a null expires_at that was *supplied* must reach the repo
        // (un-expire), not be filtered out like an omitted field.
        $shortUrl = new ShortUrl(['short_code' => 'code123']);

        $this->repository->shouldReceive('update')
            ->once()
            ->with($shortUrl, ['expires_at' => null])
            ->andReturn($shortUrl);
        $this->cache->shouldReceive('forget')->once()->with('code123');

        $this->service->update($shortUrl, new UpdateShortUrlData(expiresAtProvided: true));
    }

    public function test_update_leaves_expires_at_untouched_when_omitted(): void
    {
        $shortUrl = new ShortUrl(['short_code' => 'code123']);

        // No expires_at key expected in the attributes when it wasn't supplied.
        $this->repository->shouldReceive('update')
            ->once()
            ->with($shortUrl, ['long_url' => 'https://x.example.com'])
            ->andReturn($shortUrl);
        $this->cache->shouldReceive('forget')->once()->with('code123');

        $this->service->update($shortUrl, new UpdateShortUrlData(longUrl: 'https://x.example.com'));
    }

    public function test_delete_busts_cache_then_deletes(): void
    {
        $shortUrl = new ShortUrl(['short_code' => 'code123']);

        $this->cache->shouldReceive('forget')->once()->with('code123');
        $this->repository->shouldReceive('delete')->once()->with($shortUrl);

        $this->service->delete($shortUrl);
    }

    public function test_find_or_fail_returns_the_model_when_found(): void
    {
        $model = new ShortUrl(['short_code' => 'code123']);
        $this->repository->shouldReceive('findById')->once()->with(1)->andReturn($model);

        $this->assertSame($model, $this->service->findOrFail(1));
    }

    public function test_find_or_fail_aborts_404_when_missing(): void
    {
        $this->repository->shouldReceive('findById')->once()->with(42)->andReturnNull();

        $this->expectException(NotFoundHttpException::class);

        $this->service->findOrFail(42);
    }
}
