<?php

namespace App\Support\Contracts;

interface ShortCodeCounterInterface
{
    /**
     * Return the next value of a monotonic, unique counter.
     *
     * This is the only external dependency of short-code generation — there is
     * no collision lookup (uniqueness is guaranteed by the counter + a bijective
     * obfuscation), per ADR-0001.
     */
    public function next(): int;
}
