<?php

namespace App\Support;

use App\Support\Contracts\ShortCodeCounterInterface;

/**
 * Generates short codes per ADR-0001 (Option C):
 *
 *   id = counter.next()            // monotonic, unique
 *   scrambled = feistel(id, key)   // keyed, reversible, uniform → non-enumerable
 *   code = Base62.encode(scrambled) left-padded to the minimum length
 *
 * Uniqueness is guaranteed by the counter + a bijective permutation, so there is
 * no collision lookup on the create path. `decode()` exists for internal tooling
 * only and is never exposed via the API.
 */
class ShortCodeGenerator
{
    // 48-bit domain split into two 24-bit halves for the Feistel network.
    private const HALF_BITS = 24;

    private const HALF_MASK = 0xFFFFFF;

    // Largest id the 48-bit Feistel domain can represent bijectively.
    private const DOMAIN_MAX = (1 << 48) - 1;

    private const ROUNDS = 4;

    private readonly string $key;

    private readonly int $minLength;

    public function __construct(
        private readonly ShortCodeCounterInterface $counter,
    ) {
        // Never run unkeyed: fall back to the app key if SHORTCODE_KEY is unset.
        $this->key = (string) (config('shortcode.key') ?: config('app.key'));
        $this->minLength = (int) config('shortcode.min_length', 7);
    }

    public function generate(): string
    {
        $id = $this->counter->next();

        $code = Base62::encode($this->obfuscate($id));

        return str_pad($code, $this->minLength, '0', STR_PAD_LEFT);
    }

    /**
     * Reverse a generated code back to its counter id (internal tooling only).
     */
    public function decode(string $code): int
    {
        return $this->deobfuscate(Base62::decode($code));
    }

    // -- Feistel permutation over the 48-bit domain -------------------------

    private function obfuscate(int $id): int
    {
        if ($id < 0 || $id > self::DOMAIN_MAX) {
            // Beyond the 48-bit domain the permutation stops being bijective and
            // codes could collide — fail loudly rather than wrap silently.
            throw new \RangeException("Short-code counter id out of range: {$id}");
        }

        $left = ($id >> self::HALF_BITS) & self::HALF_MASK;
        $right = $id & self::HALF_MASK;

        for ($round = 0; $round < self::ROUNDS; $round++) {
            $next = $left ^ $this->roundFunction($right, $round);
            $left = $right;
            $right = $next;
        }

        return ($left << self::HALF_BITS) | $right;
    }

    private function deobfuscate(int $value): int
    {
        $left = ($value >> self::HALF_BITS) & self::HALF_MASK;
        $right = $value & self::HALF_MASK;

        for ($round = self::ROUNDS - 1; $round >= 0; $round--) {
            $prev = $right ^ $this->roundFunction($left, $round);
            $right = $left;
            $left = $prev;
        }

        return ($left << self::HALF_BITS) | $right;
    }

    private function roundFunction(int $half, int $round): int
    {
        $digest = hash_hmac('sha256', $round.':'.$half, $this->key, true);

        return ((ord($digest[0]) << 16) | (ord($digest[1]) << 8) | ord($digest[2])) & self::HALF_MASK;
    }
}
