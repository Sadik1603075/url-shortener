<?php

namespace Tests\Feature;

use App\Events\UrlClicked;
use App\Messaging\Contracts\ClickEventPublisherInterface;
use App\Models\ShortUrl;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\BindsInMemoryShortUrlCache;
use Tests\TestCase;

class RedirectResilienceTest extends TestCase
{
    use BindsInMemoryShortUrlCache;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bindInMemoryShortUrlCache();
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

    private function bindThrowingPublisher(): void
    {
        $this->app->bind(ClickEventPublisherInterface::class, fn () => new class implements ClickEventPublisherInterface
        {
            public function publish(UrlClicked $event): void
            {
                throw new \RuntimeException('kafka down');
            }
        });
    }

    public function test_redirect_still_302s_when_the_publisher_throws(): void
    {
        $this->makeUrl('boom123', 'https://example.com/x');
        $this->bindThrowingPublisher();

        $this->get('/boom123')
            ->assertStatus(302)
            ->assertRedirect('https://example.com/x');
    }

    public function test_publisher_failure_is_logged(): void
    {
        Log::spy();
        $this->makeUrl('boom124', 'https://example.com/x');
        $this->bindThrowingPublisher();

        $this->get('/boom124')->assertStatus(302);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message, $context = []) => $message === 'click.publish.failed'
                && ($context['short_code'] ?? null) === 'boom124')
            ->once();
    }

    public function test_publisher_failure_increments_the_metric(): void
    {
        $this->makeUrl('boom125', 'https://example.com/x');
        $this->bindThrowingPublisher();

        $this->get('/boom125')->assertStatus(302);

        // The /metrics counter for publish failures (ADR-0004) reflects the degradation.
        $body = $this->get('/metrics')->assertOk()->getContent();
        // Anchored so "…_total 1" doesn't also match "…_total 10".
        $this->assertMatchesRegularExpression('/^linkforge_click_publish_failures_total 1$/m', $body);
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
