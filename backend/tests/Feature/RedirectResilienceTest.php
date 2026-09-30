<?php

namespace Tests\Feature;

use App\Cache\Contracts\ShortUrlCacheInterface;
use App\Events\UrlClicked;
use App\Messaging\Contracts\ClickEventPublisherInterface;
use App\Models\ShortUrl;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RedirectResilienceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // In-memory cache so the redirect path never touches Redis in tests.
        $this->app->singleton(ShortUrlCacheInterface::class, fn () => new class implements ShortUrlCacheInterface
        {
            private array $store = [];

            public function get(string $shortCode): ?string
            {
                return $this->store[$shortCode] ?? null;
            }

            public function put(string $shortCode, string $longUrl, ?int $ttl = null): void
            {
                $this->store[$shortCode] = $longUrl;
            }

            public function forget(string $shortCode): void
            {
                unset($this->store[$shortCode]);
            }
        });
    }

    private function makeUrl(string $code, string $long): void
    {
        $user = User::factory()->create();

        ShortUrl::create([
            'user_id' => $user->id,
            'short_code' => $code,
            'long_url' => $long,
        ]);
    }

    public function test_redirect_still_302s_when_the_publisher_throws(): void
    {
        $this->makeUrl('boom123', 'https://example.com/x');

        $this->app->bind(ClickEventPublisherInterface::class, fn () => new class implements ClickEventPublisherInterface
        {
            public function publish(UrlClicked $event): void
            {
                throw new \RuntimeException('kafka down');
            }
        });

        $this->get('/boom123')
            ->assertStatus(302)
            ->assertRedirect('https://example.com/x');
    }

    public function test_redirect_records_a_click_event_on_the_sync_driver(): void
    {
        config(['kafka.driver' => 'sync']);

        $this->makeUrl('sync123', 'https://example.com/y');

        $this->get('/sync123')->assertStatus(302);

        $this->assertDatabaseHas('click_events', ['short_code' => 'sync123']);
        $this->assertSame(
            1,
            (int) ShortUrl::where('short_code', 'sync123')->value('click_count'),
        );
    }

    public function test_unknown_code_returns_404(): void
    {
        $this->get('/missing99')->assertStatus(404);
    }
}
