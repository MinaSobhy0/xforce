<?php

namespace Modules\KnowledgeBase\Livewire;

use Livewire\Component;
use Modules\KnowledgeBase\Services\HelpService;
use Modules\KnowledgeBase\Services\ScreenMappingService;

class HelpButton extends Component
{
    public string $screenKey = '';
    public string $panel = 'tenant';
    public bool $showDropdown = false;
    public bool $hasGuide = false;
    public int $articlesCount = 0;

    protected HelpService $helpService;
    protected ScreenMappingService $screenMappingService;

    public function boot(HelpService $helpService, ScreenMappingService $screenMappingService): void
    {
        $this->helpService = $helpService;
        $this->screenMappingService = $screenMappingService;
    }

    public function mount(?string $screenKey = null, ?string $panel = null): void
    {
        $this->screenKey = $screenKey ?? $this->screenMappingService->getCurrentScreenKey() ?? '';
        $this->panel = $panel ?? $this->screenMappingService->getCurrentPanel();

        $this->loadContextInfo();
    }

    protected function loadContextInfo(): void
    {
        if (!$this->screenKey) {
            return;
        }

        $locale = app()->getLocale();

        // Check if guide exists
        $guide = $this->helpService->getGuideForScreen($this->screenKey, $this->panel, $locale);
        $this->hasGuide = $guide !== null;

        // Count articles for this screen
        $articles = $this->helpService->getArticlesForScreen($this->screenKey, $this->panel, $locale);
        $this->articlesCount = count($articles);
    }

    public function toggleDropdown(): void
    {
        $this->showDropdown = !$this->showDropdown;
    }

    public function closeDropdown(): void
    {
        $this->showDropdown = false;
    }

    public function startGuide(): void
    {
        $this->showDropdown = false;
        $this->dispatch('start-guide', screenKey: $this->screenKey, panel: $this->panel);
    }

    public function openHelp(): void
    {
        $this->showDropdown = false;
        $this->dispatch('open-contextual-help', screenKey: $this->screenKey, panel: $this->panel);
    }

    public function openKnowledgeBase(): void
    {
        $this->showDropdown = false;
        // Navigate to knowledge base page
        $this->redirect(route('filament.tenant.pages.knowledge-base'));
    }

    public function render()
    {
        return view('knowledgebase::livewire.help-button');
    }
}
