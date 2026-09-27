<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Click event transport
    |--------------------------------------------------------------------------
    |
    | Which ClickEventPublisher implementation handles UrlClicked events.
    |
    |   "sync"  → project inline into the DB read model (host dev, no Kafka)
    |   "kafka" → produce to Kafka; the clicks:consume worker projects async
    |   "log"   → discard to the log (analytics disabled)
    |
    */

    'driver' => env('CLICK_EVENT_DRIVER', 'sync'),

    'brokers' => env('KAFKA_BROKERS', 'localhost:9092'),

    'click_topic' => env('KAFKA_CLICK_TOPIC', 'url.clicked'),

    'consumer_group' => env('KAFKA_CONSUMER_GROUP', 'linkforge-clicks'),

    /*
    | Max seconds the consumer waits for a message before looping (allows
    | graceful shutdown between polls).
    */
    'consume_timeout_ms' => (int) env('KAFKA_CONSUME_TIMEOUT_MS', 2000),
];
