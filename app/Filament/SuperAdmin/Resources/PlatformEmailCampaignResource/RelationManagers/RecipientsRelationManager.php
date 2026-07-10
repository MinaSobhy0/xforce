<?php

namespace App\Filament\SuperAdmin\Resources\PlatformEmailCampaignResource\RelationManagers;

use App\Jobs\SendPlatformCampaignEmailJob;
use App\Models\PlatformEmailCampaignRecipient;
use App\Models\PlatformEmailSuppression;
use App\Services\Ai\AiEmailPersonalizer;
use App\Services\Ai\LlmProviderRegistry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    protected static ?string $recordTitleAttribute = 'email';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name_hint')->wrap()->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => PlatformEmailCampaignRecipient::STATUS_PENDING,
                        'info' => PlatformEmailCampaignRecipient::STATUS_RENDERING,
                        'warning' => PlatformEmailCampaignRecipient::STATUS_SENDING,
                        'success' => PlatformEmailCampaignRecipient::STATUS_SENT,
                        'success' => PlatformEmailCampaignRecipient::STATUS_DELIVERED,
                        'danger' => PlatformEmailCampaignRecipient::STATUS_FAILED,
                        'danger' => PlatformEmailCampaignRecipient::STATUS_BOUNCED,
                    ])
                    ->formatStateUsing(fn ($state) => PlatformEmailCampaignRecipient::STATUSES[$state] ?? $state),
                Tables\Columns\IconColumn::make('rendered_body_html')
                    ->label('Body')
                    ->boolean()
                    ->trueIcon('heroicon-o-document-text')
                    ->falseIcon('heroicon-o-minus')
                    ->tooltip(fn ($record) => $record->rendered_body_html ? 'Personalized copy ready — click Edit to review' : 'Not yet rendered — click Render preview'),
                Tables\Columns\TextColumn::make('sent_at')->dateTime()->toggleable(),
                Tables\Columns\TextColumn::make('opened_at')->dateTime()->toggleable(),
                Tables\Columns\TextColumn::make('first_clicked_at')->label('Clicked at')->dateTime()->toggleable(),
                Tables\Columns\TextColumn::make('ai_cost_usd_cents')
                    ->label('AI $')
                    ->formatStateUsing(fn ($state) => $state ? '$'.number_format(($state ?? 0) / 100, 4) : '—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('error_message')->wrap()->limit(50)->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(PlatformEmailCampaignRecipient::STATUSES),
            ])
            ->headerActions([
                // Bulk-render every un-rendered recipient so the whole
                // batch can be reviewed before Send Now fires. Skips
                // recipients that already have rendered_body_html.
                Tables\Actions\Action::make('render_all_pending')
                    ->label('Render previews for pending')
                    ->icon('heroicon-o-sparkles')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn () => $this->ownerRecord->ai_personalize && count(LlmProviderRegistry::available()) > 0)
                    ->modalDescription(fn () => 'Runs the AI on every recipient that hasn\'t been rendered yet. Skips already-rendered rows. Cost per recipient is what the AI Providers page shows for the campaign\'s model.')
                    ->action(function (): void {
                        $this->renderAllPending();
                    }),
            ])
            ->actions([
                // Render preview for this single recipient (fills
                // rendered_body_html so Edit Body can then be used).
                Tables\Actions\Action::make('render_preview')
                    ->label('Render preview')
                    ->icon('heroicon-o-sparkles')
                    ->color('info')
                    ->visible(fn (PlatformEmailCampaignRecipient $record) =>
                        $this->ownerRecord->ai_personalize
                        && ! $record->rendered_body_html
                        && ! in_array($record->status, [
                            PlatformEmailCampaignRecipient::STATUS_SENT,
                            PlatformEmailCampaignRecipient::STATUS_DELIVERED,
                            PlatformEmailCampaignRecipient::STATUS_UNSUBSCRIBED,
                        ], true)
                        && count(LlmProviderRegistry::available()) > 0)
                    ->action(function (PlatformEmailCampaignRecipient $record): void {
                        $this->renderOne($record);
                    }),

                // Edit the rendered body for this recipient. Uses a
                // RichEditor so the admin can tweak the AI's output
                // before it goes out. Also editable AFTER a send has
                // failed — retrying will use the corrected body.
                Tables\Actions\Action::make('edit_body')
                    ->label('Edit body')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->visible(fn (PlatformEmailCampaignRecipient $record) =>
                        (bool) $record->rendered_body_html
                        && ! in_array($record->status, [
                            PlatformEmailCampaignRecipient::STATUS_SENT,
                            PlatformEmailCampaignRecipient::STATUS_DELIVERED,
                            PlatformEmailCampaignRecipient::STATUS_UNSUBSCRIBED,
                        ], true))
                    ->modalWidth('4xl')
                    ->modalHeading(fn (PlatformEmailCampaignRecipient $record) => 'Edit copy for '.$record->email)
                    ->fillForm(fn (PlatformEmailCampaignRecipient $record) => [
                        'rendered_body_html' => $record->rendered_body_html,
                    ])
                    ->form([
                        Forms\Components\RichEditor::make('rendered_body_html')
                            ->label('Personalized body')
                            ->toolbarButtons(['bold', 'italic', 'underline', 'link', 'orderedList', 'bulletList', 'h2', 'h3', 'blockquote', 'undo', 'redo'])
                            ->helperText('This exact copy is what will be sent to this recipient. Empties reset to the AI\'s original render.')
                            ->required(),
                    ])
                    ->action(function (array $data, PlatformEmailCampaignRecipient $record): void {
                        $record->update([
                            'rendered_body_html' => $data['rendered_body_html'],
                        ]);
                        Notification::make()->title('Copy updated')->success()->send();
                    }),

                // Send just this one recipient — bypasses the batch
                // flow for spot-testing after an edit.
                Tables\Actions\Action::make('send_this_one')
                    ->label('Send this one')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PlatformEmailCampaignRecipient $record) => in_array($record->status, [
                        PlatformEmailCampaignRecipient::STATUS_PENDING,
                        PlatformEmailCampaignRecipient::STATUS_FAILED,
                    ], true))
                    ->action(function (PlatformEmailCampaignRecipient $record): void {
                        $record->update(['status' => PlatformEmailCampaignRecipient::STATUS_PENDING]);
                        SendPlatformCampaignEmailJob::dispatch($record->id);
                        Notification::make()->title('Send queued for '.$record->email)->success()->send();
                    }),

                Tables\Actions\Action::make('mark_bounced')
                    ->label('Mark bounced')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Adds this address to the global suppression list. Future campaigns skip it silently.')
                    ->visible(fn ($record) => in_array($record->status, [
                        PlatformEmailCampaignRecipient::STATUS_SENT,
                        PlatformEmailCampaignRecipient::STATUS_DELIVERED,
                        PlatformEmailCampaignRecipient::STATUS_FAILED,
                    ], true))
                    ->action(function ($record): void {
                        PlatformEmailSuppression::add(
                            email: $record->email,
                            reason: PlatformEmailSuppression::REASON_BOUNCED,
                            extra: ['campaign_id' => $record->campaign_id, 'added_by_user_id' => auth()->id()],
                        );
                        $record->update(['status' => PlatformEmailCampaignRecipient::STATUS_BOUNCED]);
                        $record->campaign?->increment('bounced_count');
                        Notification::make()->title('Suppressed')->success()->send();
                    }),

                Tables\Actions\Action::make('mark_complained')
                    ->label('Mark spam complaint')
                    ->icon('heroicon-o-flag')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Same as bounce, but flagged as spam complaint (worse for reputation — do only when a real complaint has arrived).')
                    ->visible(fn ($record) => in_array($record->status, [
                        PlatformEmailCampaignRecipient::STATUS_SENT,
                        PlatformEmailCampaignRecipient::STATUS_DELIVERED,
                    ], true))
                    ->action(function ($record): void {
                        PlatformEmailSuppression::add(
                            email: $record->email,
                            reason: PlatformEmailSuppression::REASON_COMPLAINED,
                            extra: ['campaign_id' => $record->campaign_id, 'added_by_user_id' => auth()->id()],
                        );
                        $record->update(['status' => PlatformEmailCampaignRecipient::STATUS_BOUNCED]);
                        $record->campaign?->increment('complained_count');
                        Notification::make()->title('Complaint recorded — address suppressed')->success()->send();
                    }),
            ])
            ->defaultSort('id')
            ->paginated([25, 50, 100]);
    }

    /**
     * Render one recipient's personalized copy via the AI. Writes
     * the result to rendered_body_html so it can be reviewed and
     * edited before the send job fires.
     */
    protected function renderOne(PlatformEmailCampaignRecipient $recipient): void
    {
        $campaign = $this->ownerRecord;
        try {
            $personalizer = app(AiEmailPersonalizer::class);
            $response = $personalizer->personalize(
                brief: (string) $campaign->body_html,
                promptExtra: (string) $campaign->ai_prompt_template,
                recipient: ['email' => $recipient->email, 'name_hint' => $recipient->name_hint],
                context: (array) $recipient->context,
                modelKey: $campaign->ai_model,
                language: (string) ($campaign->language ?: 'en'),
            );
            $recipient->forceFill([
                'rendered_body_html' => $response->text,
                'rendered_at' => now(),
                'ai_tokens_input' => $response->tokensInput,
                'ai_tokens_output' => $response->tokensOutput,
                'ai_cost_usd_cents' => $response->costUsdCents,
            ])->save();
            $campaign->increment('ai_total_cost_usd_cents', (int) $response->costUsdCents);
            Notification::make()->title('Preview rendered')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('AI render failed')
                ->body(mb_substr($e->getMessage(), 0, 200))
                ->danger()->send();
        }
    }

    /**
     * Bulk-render every un-rendered recipient (up to 200 at a time
     * to avoid runaway loops). Runs synchronously — for larger lists
     * we'd queue jobs, but at this scale it's a couple of seconds.
     */
    protected function renderAllPending(): void
    {
        $campaign = $this->ownerRecord;
        $pending = $campaign->recipients()
            ->whereNull('rendered_body_html')
            ->whereNotIn('status', [
                PlatformEmailCampaignRecipient::STATUS_SENT,
                PlatformEmailCampaignRecipient::STATUS_DELIVERED,
                PlatformEmailCampaignRecipient::STATUS_UNSUBSCRIBED,
            ])
            ->limit(200)
            ->get();

        if ($pending->isEmpty()) {
            Notification::make()->title('Nothing to render — every recipient already has a preview')->send();
            return;
        }

        $personalizer = app(AiEmailPersonalizer::class);
        $ok = 0;
        $failed = 0;
        foreach ($pending as $recipient) {
            try {
                $response = $personalizer->personalize(
                    brief: (string) $campaign->body_html,
                    promptExtra: (string) $campaign->ai_prompt_template,
                    recipient: ['email' => $recipient->email, 'name_hint' => $recipient->name_hint],
                    context: (array) $recipient->context,
                    modelKey: $campaign->ai_model,
                    language: (string) ($campaign->language ?: 'en'),
                );
                $recipient->forceFill([
                    'rendered_body_html' => $response->text,
                    'rendered_at' => now(),
                    'ai_tokens_input' => $response->tokensInput,
                    'ai_tokens_output' => $response->tokensOutput,
                    'ai_cost_usd_cents' => $response->costUsdCents,
                ])->save();
                $campaign->increment('ai_total_cost_usd_cents', (int) $response->costUsdCents);
                $ok++;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('AI render failed for recipient', [
                    'recipient_id' => $recipient->id,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        Notification::make()
            ->title("Rendered {$ok} previews")
            ->body($failed ? "{$failed} failed — check logs; retry the row individually." : 'You can now review and edit each recipient\'s copy.')
            ->success()->send();
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
