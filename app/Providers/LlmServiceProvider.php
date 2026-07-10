<?php

namespace App\Providers;

use App\Services\Ai\LlmProviderRegistry;
use Illuminate\Support\ServiceProvider;

class LlmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LlmProviderRegistry::class);
    }
}
