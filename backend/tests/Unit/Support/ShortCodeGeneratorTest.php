<?php

namespace Tests\Unit\Support;

use App\Support\Contracts\ShortCodeCounterInterface;
use App\Support\ShortCodeGenerator;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class ShortCodeGeneratorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('shortcode.key', 'unit-test-secret-key');
        Config::set('shortcode.min_length', 7);
    }

    /** A counter fake that yields 1, 2, 3, … without any DB. */
    private function sequentialCounter(): ShortCodeCounterInterface
    {
        return new class implements ShortCodeCounterInterface
        {
            private int $value = 0;

            public function next(): int
            {
                return ++$this->value;
            }
        };
    }

    public function test_generates_unique_alphabet_only_codes_of_at_least_min_length(): void
    {
        $generator = new ShortCodeGenerator($this->sequentialCounter());

        $codes = [];
        for ($i = 0; $i < 500; $i++) {
            $codes[] = $generator->generate();
        }

        // Unique over N generations (bijection guarantees no collisions).
        $this->assertCount(500, array_unique($codes));

        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[0-9A-Za-z]+$/', $code);
            $this->assertGreaterThanOrEqual(7, strlen($code));
        }
    }

    public function test_consecutive_ids_produce_scattered_non_sequential_codes(): void
    {
        $generator = new ShortCodeGenerator($this->sequentialCounter());

        $codes = [];
        for ($i = 0; $i < 100; $i++) {
            $codes[] = $generator->generate();
        }

        $sorted = $codes;
        sort($sorted);

        // A pure counter would already be sorted; obfuscation must reorder them.
        $this->assertNotSame($sorted, $codes, 'Codes should not be in counter order.');
        // Adjacent ids map to different codes.
        $this->assertNotSame($codes[0], $codes[1]);
    }

    public function test_generate_does_not_perform_a_collision_lookup(): void
    {
        // The only dependency is the counter — there is no repository / DB SELECT
        // to check for collisions (ADR-0001). A strict mock proves the contract.
        $counter = Mockery::mock(ShortCodeCounterInterface::class);
        $counter->shouldReceive('next')->once()->andReturn(1);

        $generator = new ShortCodeGenerator($counter);

        $this->assertNotEmpty($generator->generate());
    }

    public function test_decode_reverses_generate_back_to_the_counter_id(): void
    {
        foreach ([1, 2, 1000, 123_456, 2 ** 40] as $id) {
            $counter = Mockery::mock(ShortCodeCounterInterface::class);
            $counter->shouldReceive('next')->once()->andReturn($id);

            $generator = new ShortCodeGenerator($counter);
            $code = $generator->generate();

            $this->assertSame($id, $generator->decode($code));
        }
    }

    public function test_padding_does_not_corrupt_decoding(): void
    {
        // A code padded with leading zeros must still decode back to its id.
        $counter = Mockery::mock(ShortCodeCounterInterface::class);
        $counter->shouldReceive('next')->once()->andReturn(1);

        $generator = new ShortCodeGenerator($counter);
        $code = $generator->generate();

        $this->assertGreaterThanOrEqual(7, strlen($code));
        $this->assertSame(1, $generator->decode($code));
    }
}
