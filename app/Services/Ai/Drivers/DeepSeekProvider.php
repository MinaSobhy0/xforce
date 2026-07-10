<?php

namespace App\Services\Ai\Drivers;

class DeepSeekProvider extends OpenAiCompatibleProvider
{
    protected static function providerLabel(): string
    {
        return 'DeepSeek';
    }
}
