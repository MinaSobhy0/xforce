<?php

namespace App\Livewire;

use Livewire\Component;

class ThemeSwitcher extends Component
{
    public string $theme;

    public function mount(): void
    {
        $this->theme = $this->getUserTheme();
    }

    public function setTheme(string $theme): void
    {
        if (! in_array($theme, ['light', 'dark', 'system'])) {
            return;
        }

        $this->theme = $theme;

        // Update user's theme preference in database
        if (auth()->check()) {
            auth()->user()->setSetting('dashboard.theme', $theme);
        }

        // Dispatch browser event to update the UI
        $this->dispatch('theme-changed', theme: $theme);
    }

    /**
     * Get the user's theme preference from database or default.
     */
    protected function getUserTheme(): string
    {
        if (auth()->check()) {
            return auth()->user()->getSetting('dashboard.theme', 'light');
        }

        return filament()->getDefaultThemeMode()->value ?? 'light';
    }

    public function render()
    {
        return view('livewire.theme-switcher');
    }
}
