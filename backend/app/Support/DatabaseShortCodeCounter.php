<?php

namespace App\Support;

use App\Support\Contracts\ShortCodeCounterInterface;
use Illuminate\Support\Facades\DB;

/**
 * Monotonic counter backed by a single row in `short_code_counters`.
 *
 * `lockForUpdate` serializes concurrent generators so each gets a distinct id.
 */
class DatabaseShortCodeCounter implements ShortCodeCounterInterface
{
    private const COUNTER_NAME = 'short_url';

    public function next(): int
    {
        return DB::transaction(function () {
            $current = DB::table('short_code_counters')
                ->where('name', self::COUNTER_NAME)
                ->lockForUpdate()
                ->value('value') ?? 0;

            $next = $current + 1;

            DB::table('short_code_counters')->updateOrInsert(
                ['name' => self::COUNTER_NAME],
                ['value' => $next],
            );

            return (int) $next;
        });
    }
}
