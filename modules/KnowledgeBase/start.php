<?php

use Modules\KnowledgeBase\Providers\KnowledgeBaseServiceProvider;

if (!app()->providerIsLoaded(KnowledgeBaseServiceProvider::class)) {
    app()->register(KnowledgeBaseServiceProvider::class);
}

if (app()->isBooted()) {
    app(KnowledgeBaseServiceProvider::class)->boot();
}
