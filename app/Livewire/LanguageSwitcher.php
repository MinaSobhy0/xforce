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
        session()->save();

        // Set app locale
        App::setLocale($locale);

        // Use JS to force a full page reload
        $this->js('window.location.reload()');
    }

    public function render()
    {
        return view('livewire.language-switcher');
    }
}
