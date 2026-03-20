<?php

use Modules\MobileApi\Providers\MobileApiServiceProvider;

if (!app()->providerIsLoaded(MobileApiServiceProvider::class)) {
    app()->register(MobileApiServiceProvider::class);
}

if (app()->isBooted()) {
    app(MobileApiServiceProvider::class)->boot();
}
