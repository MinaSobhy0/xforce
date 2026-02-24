<?php

namespace App\Livewire;

use Illuminate\Support\Facades\App;
use Livewire\Component;

class LanguageSwitcher extends Component
{
    public string $locale;

    public function getLocales(): array
    {
        return [
            'en' => [
                'label' => __('core::core.english'),
                'native' => 'English',
                'code' => 'EN',
            ],
            'ar' => [
                'label' => __('core::core.arabic'),
                'native' => 'العربية',
                'code' => 'AR',
            ],
        ];
    }

    public function mount(): void
    {
        $this->locale = auth()->user()?->language ?? App::getLocale();
    }

    public function setLocale(string $locale): void
    {
        if (!array_key_exists($locale, $this->getLocales())) {
            return;
        }

        $this->locale = $locale;

        // Update user's language preference
        if (auth()->check()) {
            auth()->user()->update(['language' => $locale]);
        }

        // Set session locale
        session()->put('locale', $locale);

        // Set app locale
        App::setLocale($locale);

        // Refresh the page to apply the new locale
        $this->dispatch('locale-changed', locale: $locale);

        // Redirect to refresh the page with new locale
        $this->redirect(request()->header('Referer', '/'), navigate: true);
    }

    public function render()
    {
        return view('livewire.language-switcher');
    }
}
