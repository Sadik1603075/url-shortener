<?php

namespace App\Http\Controllers;

use App\DTOs\Analytics\ClickContext;
use App\Services\ShortUrl\UrlRedirectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RedirectController extends Controller
{
    public function __construct(
        private readonly UrlRedirectService $service,
    ) {}

    public function __invoke(Request $request, string $shortCode): RedirectResponse
    {
        return $this->service->redirect(
            $shortCode,
            ClickContext::fromRequest($request),
        );
    }
}
