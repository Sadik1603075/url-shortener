<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Base62 integer codec (alphabet 0-9A-Za-z).
 *
 * Pure and reversible: decode(encode($n)) === $n for every non-negative int.
 * Used to render an obfuscated counter id as a short, URL-safe code.
 */
final class Base62
{
    public const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    private const BASE = 62;

    public static function encode(int $number): string
    {
        if ($number < 0) {
            throw new InvalidArgumentException('Base62 can only encode non-negative integers.');
        }

        if ($number === 0) {
            return '0';
        }

        $encoded = '';

        while ($number > 0) {
            $encoded = self::ALPHABET[$number % self::BASE].$encoded;
            $number = intdiv($number, self::BASE);
        }

        return $encoded;
    }

    public static function decode(string $code): int
    {
        if ($code === '') {
            throw new InvalidArgumentException('Cannot decode an empty string.');
        }

        $number = 0;

        for ($i = 0, $len = strlen($code); $i < $len; $i++) {
            $position = strpos(self::ALPHABET, $code[$i]);

            if ($position === false) {
                throw new InvalidArgumentException("Illegal base62 character: {$code[$i]}");
            }

            $number = $number * self::BASE + $position;
        }

        return $number;
    }
}
