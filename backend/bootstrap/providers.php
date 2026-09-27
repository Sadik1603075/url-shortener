<?php

use App\Providers\AppServiceProvider;
use App\Providers\MessagingServiceProvider;
use App\Providers\RepositoryServiceProvider;

return [
    AppServiceProvider::class,
    RepositoryServiceProvider::class,
    MessagingServiceProvider::class,
];
