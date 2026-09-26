<?php

namespace App\Support;

use App\Repositories\Contracts\AccessCodeRepositoryInterface;

class AccessCodeGenerator
{
    private const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public function __construct(
        private readonly AccessCodeRepositoryInterface $repository,
    ) {}

    /**
     * Generate a unique, human-readable access code.
     *
     * Format: USR-XXXX-XXXX  (uppercase alphanumeric segments, dash-separated)
     */
    public function generate(string $prefix = 'USR', int $segmentLength = 4, int $segments = 2): string
    {
        do {
            $parts = [$prefix];

            for ($s = 0; $s < $segments; $s++) {
                $parts[] = $this->randomSegment($segmentLength);
            }

            $code = implode('-', $parts);
        } while ($this->repository->findByCode($code) !== null);

        return $code;
    }

    private function randomSegment(int $length): string
    {
        $alphabetLength = strlen(self::ALPHABET);
        $segment = '';

        for ($i = 0; $i < $length; $i++) {
            $segment .= self::ALPHABET[random_int(0, $alphabetLength - 1)];
        }

        return $segment;
    }
}
