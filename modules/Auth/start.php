<?php

use Modules\Auth\Providers\AuthServiceProvider;

// Register Auth module service provider
if (!app()->providerIsLoaded(AuthServiceProvider::class)) {
    app()->register(AuthServiceProvider::class);
}

// Boot Auth module
if (app()->isBooted()) {
    app(AuthServiceProvider::class)->boot();
}