<?php

namespace App\Filament\SuperAdmin\Resources\PlatformEmailCampaignResource\Pages;

use App\Filament\Resources\Pages\BaseViewRecord;
use App\Filament\SuperAdmin\Resources\PlatformEmailCampaignResource;
use App\Filament\SuperAdmin\Resources\PlatformEmailCampaignResource\Concerns\HasCampaignActions;
use App\Models\PlatformEmailCampaign;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;

class ViewPlatformEmailCampaign extends BaseViewRecord
{
    use HasCampaignActions;

    protected static string $resource = PlatformEmailCampaignResource::class;

    protected function getViewHeaderActions(): array
    {
        return array_merge(
            $this->campaignActions(),
            [
                Actions\EditAction::make()
                    ->visible(fn (PlatformEmailCampaign $record) => $record->isEditable()),
            ],
        );
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Basics')->schema([
                TextEntry::make('name'),
                TextEntry::make('subject'),
                TextEntry::make('preheader'),
                TextEntry::make('status')->badge(),
                TextEntry::make('list.name')->label('List'),
                TextEntry::make('ai_personalize')->label('AI')->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'On' : 'Off'),
                TextEntry::make('ai_model')->visible(fn ($record) => (bool) $record->ai_personalize),
            ])->columns(2),

            Section::make('Message')->schema([
                TextEntry::make('body_html')->label('Body')->html(),
            ])->collapsible(),

            Section::make('Counters')->schema([
                TextEntry::make('sent_count')->label('Sent')->numeric(),
                TextEntry::make('delivered_count')->label('Delivered')->numeric(),
                TextEntry::make('opened_count')->label('Opened')->numeric(),
                TextEntry::make('clicked_count')->label('Clicked')->numeric(),
                TextEntry::make('bounced_count')->label('Bounced')->numeric(),
                TextEntry::make('unsubscribed_count')->label('Unsubscribed')->numeric(),
                TextEntry::make('failed_count')->label('Failed')->numeric(),
                TextEntry::make('ai_total_cost_usd_cents')
                    ->label('AI cost')
                    ->formatStateUsing(fn ($state) => $state ? '$'.number_format(($state ?? 0) / 100, 4) : '—'),
            ])->columns(4),

            Section::make('Timeline')->schema([
                TextEntry::make('scheduled_at')->dateTime(),
                TextEntry::make('started_at')->dateTime(),
                TextEntry::make('finished_at')->dateTime(),
                TextEntry::make('created_at')->dateTime(),
            ])->columns(2)->collapsed(),
        ]);
    }
}
