<?php

namespace Modules\Marketing\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Marketing\Filament\Resources\WhatsAppConversationResource\Pages;
use Modules\Marketing\Models\WhatsAppConversation;

class WhatsAppConversationResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = WhatsAppConversation::class;

    protected static ?string $moduleCode = 'marketing';

    protected static ?string $permissionKey = 'whatsapp_conversations';

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'remote_display_name';

    public static function getNavigationLabel(): string
    {
        return __('marketing::whatsapp.inbox.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('marketing::whatsapp.inbox.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('marketing::whatsapp.inbox.model_label_plural');
    }

    public static function getNavigationBadge(): ?string
    {
        $unread = static::getModel()::sum('unread_count');

        return $unread > 0 ? (string) $unread : null;
    }

    public static function canCreate(): bool
    {
        return false; // Conversations are created by inbound messages
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]); // unused — view page renders a custom thread
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_message_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('remote_display_name')
                    ->label(__('marketing::whatsapp.inbox.col_contact'))
                    ->formatStateUsing(fn ($state, WhatsAppConversation $r) => $state ?: $r->remote_phone_e164)
                    ->description(fn (WhatsAppConversation $r) => $r->remote_display_name ? $r->remote_phone_e164 : null)
                    ->searchable(['remote_display_name', 'remote_phone_e164']),

                Tables\Columns\TextColumn::make('latestMessage.body')
                    ->label(__('marketing::whatsapp.inbox.col_last_message'))
                    ->limit(60)
                    ->wrap()
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('within_window')
                    ->label(__('marketing::whatsapp.inbox.col_window'))
                    ->getStateUsing(fn (WhatsAppConversation $r) => $r->isWithinServiceWindow())
                    ->boolean()
                    ->trueIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseIcon('heroicon-o-lock-closed')
                    ->falseColor('gray')
                    ->tooltip(fn (WhatsAppConversation $r) => $r->isWithinServiceWindow()
                        ? __('marketing::whatsapp.inbox.window_open_tip')
                        : __('marketing::whatsapp.inbox.window_closed_tip')),

                Tables\Columns\TextColumn::make('unread_count')
                    ->label(__('marketing::whatsapp.inbox.col_unread'))
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'warning' : 'gray')
                    ->formatStateUsing(fn (int $state) => $state > 0 ? $state : '—'),

                Tables\Columns\TextColumn::make('last_message_at')
                    ->label(__('marketing::whatsapp.inbox.col_last_at'))
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('unread')
                    ->label(__('marketing::whatsapp.inbox.filter_unread'))
                    ->queries(
                        true: fn ($q) => $q->where('unread_count', '>', 0),
                        false: fn ($q) => $q->where('unread_count', '=', 0),
                    ),
                Filter::make('within_window')
                    ->label(__('marketing::whatsapp.inbox.filter_window'))
                    ->query(fn ($q) => $q->where('last_inbound_at', '>', now()->subHours(24))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWhatsAppConversations::route('/'),
            'view' => Pages\ViewWhatsAppConversation::route('/{record}'),
        ];
    }
}
