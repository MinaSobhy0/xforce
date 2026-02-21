<?php

namespace Modules\Patients\Filament\Forms\Components;

use Filament\Forms\Components\Field;

class SignaturePad extends Field
{
    protected string $view = 'patients::filament.forms.components.signature-pad';

    /**
     * Canvas width.
     */
    protected int $canvasWidth = 500;

    /**
     * Canvas height.
     */
    protected int $canvasHeight = 200;

    /**
     * Stroke color.
     */
    protected string $strokeColor = '#1e40af';

    /**
     * Stroke width.
     */
    protected int $strokeWidth = 2;

    /**
     * Background color.
     */
    protected string $backgroundColor = '#ffffff';

    /**
     * Whether to show clear button.
     */
    protected bool $showClearButton = true;

    /**
     * Whether signature is required.
     */
    protected bool $signatureRequired = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dehydrateStateUsing(static function (?string $state): ?string {
            // Return the base64 data URL
            return $state;
        });
    }

    /**
     * Set the canvas width.
     */
    public function canvasWidth(int $width): static
    {
        $this->canvasWidth = $width;

        return $this;
    }

    /**
     * Get the canvas width.
     */
    public function getCanvasWidth(): int
    {
        return $this->canvasWidth;
    }

    /**
     * Set the canvas height.
     */
    public function canvasHeight(int $height): static
    {
        $this->canvasHeight = $height;

        return $this;
    }

    /**
     * Get the canvas height.
     */
    public function getCanvasHeight(): int
    {
        return $this->canvasHeight;
    }

    /**
     * Set the stroke color.
     */
    public function strokeColor(string $color): static
    {
        $this->strokeColor = $color;

        return $this;
    }

    /**
     * Get the stroke color.
     */
    public function getStrokeColor(): string
    {
        return $this->strokeColor;
    }

    /**
     * Set the stroke width.
     */
    public function strokeWidth(int $width): static
    {
        $this->strokeWidth = $width;

        return $this;
    }

    /**
     * Get the stroke width.
     */
    public function getStrokeWidth(): int
    {
        return $this->strokeWidth;
    }

    /**
     * Set the background color.
     */
    public function backgroundColor(string $color): static
    {
        $this->backgroundColor = $color;

        return $this;
    }

    /**
     * Get the background color.
     */
    public function getBackgroundColor(): string
    {
        return $this->backgroundColor;
    }

    /**
     * Show or hide the clear button.
     */
    public function showClearButton(bool $show = true): static
    {
        $this->showClearButton = $show;

        return $this;
    }

    /**
     * Get whether to show clear button.
     */
    public function getShowClearButton(): bool
    {
        return $this->showClearButton;
    }

    /**
     * Mark signature as required.
     */
    public function signatureRequired(bool $required = true): static
    {
        $this->signatureRequired = $required;

        return $this;
    }

    /**
     * Get whether signature is required.
     */
    public function getSignatureRequired(): bool
    {
        return $this->signatureRequired;
    }

    /**
     * Check if a signature data URL is valid (not empty canvas).
     */
    public static function isValidSignature(?string $dataUrl): bool
    {
        if (empty($dataUrl)) {
            return false;
        }

        // Check if it starts with a valid data URL prefix
        if (!str_starts_with($dataUrl, 'data:image/png;base64,')) {
            return false;
        }

        return true;
    }

    /**
     * Extract raw base64 data from data URL.
     */
    public static function extractBase64(string $dataUrl): ?string
    {
        if (preg_match('/^data:image\/\w+;base64,(.+)$/', $dataUrl, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
