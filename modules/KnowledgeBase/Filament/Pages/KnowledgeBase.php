<?php

namespace Modules\KnowledgeBase\Filament\Pages;

use Filament\Pages\Page;
use Modules\KnowledgeBase\Services\HelpService;

class KnowledgeBase extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static string $view = 'knowledgebase::filament.pages.knowledge-base';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 95;

    protected static ?string $slug = 'knowledge-base';

    public array $categories = [];
    public array $featuredArticles = [];
    public ?string $selectedCategory = null;
    public array $categoryArticles = [];

    public function getTitle(): string
    {
        return __('knowledgebase::knowledgebase.knowledge_base');
    }

    public static function getNavigationLabel(): string
    {
        return __('knowledgebase::knowledgebase.help_center');
    }

    public function mount(): void
    {
        $helpService = app(HelpService::class);
        $locale = app()->getLocale();

        $this->categories = $helpService->getCategories($locale);
        $this->featuredArticles = $helpService->getFeaturedArticles($locale, 6);
    }

    public function selectCategory(string $slug): void
    {
        $this->selectedCategory = $slug;

        $helpService = app(HelpService::class);
        $locale = app()->getLocale();

        $this->categoryArticles = $helpService->getArticlesInCategory($slug, $locale, 20);
    }

    public function clearCategory(): void
    {
        $this->selectedCategory = null;
        $this->categoryArticles = [];
    }
}
