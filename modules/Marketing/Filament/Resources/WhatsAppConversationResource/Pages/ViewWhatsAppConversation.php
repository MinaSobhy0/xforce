<?php

namespace Modules\Marketing\Filament\Resources\WhatsAppConversationResource\Pages;

use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Modules\Marketing\Filament\Resources\WhatsAppConversationResource;
use Modules\Marketing\Models\MessageTemplate;
use Modules\Marketing\Models\WhatsAppConversation;
use Modules\Marketing\Services\WhatsAppService;

class ViewWhatsAppConversation extends ViewRecord
{
    protected static string $resource = WhatsAppConversationResource::class;

    protected static string $view = 'marketing::filament.pages.whatsapp-conversation';

    /** Poll-refresh every 5s so new inbound messages appear in the thread. */
    protected ?string $pollingInterval = '5s';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Opening the conversation clears the unread badge.
        if ($this->record->unread_count > 0) {
            $this->record->update(['unread_count' => 0]);
        }
    }

    public function getTitle(): string
    {
        /** @var WhatsAppConversation $r */
        $r = $this->record;

        return $r->remote_display_name ?: $r->remote_phone_e164;
    }

    protected function getHeaderActions(): array
    {
        /** @var WhatsAppConversation $r */
        $r = $this->record;

        return [
            Action::make('reply_freeform')
                ->label(__('marketing::whatsapp.inbox.reply_freeform'))
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->visible(fn () => $r->isWithinServiceWindow())
                ->form([
                    Forms\Components\Textarea::make('body')
                        ->label(__('marketing::whatsapp.inbox.reply_placeholder'))
                        ->required()
                        ->rows(3)
                        ->maxLength(4096),
                ])
                ->action(function (array $data) use ($r): void {
                    $result = app(WhatsAppService::class)->sendTextMessage($r->remote_phone_e164, $data['body']);

                    $this->surfaceSendResult($result);
                }),

            Action::make('reply_template')
                ->label(__('marketing::whatsapp.inbox.reply_template'))
                ->icon('heroicon-o-document-text')
                ->color($r->isWithinServiceWindow() ? 'gray' : 'warning')
                ->modalHeading(__('marketing::whatsapp.inbox.reply_template'))
                ->modalDescription(fn () => $r->isWithinServiceWindow()
                    ? null
                    : __('marketing::whatsapp.inbox.window_expired_help'))
                ->form([
                    Forms\Components\Select::make('template_id')
                        ->label(__('marketing::whatsapp.inbox.choose_template'))
                        ->options(fn () => MessageTemplate::query()
                            ->where('channel', MessageTemplate::CHANNEL_WHATSAPP)
                            ->where('is_active', true)
                            ->whereNotNull('whatsapp_template_name')
                            ->pluck('whatsapp_template_name', 'id')
                            ->toArray())
                        ->required()
                        ->searchable(),
                    Forms\Components\TextInput::make('language')
                        ->label(__('marketing::whatsapp.test.template_language'))
                        ->default('en_US')
                        ->required(),
                ])
                ->action(function (array $data) use ($r): void {
                    $template = MessageTemplate::find($data['template_id']);
                    if (! $template) {
                        $this->surfaceSendResult(['success' => false, 'error' => 'Template not found']);
                        return;
                    }

                    $result = app(WhatsAppService::class)->sendTemplateMessage(
                        $r->remote_phone_e164,
                        $template->whatsapp_template_name,
                        $data['language'] ?: 'en_US'
                    );

                    $this->surfaceSendResult($result);
                }),
        ];
    }

    protected function surfaceSendResult(array $result): void
    {
        if ($result['success'] ?? false) {
            Notification::make()
                ->title(__('marketing::whatsapp.inbox.send_success'))
                ->body(($result['message_id'] ?? null) ? "wamid: {$result['message_id']}" : null)
                ->success()
                ->send();

            // Refresh so the just-sent message appears in the thread.
            $this->record->refresh();
        } else {
            $body = $result['error'] ?? 'Unknown error';
            if (! empty($result['error_code'])) {
                $body = "[code {$result['error_code']}] {$body}";
            }
            Notification::make()
                ->title(__('marketing::whatsapp.inbox.send_failed'))
                ->body($body)
                ->danger()
                ->persistent()
                ->send();
        }
    }
}
