<?php

namespace Tests\Feature\ShortUrl;

use App\Http\Resources\ShortUrlResource;
use App\Models\ShortUrl;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * short_url must be built from the configured redirect base (shortcode.url_base),
 * NOT from the incoming request host — the admin/API host and the redirect host are
 * deliberately different behind the k8s ingress. No DB: the model is only `make()`d.
 */
class ShortUrlResourceTest extends TestCase
{
    public function test_short_url_uses_configured_base_not_request_host(): void
    {
        config()->set('shortcode.url_base', 'http://linkforge.local:8090');

        $model = ShortUrl::factory()->make(['short_code' => 'ABC1234']);

        // A request arriving on a DIFFERENT host must not leak into the short_url.
        $request = Request::create('http://app.linkforge.local:8090/api/v1/urls', 'POST');

        $array = (new ShortUrlResource($model))->toArray($request);

        $this->assertSame('http://linkforge.local:8090/ABC1234', $array['short_url']);
    }

    public function test_short_url_has_no_double_slash_when_base_has_trailing_slash(): void
    {
        // config/shortcode.php rtrims, but guard the contract here too.
        config()->set('shortcode.url_base', 'http://linkforge.local:8090');

        $model = ShortUrl::factory()->make(['short_code' => 'XyZ9876']);

        $array = (new ShortUrlResource($model))->toArray(Request::create('/'));

        $this->assertSame('http://linkforge.local:8090/XyZ9876', $array['short_url']);
    }
}
