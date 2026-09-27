<?php

namespace App\Messaging;

use App\Events\UrlClicked;
use App\Messaging\Contracts\ClickEventPublisherInterface;

/**
 * Host-dev transport: project the click inline, no Kafka required.
 *
 * Chosen when CLICK_EVENT_DRIVER=sync. Analytics are up to date immediately,
 * at the cost of doing the projection within the request. The redirect still
 * guards the call, so a projection failure never breaks the 302.
 */
class SyncClickEventPublisher implements ClickEventPublisherInterface
{
    public function __construct(
        private readonly ClickProjector $projector,
    ) {}

    public function publish(UrlClicked $event): void
    {
        $this->projector->project($event);
    }
}
