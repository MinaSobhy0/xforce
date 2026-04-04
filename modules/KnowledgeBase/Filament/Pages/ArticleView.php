<?php

namespace Modules\KnowledgeBase\Filament\Pages;

use Filament\Pages\Page;
use Modules\KnowledgeBase\Services\HelpService;

class ArticleView extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'knowledgebase::filament.pages.article-view';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'help/{slug}';

    public ?array $article = null;
    public ?bool $userFeedback = null;
    public string $articleSlug = '';

    public function getTitle(): string
    {
        return $this->article['title'] ?? __('knowledgebase::knowledgebase.article');
    }

    public function getBreadcrumbs(): array
    {
        $breadcrumbs = [
            route('filament.tenant.pages.knowledge-base') => __('knowledgebase::knowledgebase.help_center'),
        ];

        if ($this->article && $this->article['category']) {
            $breadcrumbs['#'] = $this->article['category']['name'];
        }

        if ($this->article) {
            $breadcrumbs[''] = $this->article['title'];
        }

        return $breadcrumbs;
    }

    public function mount(string $slug): void
    {
        $this->articleSlug = $slug;

        $helpService = app(HelpService::class);
        $locale = app()->getLocale();

        $this->article = $helpService->getArticle($slug, $locale);

        if (!$this->article) {
            abort(404);
        }

        // Get user's existing feedback
        $tenant = current_tenant();
        $this->userFeedback = $helpService->getUserFeedback(
            $this->article['id'],
            $tenant?->id,
            auth()->id(),
            session()->getId()
        );
    }

    public function submitFeedback(bool $isHelpful): void
    {
        $helpService = app(HelpService::class);
        $tenant = current_tenant();

        $helpService->submitFeedback(
            articleId: $this->article['id'],
            isHelpful: $isHelpful,
            tenantId: $tenant?->id,
            userId: auth()->id(),
            sessionId: session()->getId(),
            ipAddress: request()->ip()
        );

        $this->userFeedback = $isHelpful;

        // Refresh article to get updated counts
        $locale = app()->getLocale();
        $this->article = $helpService->getArticle($this->articleSlug, $locale);
    }
}
