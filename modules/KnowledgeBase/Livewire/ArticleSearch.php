<?php

namespace Modules\KnowledgeBase\Livewire;

use Livewire\Component;
use Livewire\Attributes\Url;
use Modules\KnowledgeBase\Services\SearchService;

class ArticleSearch extends Component
{
    #[Url(as: 'q')]
    public string $query = '';

    public array $results = [];
    public int $total = 0;
    public bool $loading = false;
    public array $suggestions = [];
    public bool $showSuggestions = false;
    public ?string $error = null;

    protected SearchService $searchService;

    public function boot(SearchService $searchService): void
    {
        $this->searchService = $searchService;
    }

    public function mount(): void
    {
        if ($this->query) {
            $this->search();
        }
    }

    public function updatedQuery(): void
    {
        if (strlen($this->query) >= 2) {
            $this->loadSuggestions();
        } else {
            $this->suggestions = [];
            $this->showSuggestions = false;
        }
    }

    protected function loadSuggestions(): void
    {
        $this->suggestions = $this->searchService->getSuggestions($this->query, 5);
        $this->showSuggestions = count($this->suggestions) > 0;
    }

    public function selectSuggestion(string $suggestion): void
    {
        $this->query = $suggestion;
        $this->showSuggestions = false;
        $this->search();
    }

    public function search(): void
    {
        $this->showSuggestions = false;
        $this->error = null;

        if (strlen($this->query) < 2) {
            $this->results = [];
            $this->total = 0;
            return;
        }

        $this->loading = true;

        $tenant = current_tenant();
        $locale = app()->getLocale();

        $response = $this->searchService->search(
            query: $this->query,
            locale: $locale,
            panel: 'tenant',
            tenantId: $tenant?->id,
            userId: auth()->id(),
            sessionId: session()->getId(),
        );

        $this->results = $response['results'];
        $this->total = $response['total'];
        $this->error = $response['error'] ?? null;

        $this->loading = false;
    }

    public function clear(): void
    {
        $this->query = '';
        $this->results = [];
        $this->total = 0;
        $this->suggestions = [];
        $this->showSuggestions = false;
        $this->error = null;
    }

    public function render()
    {
        return view('knowledgebase::livewire.article-search');
    }
}
