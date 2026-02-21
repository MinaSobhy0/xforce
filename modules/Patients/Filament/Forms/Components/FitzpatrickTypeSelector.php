<?php

namespace Modules\Patients\Filament\Forms\Components;

use Filament\Forms\Components\Field;

class FitzpatrickTypeSelector extends Field
{
    protected string $view = 'patients::filament.forms.components.fitzpatrick-type-selector';

    /**
     * Fitzpatrick scale definitions with colors and descriptions.
     */
    public static array $types = [
        'I' => [
            'label' => 'Type I',
            'color' => '#FAE0D4',
            'description' => 'Very fair skin, always burns, never tans',
            'characteristics' => 'Very light or pale white, often with freckles',
            'sun_response' => 'Always burns, never tans',
        ],
        'II' => [
            'label' => 'Type II',
            'color' => '#F5D0B5',
            'description' => 'Fair skin, burns easily, tans minimally',
            'characteristics' => 'White to light beige',
            'sun_response' => 'Burns easily, tans with difficulty',
        ],
        'III' => [
            'label' => 'Type III',
            'color' => '#D9A578',
            'description' => 'Medium skin, sometimes burns, tans uniformly',
            'characteristics' => 'Beige to light brown',
            'sun_response' => 'Sometimes mild burn, tans uniformly',
        ],
        'IV' => [
            'label' => 'Type IV',
            'color' => '#C68642',
            'description' => 'Olive skin, rarely burns, tans easily',
            'characteristics' => 'Light brown to olive',
            'sun_response' => 'Rarely burns, tans with ease',
        ],
        'V' => [
            'label' => 'Type V',
            'color' => '#8D5524',
            'description' => 'Brown skin, very rarely burns',
            'characteristics' => 'Brown',
            'sun_response' => 'Very rarely burns, tans very easily',
        ],
        'VI' => [
            'label' => 'Type VI',
            'color' => '#5C3317',
            'description' => 'Dark brown/black skin, never burns',
            'characteristics' => 'Dark brown to black',
            'sun_response' => 'Never burns, deeply pigmented',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->default('III');
    }

    /**
     * Get the Fitzpatrick type options for the view.
     */
    public function getTypes(): array
    {
        return self::$types;
    }

    /**
     * Get the translated label for a type.
     */
    public static function getLabelForType(string $type): string
    {
        return self::$types[$type]['label'] ?? $type;
    }

    /**
     * Get the color for a type.
     */
    public static function getColorForType(string $type): string
    {
        return self::$types[$type]['color'] ?? '#D9A578';
    }
}
