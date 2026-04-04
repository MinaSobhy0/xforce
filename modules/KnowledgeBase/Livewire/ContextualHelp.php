<?php

namespace Modules\KnowledgeBase\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use Modules\KnowledgeBase\Services\HelpService;

class ContextualHelp extends Component
{
    public bool $isOpen = false;
    public string $screenKey = '';
    public string $panel = 'tenant';
    public array $articles = [];
    public ?array $selectedArticle = null;
    public bool $loading = false;

    protected HelpService $helpService;

    public function boot(HelpService $helpService): void
    {
        $this->helpService = $helpService;
    }

    #[On('open-contextual-help')]
    public function openHelp(string $screenKey, string $panel = 'tenant'): void
    {
        $this->screenKey = $screenKey;
        $this->panel = $panel;
        $this->isOpen = true;
        $this->selectedArticle = null;

        $this->loadArticles();
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->selectedArticle = null;
    }

    public function loadArticles(): void
    {
        $this->loading = true;
        $locale = app()->getLocale();

        $this->articles = $this->helpService->getArticlesForScreen(
            $this->screenKey,
            $this->panel,
            $locale,
            10
        );

        $this->loading = false;
    }

    public function viewArticle(int $articleId): void
    {
        $this->loading = true;
        $locale = app()->getLocale();

        $this->selectedArticle = $this->helpService->getArticle($articleId, $locale);

        $this->loading = false;
    }

    public function backToList(): void
    {
        $this->selectedArticle = null;
    }

    public function submitFeedback(int $articleId, bool $isHelpful): void
    {
        $tenant = current_tenant();

        $this->helpService->submitFeedback(
            articleId: $articleId,
            isHelpful: $isHelpful,
            tenantId: $tenant?->id,
            userId: auth()->id(),
            sessionId: session()->getId(),
            ipAddress: request()->ip()
        );

        // Refresh article to show updated feedback status
        if ($this->selectedArticle && $this->selectedArticle['id'] === $articleId) {
            $this->selectedArticle = $this->helpService->getArticle($articleId, app()->getLocale());
        }

        $this->dispatch('feedback-submitted');
    }

    public function getUserFeedback(int $articleId): ?bool
    {
        $tenant = current_tenant();

        return $this->helpService->getUserFeedback(
            $articleId,
            $tenant?->id,
            auth()->id(),
            session()->getId()
        );
    }

    public function render()
    {
        return view('knowledgebase::livewire.contextual-help');
    }
}
