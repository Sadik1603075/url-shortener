<?php

namespace App\Providers;

use App\Repositories\Contracts\AccessCodeRepositoryInterface;
use App\Repositories\Contracts\ClickAnalyticsRepositoryInterface;
use App\Repositories\Contracts\ShortUrlRepositoryInterface;
use App\Repositories\Eloquent\EloquentAccessCodeRepository;
use App\Repositories\Eloquent\EloquentClickAnalyticsRepository;
use App\Repositories\Eloquent\EloquentShortUrlRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ShortUrlRepositoryInterface::class,
            EloquentShortUrlRepository::class
        );

        $this->app->bind(
            AccessCodeRepositoryInterface::class,
            EloquentAccessCodeRepository::class
        );

        $this->app->bind(
            ClickAnalyticsRepositoryInterface::class,
            EloquentClickAnalyticsRepository::class
        );
    }

    public function boot(): void
    {
        //
    }
}
