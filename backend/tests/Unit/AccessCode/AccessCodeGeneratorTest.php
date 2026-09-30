<?php

namespace Tests\Unit\AccessCode;

use App\Models\AccessCode;
use App\Repositories\Contracts\AccessCodeRepositoryInterface;
use App\Support\AccessCodeGenerator;
use Mockery;
use Tests\TestCase;

class AccessCodeGeneratorTest extends TestCase
{
    private const FORMAT = '/^USR-[0-9A-Z]{4}-[0-9A-Z]{4}$/';

    public function test_generates_a_code_in_the_expected_format(): void
    {
        $repo = Mockery::mock(AccessCodeRepositoryInterface::class);
        $repo->shouldReceive('findByCode')->once()->andReturnNull();

        $code = (new AccessCodeGenerator($repo))->generate();

        $this->assertMatchesRegularExpression(self::FORMAT, $code);
    }

    public function test_retries_until_it_finds_an_unused_code(): void
    {
        // Proves the uniqueness guarantee: the first candidate collides with an
        // existing code, so the generator must loop and produce another. `twice()`
        // fails if the collision check is removed (the loop would run once, or not
        // call the repo at all).
        $repo = Mockery::mock(AccessCodeRepositoryInterface::class);
        $repo->shouldReceive('findByCode')
            ->twice()
            ->andReturn(new AccessCode(['code' => 'USR-XXXX-XXXX']), null);

        $code = (new AccessCodeGenerator($repo))->generate();

        $this->assertMatchesRegularExpression(self::FORMAT, $code);
    }
}
