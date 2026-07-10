<?php

namespace App\Services\Ai\Drivers;

class GrokProvider extends OpenAiCompatibleProvider
{
    protected static function providerLabel(): string
    {
        return 'xAI Grok';
    }
}
