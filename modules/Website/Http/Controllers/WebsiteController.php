<?php

namespace Modules\Website\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Website\Models\WebsiteMenu;
use Modules\Website\Models\WebsitePage;
use Modules\Website\Models\WebsiteSetting;
use Modules\Website\Services\WebsiteRenderService;

class WebsiteController extends Controller
{
    public function __construct(
        protected WebsiteRenderService $renderService
    ) {}

    /**
     * Handle the home/root route.
     */
    public function home(Request $request): View
    {
        // Check if middleware flagged this as a website render
        if ($request->attributes->get('render_website')) {
            $tenant = $request->attributes->get('tenant');

            // Check if tenant has a published homepage
            $homepage = WebsitePage::where('is_homepage', true)
                ->where('is_published', true)
                ->first();

            if ($homepage) {
                return $this->renderPage($homepage, $request);
            }

            // No homepage created yet - fall back to platform landing
        }

        // Show platform landing
        return view('landing');
    }

    /**
     * Show a specific page by slug.
     */
    public function show(Request $request, string $slug): View
    {
        // Check if middleware flagged this as a website render
        if (!$request->attributes->get('render_website')) {
            abort(404);
        }

        // Find the page
        $page = WebsitePage::where('slug', $slug)
            ->where('is_published', true)
            ->first();

        if (!$page) {
            abort(404);
        }

        return $this->renderPage($page, $request);
    }

    /**
     * Render a website page.
     */
    protected function renderPage(WebsitePage $page, Request $request): View
    {
        $locale = $this->detectLocale($request);
        app()->setLocale($locale);

        $blocks = $page->visibleBlocks()->get();
        $settings = WebsiteSetting::getAllForTenant();
        $headerMenu = WebsiteMenu::getByLocation('header');
        $footerMenu = WebsiteMenu::getByLocation('footer');

        return view('website::page', [
            'page' => $page,
            'blocks' => $blocks,
            'settings' => $settings,
            'headerMenu' => $headerMenu?->getFormattedItems($locale) ?? [],
            'footerMenu' => $footerMenu?->getFormattedItems($locale) ?? [],
            'locale' => $locale,
            'isRtl' => $locale === 'ar',
            'tenant' => $request->attributes->get('tenant'),
            'pages' => WebsitePage::getNavigationPages(),
        ]);
    }

    /**
     * Detect locale from request.
     */
    protected function detectLocale(Request $request): string
    {
        // Check query parameter
        if ($locale = $request->query('lang')) {
            if (in_array($locale, ['en', 'ar'])) {
                session(['website_locale' => $locale]);
                return $locale;
            }
        }

        // Check session
        if ($locale = session('website_locale')) {
            return $locale;
        }

        // Check Accept-Language header
        $acceptLanguage = $request->header('Accept-Language', '');
        if (str_contains($acceptLanguage, 'ar')) {
            return 'ar';
        }

        // Default to tenant's default locale or English
        $tenant = $request->attributes->get('tenant');
        return $tenant?->settings['default_locale'] ?? 'en';
    }
}
