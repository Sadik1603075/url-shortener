<?php

namespace App\Console\Commands;

use App\Events\UrlClicked;
use App\Messaging\ClickProjector;
use Illuminate\Console\Command;
use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use RdKafka\Message;

/**
 * CQRS query-side worker: consume `url.clicked` from Kafka and project each
 * event into the read model. Runs as a long-lived process (Docker worker).
 *
 * Requires ext-rdkafka (present in the Dockerised backend image). On the host
 * dev runtime the driver is "sync" and this command is not used.
 */
class ConsumeClicks extends Command
{
    protected $signature = 'clicks:consume';

    protected $description = 'Consume url.clicked events from Kafka into the analytics read model';

    private bool $shouldStop = false;

    public function handle(ClickProjector $projector): int
    {
        if (! extension_loaded('rdkafka')) {
            $this->error('ext-rdkafka is not installed. Run this in the Dockerised backend.');

            return self::FAILURE;
        }

        $this->listenForSignals();

        $consumer = $this->makeConsumer();
        $consumer->subscribe([(string) config('kafka.click_topic')]);

        $timeout = (int) config('kafka.consume_timeout_ms', 2000);

        $this->info('Consuming '.config('kafka.click_topic').' … (Ctrl+C to stop)');

        while (! $this->shouldStop) {
            $message = $consumer->consume($timeout);

            match ($message->err) {
                RD_KAFKA_RESP_ERR_NO_ERROR => $this->handleMessage($projector, $consumer, $message),
                RD_KAFKA_RESP_ERR__PARTITION_EOF => null, // no more messages for now
                RD_KAFKA_RESP_ERR__TIMED_OUT => null, // idle poll
                default => $this->warn('Kafka: '.$message->errstr()),
            };
        }

        $this->info('Shutting down consumer.');
        $consumer->close();

        return self::SUCCESS;
    }

    private function handleMessage(ClickProjector $projector, KafkaConsumer $consumer, Message $message): void
    {
        try {
            $payload = json_decode((string) $message->payload, true, 512, JSON_THROW_ON_ERROR);

            $projector->project(UrlClicked::fromArray($payload));

            $consumer->commit($message);
        } catch (\Throwable $e) {
            // Poison message: log and move on so one bad event can't wedge the worker.
            $this->error('Projection failed: '.$e->getMessage());
            $consumer->commit($message);
        }
    }

    private function makeConsumer(): KafkaConsumer
    {
        $conf = new Conf;
        $conf->set('group.id', (string) config('kafka.consumer_group'));
        $conf->set('bootstrap.servers', (string) config('kafka.brokers'));
        $conf->set('auto.offset.reset', 'earliest');
        $conf->set('enable.auto.commit', 'false');
        $conf->set('enable.partition.eof', 'true');

        return new KafkaConsumer($conf);
    }

    private function listenForSignals(): void
    {
        if (! function_exists('pcntl_signal')) {
            return;
        }

        pcntl_async_signals(true);

        $stop = function (): void {
            $this->shouldStop = true;
        };

        pcntl_signal(SIGINT, $stop);
        pcntl_signal(SIGTERM, $stop);
    }
}
