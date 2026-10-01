<?php

namespace App\Http\Controllers;

use App\Metrics\Metrics;
use Illuminate\Http\Response;
use Prometheus\RenderTextFormat;

/**
 * Prometheus scrape endpoint (GET /metrics). Plain-text exposition format.
 * Unauthenticated for local scraping; restrict at the network layer in cloud.
 */
class MetricsController extends Controller
{
    public function __invoke(Metrics $metrics): Response
    {
        return response($metrics->render(), 200, [
            'Content-Type' => RenderTextFormat::MIME_TYPE,
        ]);
    }
}
