<?php

namespace Modules\Marketing\Filament\Pages;

use App\Models\PlatformWhatsAppTemplate;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Modules\Marketing\Models\MessageTemplate;
use Modules\Marketing\Services\TemplateAdoptionService;

/**
 * Tenant-side catalog browser. Lists active platform-managed WhatsApp
 * templates as cards; each card has an "Adopt" action that clones the
 * blueprint into the tenant's message_templates and submits to Meta.
 */
class PlatformTemplateCatalog extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'whatsapp-template-catalog';

    protected static string $view = 'marketing::filament.pages.platform-template-catalog';

    public function getTitle(): string
    {
        return (string) __('marketing::whatsapp.catalog.title');
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('marketing::whatsapp.catalog.nav_label');
    }

    public function getViewData(): array
    {
        // Central connection — these rows live in public.platform_whatsapp_templates.
        $catalog = PlatformWhatsAppTemplate::active()
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        // Build a map of which catalog templates the current tenant has
        // already adopted (lookup by platform_template_id) so the card
        // can show "Adopted ✓" instead of "Adopt".
        $tenantAdopted = MessageTemplate::query()
            ->whereIn('platform_template_id', $catalog->pluck('id'))
            ->get()
            ->keyBy('platform_template_id');

        return [
            'catalog' => $catalog,
            'adopted' => $tenantAdopted,
        ];
    }

    public function adoptAction(): Action
    {
        return Action::make('adopt')
            ->label(__('marketing::whatsapp.catalog.adopt'))
            ->icon('heroicon-o-plus-circle')
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading(__('marketing::whatsapp.catalog.adopt'))
            ->modalDescription(__('marketing::whatsapp.catalog.adopt_modal_desc'))
            ->action(function (array $arguments): void {
                $tenant = current_tenant();
                if (! $tenant) {
                    Notification::make()
                        ->title(__('marketing::whatsapp.notify.no_tenant'))
                        ->danger()->send();
                    return;
                }

                $catalogId = (int) ($arguments['platform_template_id'] ?? 0);
                $catalog = PlatformWhatsAppTemplate::find($catalogId);
                if (! $catalog) {
                    Notification::make()
                        ->title(__('marketing::whatsapp.catalog.not_found'))
                        ->danger()->send();
                    return;
                }

                $result = app(TemplateAdoptionService::class)->adopt($catalog, $tenant);

                Notification::make()
                    ->title($result['already_adopted']
                        ? __('marketing::whatsapp.catalog.already_adopted')
                        : __('marketing::whatsapp.catalog.adopted'))
                    ->body($result['already_adopted']
                        ? null
                        : __('marketing::whatsapp.catalog.adopted_body'))
                    ->success()
                    ->send();
            });
    }
}
