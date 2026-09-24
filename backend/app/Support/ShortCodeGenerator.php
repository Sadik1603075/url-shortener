<?php

namespace App\Support;

use App\Repositories\Contracts\ShortUrlRepositoryInterface;

class ShortCodeGenerator
{
    private const ALPHABET =
        '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public function __construct(
        private readonly ShortUrlRepositoryInterface $repository,
    ) {}

    public function generate(int $length = 7): string
    {
        do {
            $code = $this->randomCode($length);
        } while ($this->repository->findByShortCode($code) !== null);

        return $code;
    }

    private function randomCode(int $length): string
    {
        $alphabetLength = strlen(self::ALPHABET);
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= self::ALPHABET[random_int(0, $alphabetLength - 1)];
        }

        return $code;
    }
}