<?php

namespace Tests\Feature\Support;

use App\Support\DatabaseShortCodeCounter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DatabaseShortCodeCounterTest extends TestCase
{
    use DatabaseTransactions;

    public function test_next_returns_a_monotonic_increasing_sequence(): void
    {
        $counter = new DatabaseShortCodeCounter;

        // The migration seeds value=0, so the first next() is 1.
        $this->assertSame(1, $counter->next());
        $this->assertSame(2, $counter->next());
        $this->assertSame(3, $counter->next());

        $this->assertDatabaseHas('short_code_counters', [
            'name' => 'short_url',
            'value' => 3,
        ]);
    }
}
