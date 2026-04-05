<?php

namespace Modules\Website\Services;

use Illuminate\View\View;
use Modules\Website\Models\WebsiteMenu;
use Modules\Website\Models\WebsitePage;
use Modules\Website\Models\WebsiteSetting;

class WebsiteRenderService
{
    /**
     * Render a website page.
     */
    public function renderPage(WebsitePage $page, ?string $locale = null): View
    {
        $locale = $locale ?? app()->getLocale();
        $blocks = $page->visibleBlocks()->get();

        return view('website::page', [
            'page' => $page,
            'blocks' => $blocks,
            'settings' => WebsiteSetting::getAllForTenant(),
            'headerMenu' => $this->getHeaderMenu($locale),
            'footerMenu' => $this->getFooterMenu($locale),
            'locale' => $locale,
            'isRtl' => $locale === 'ar',
            'pages' => WebsitePage::getNavigationPages(),
        ]);
    }

    /**
     * Get header menu items.
     */
    public function getHeaderMenu(?string $locale = null): array
    {
        $menu = WebsiteMenu::getByLocation('header');
        return $menu?->getFormattedItems($locale ?? app()->getLocale()) ?? [];
    }

    /**
     * Get footer menu items.
     */
    public function getFooterMenu(?string $locale = null): array
    {
        $menu = WebsiteMenu::getByLocation('footer');
        return $menu?->getFormattedItems($locale ?? app()->getLocale()) ?? [];
    }

    /**
     * Render a single block.
     */
    public function renderBlock(\Modules\Website\Models\WebsiteBlock $block, ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $viewName = 'website::blocks.' . $block->type;

        if (!view()->exists($viewName)) {
            return "<!-- Block type '{$block->type}' not found -->";
        }

        return view($viewName, [
            'block' => $block,
            'content' => $block->content ?? [],
            'settings' => $block->settings ?? [],
            'locale' => $locale,
        ])->render();
    }

    /**
     * Get SEO meta data for a page.
     */
    public function getSeoMeta(WebsitePage $page, ?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();
        $settings = WebsiteSetting::getAllForTenant();

        return [
            'title' => $page->getTranslation('meta_title', $locale)
                ?? $page->getTranslation('title', $locale)
                ?? '',
            'description' => $page->getTranslation('meta_description', $locale) ?? '',
            'og_image' => $settings['og_image'] ?? $settings['logo'] ?? null,
            'canonical' => url($page->url),
            'locale' => $locale,
        ];
    }

    /**
     * Generate dynamic CSS based on settings.
     */
    public function generateCustomCss(): string
    {
        $settings = WebsiteSetting::getAllForTenant();

        $css = ":root {\n";
        $css .= "  --color-primary: " . ($settings['primary_color'] ?? '#3B82F6') . ";\n";
        $css .= "  --color-secondary: " . ($settings['secondary_color'] ?? '#10B981') . ";\n";
        $css .= "  --color-accent: " . ($settings['accent_color'] ?? '#F59E0B') . ";\n";
        $css .= "}\n";

        // Add custom CSS from settings
        if (!empty($settings['custom_css'])) {
            $css .= "\n/* Custom CSS */\n" . $settings['custom_css'];
        }

        return $css;
    }
}
