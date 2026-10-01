<?php

namespace Tests\Unit\Support;

use App\Support\Base62;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class Base62Test extends TestCase
{
    public static function vectors(): array
    {
        return [
            [0, '0'],
            [1, '1'],
            [9, '9'],
            [10, 'A'],
            [35, 'Z'],
            [36, 'a'],
            [61, 'z'],
            [62, '10'],
            [63, '11'],
            [3843, 'zz'],   // 62^2 - 1
            [3844, '100'],  // 62^2
        ];
    }

    #[DataProvider('vectors')]
    public function test_encode_matches_known_vectors(int $number, string $expected): void
    {
        $this->assertSame($expected, Base62::encode($number));
    }

    #[DataProvider('vectors')]
    public function test_decode_matches_known_vectors(int $number, string $code): void
    {
        $this->assertSame($number, Base62::decode($code));
    }

    public function test_encode_decode_round_trips(): void
    {
        foreach ([0, 1, 61, 62, 1000, 999_999, 2 ** 32, 2 ** 48 - 1] as $n) {
            $this->assertSame($n, Base62::decode(Base62::encode($n)));
        }
    }

    public function test_leading_zero_padding_decodes_to_the_same_value(): void
    {
        $this->assertSame(62, Base62::decode('0000010'));
    }

    public function test_encode_rejects_negative_numbers(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Base62::encode(-1);
    }

    public function test_decode_rejects_illegal_characters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Base62::decode('ab-cd');
    }
}
