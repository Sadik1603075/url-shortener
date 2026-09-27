<?php

namespace App\Messaging\Contracts;

use App\Events\UrlClicked;

interface ClickEventPublisherInterface
{
    /**
     * Publish a click event for asynchronous analytics processing.
     *
     * Implementations MUST be safe to call on the redirect hot-path. Callers
     * still guard against exceptions, but a publisher should prefer to fail
     * fast rather than block.
     */
    public function publish(UrlClicked $event): void;
}
