<?php

namespace App\Providers;

use App\Messaging\Contracts\ClickEventPublisherInterface;
use App\Messaging\KafkaClickEventPublisher;
use App\Messaging\LogClickEventPublisher;
use App\Messaging\SyncClickEventPublisher;
use Illuminate\Support\ServiceProvider;

class MessagingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ClickEventPublisherInterface::class,
            fn () => $this->makePublisher(),
        );
    }

    private function makePublisher(): ClickEventPublisherInterface
    {
        return match (config('kafka.driver', 'sync')) {
            'kafka' => new KafkaClickEventPublisher(
                brokers: (string) config('kafka.brokers'),
                topicName: (string) config('kafka.click_topic'),
            ),
            'log' => new LogClickEventPublisher,
            default => $this->app->make(SyncClickEventPublisher::class),
        };
    }
}
