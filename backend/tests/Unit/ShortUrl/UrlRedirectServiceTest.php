<?php

namespace Tests\Unit\ShortUrl;

use App\Cache\Contracts\ShortUrlCacheInterface;
use App\DTOs\Analytics\ClickContext;
use App\Messaging\Contracts\ClickEventPublisherInterface;
use App\Models\ShortUrl;
use App\Repositories\Contracts\ShortUrlRepositoryInterface;
use App\Services\ShortUrl\UrlRedirectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Mockery;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class UrlRedirectServiceTest extends TestCase
{
    private ShortUrlCacheInterface $cache;

    private ShortUrlRepositoryInterface $repository;

    private ClickEventPublisherInterface $publisher;

    private UrlRedirectService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = Mockery::mock(ShortUrlCacheInterface::class);
        $this->repository = Mockery::mock(ShortUrlRepositoryInterface::class);
        $this->publisher = Mockery::mock(ClickEventPublisherInterface::class);

        $this->service = new UrlRedirectService($this->cache, $this->repository, $this->publisher);
    }

    private function context(): ClickContext
    {
        return new ClickContext(ip: '127.0.0.1', userAgent: 'phpunit', referer: null);
    }

    public function test_cache_hit_redirects_without_touching_the_repository(): void
    {
        $this->cache->shouldReceive('get')->once()->with('abc')->andReturn('https://cached.example.com');
        $this->publisher->shouldReceive('publish')->once();
        // Strict mocks: no findByShortCode / put expectation ⇒ test fails if the
        // hot path falls through to the DB or rewrites the cache on a hit.

        $response = $this->service->redirect('abc', $this->context());

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://cached.example.com', $response->getTargetUrl());
    }

    public function test_cache_miss_loads_from_repo_and_populates_cache_with_no_ttl_when_no_expiry(): void
    {
        $shortUrl = new ShortUrl([
            'short_code' => 'abc',
            'long_url' => 'https://long.example.com',
            'is_active' => true,
            'expires_at' => null,
        ]);

        $this->cache->shouldReceive('get')->once()->with('abc')->andReturnNull();
        $this->repository->shouldReceive('findByShortCode')->once()->with('abc')->andReturn($shortUrl);
        $this->cache->shouldReceive('put')->once()->with('abc', 'https://long.example.com', null);
        $this->publisher->shouldReceive('publish')->once();

        $response = $this->service->redirect('abc', $this->context());

        $this->assertSame('https://long.example.com', $response->getTargetUrl());
    }

    public function test_cache_miss_sets_a_positive_ttl_when_the_url_expires(): void
    {
        $shortUrl = new ShortUrl([
            'short_code' => 'abc',
            'long_url' => 'https://long.example.com',
            'is_active' => true,
            'expires_at' => now()->addHour(),
        ]);

        $this->cache->shouldReceive('get')->once()->andReturnNull();
        $this->repository->shouldReceive('findByShortCode')->once()->andReturn($shortUrl);
        $this->cache->shouldReceive('put')
            ->once()
            ->with('abc', 'https://long.example.com', Mockery::on(fn ($ttl) => is_int($ttl) && $ttl > 0 && $ttl <= 3600));
        $this->publisher->shouldReceive('publish')->once();

        $this->service->redirect('abc', $this->context());
    }

    public function test_unknown_code_aborts_404_and_does_not_emit_or_cache(): void
    {
        $this->cache->shouldReceive('get')->once()->andReturnNull();
        $this->repository->shouldReceive('findByShortCode')->once()->andReturnNull();
        // No publish / put expectations ⇒ they must not be called.

        $this->expectException(NotFoundHttpException::class);

        $this->service->redirect('nope', $this->context());
    }

    public function test_expired_url_aborts_404(): void
    {
        $shortUrl = new ShortUrl([
            'short_code' => 'abc',
            'long_url' => 'https://long.example.com',
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        $this->cache->shouldReceive('get')->once()->andReturnNull();
        $this->repository->shouldReceive('findByShortCode')->once()->andReturn($shortUrl);

        $this->expectException(NotFoundHttpException::class);

        $this->service->redirect('abc', $this->context());
    }

    public function test_inactive_url_aborts_404(): void
    {
        $shortUrl = new ShortUrl([
            'short_code' => 'abc',
            'long_url' => 'https://long.example.com',
            'is_active' => false,
            'expires_at' => null,
        ]);

        $this->cache->shouldReceive('get')->once()->andReturnNull();
        $this->repository->shouldReceive('findByShortCode')->once()->andReturn($shortUrl);

        $this->expectException(NotFoundHttpException::class);

        $this->service->redirect('abc', $this->context());
    }

    public function test_redirect_survives_a_publisher_failure(): void
    {
        // Resilience: the hot path must never 500 because analytics is down.
        Log::shouldReceive('warning')->once()->with('click.publish.failed', Mockery::type('array'));

        $this->cache->shouldReceive('get')->once()->andReturn('https://cached.example.com');
        $this->publisher->shouldReceive('publish')->once()->andThrow(new \RuntimeException('broker down'));

        $response = $this->service->redirect('abc', $this->context());

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://cached.example.com', $response->getTargetUrl());
    }
}
