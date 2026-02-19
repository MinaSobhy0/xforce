<?php

use Modules\Core\Providers\CoreServiceProvider;

// Register Core module service provider
if (!app()->providerIsLoaded(CoreServiceProvider::class)) {
    app()->register(CoreServiceProvider::class);
}

// Boot Core module
if (app()->isBooted()) {
    app(CoreServiceProvider::class)->boot();
}