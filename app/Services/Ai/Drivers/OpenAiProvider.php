<?php

namespace App\Services\Ai\Drivers;

class OpenAiProvider extends OpenAiCompatibleProvider
{
    protected static function providerLabel(): string
    {
        return 'OpenAI';
    }
}
