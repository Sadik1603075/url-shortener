<?php

namespace App\Messaging;

use App\Events\UrlClicked;
use App\Messaging\Contracts\ClickEventPublisherInterface;
use RdKafka\Conf;
use RdKafka\Producer;
use RdKafka\ProducerTopic;

/**
 * Kafka transport (CLICK_EVENT_DRIVER=kafka), backed by ext-rdkafka.
 *
 * Fire-and-forget on the redirect hot-path: produce() hands the message to
 * librdkafka's background thread and returns immediately. The clicks:consume
 * worker projects the event into the read model asynchronously.
 *
 * Only instantiated when the driver is "kafka" (the Dockerised backend image
 * ships ext-rdkafka), so the host-dev runtime never needs the extension.
 */
class KafkaClickEventPublisher implements ClickEventPublisherInterface
{
    private ?Producer $producer = null;

    private ?ProducerTopic $topic = null;

    public function __construct(
        private readonly string $brokers,
        private readonly string $topicName,
    ) {}

    public function publish(UrlClicked $event): void
    {
        $topic = $this->topic();

        $topic->produce(
            RD_KAFKA_PARTITION_UA,
            0,
            json_encode($event->toArray(), JSON_THROW_ON_ERROR),
            $event->shortCode, // key → per-URL ordering within a partition
        );

        // Serve delivery-report callbacks without blocking the request.
        $this->producer?->poll(0);
    }

    private function topic(): ProducerTopic
    {
        if ($this->topic !== null) {
            return $this->topic;
        }

        $conf = new Conf;
        $conf->set('bootstrap.servers', $this->brokers);
        $conf->set('message.timeout.ms', '5000');
        $conf->set('socket.timeout.ms', '3000');

        $this->producer = new Producer($conf);
        $this->topic = $this->producer->newTopic($this->topicName);

        return $this->topic;
    }

    public function __destruct()
    {
        // Best-effort drain of buffered messages at process shutdown.
        $this->producer?->flush(2000);
    }
}
