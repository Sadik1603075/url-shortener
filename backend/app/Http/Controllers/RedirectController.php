<?php

namespace App\Http\Controllers;

use App\Services\ShortUrl\UrlRedirectService;
use Illuminate\Http\RedirectResponse;

class RedirectController extends Controller
{
    public function __construct(
        private readonly UrlRedirectService $service,
    ) {}

    public function __invoke(string $shortCode): RedirectResponse
    {
        return $this->service->redirect($shortCode);
    }
}