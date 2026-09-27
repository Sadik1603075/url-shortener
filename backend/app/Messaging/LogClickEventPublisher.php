<?php

namespace App\Messaging;

use App\Events\UrlClicked;
use App\Messaging\Contracts\ClickEventPublisherInterface;
use Illuminate\Support\Facades\Log;

/**
 * No-op transport (CLICK_EVENT_DRIVER=log): analytics disabled, click is just
 * logged. Useful for load tests or environments without an analytics store.
 */
class LogClickEventPublisher implements ClickEventPublisherInterface
{
    public function publish(UrlClicked $event): void
    {
        Log::info('url.clicked', $event->toArray());
    }
}
