<?php

namespace App\Filament\SuperAdmin\Resources\PlatformEmailCampaignResource\Concerns;

use App\Jobs\ProcessPlatformCampaignJob;
use App\Mail\PlatformCampaignMail;
use App\Models\PlatformEmailCampaign;
use App\Models\PlatformEmailCampaignRecipient;
use App\Services\Ai\AiEmailPersonalizer;
use App\Services\Ai\LlmProviderRegistry;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Send Test / Send Now / Cancel action definitions, shared between
 * the Edit and View pages of a campaign. The trait relies on
 * $this->record being a PlatformEmailCampaign — true on both parent
 * page types.
 */
trait HasCampaignActions
{
    protected function campaignActions(): array
    {
        return [
            Actions\Action::make('send_test')
                ->label('Send test')
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->form([
                    Forms\Components\TextInput::make('addresses')
                        ->label('Send test to')
                        ->required()
                        ->helperText('Comma-separated. Test sends use AI if AI is enabled on the campaign.'),
                ])
                ->action(function (array $data): void {
                    $this->sendTest($data['addresses']);
                }),

            Actions\Action::make('send_now')
                ->label('Send now')
                ->icon('heroicon-o-megaphone')
                ->color('success')
                ->modalHeading('Send this campaign')
                ->modalDescription(fn () => 'The list will be materialized and jobs dispatched at '.config('platform_email.throttle_per_minute').'/min. You can stop mid-batch via Cancel campaign, but individual sends already delivered can\'t be recalled.')
                ->form(function () {
                    $list = $this->record->list;
                    $listSize = $list ? $list->activeMembers()->count() : 0;
                    $alreadyMaterialized = $this->record->recipients()->count();
                    $remaining = max(0, $listSize - $alreadyMaterialized);

                    return [
                        Forms\Components\Placeholder::make('summary')
                            ->label('')
                            ->content("List has {$listSize} subscribed members. Already materialized on this campaign: {$alreadyMaterialized}. Remaining to send: {$remaining}."),

                        Forms\Components\TextInput::make('limit')
                            ->label('Send to how many recipients this batch?')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue($remaining > 0 ? $remaining : 1)
                            ->default($remaining)
                            ->required()
                            ->helperText('Set a smaller number for a canary batch (e.g. 5 first to verify quality), then re-run Send Now for the rest. Members are picked in list order.'),

                        Forms\Components\Toggle::make('render_only')
                            ->label('Render previews only — don\'t send yet')
                            ->default(false)
                            ->helperText('When ON: recipients are materialized and (for AI campaigns) their personalized copy is rendered, but no emails go out. Review each row on the Recipients tab, click the Body icon to preview, use Edit body to tweak, then use Send this one (or Send Now again without this toggle) to actually dispatch. Human-in-the-loop control.')
                            ->visible(fn () => $this->record->ai_personalize),
                    ];
                })
                // Allow re-invoking Send Now after a partial (canary) batch
                // so the user can send another batch to remaining members.
                // materialize() only creates rows for members not already on
                // this campaign, so re-invoking is idempotent-safe.
                ->visible(function (PlatformEmailCampaign $record): bool {
                    if (! config('platform_email.enabled')) {
                        return false;
                    }
                    if ($record->status === PlatformEmailCampaign::STATUS_CANCELLED) {
                        return false;
                    }
                    // SENDING is allowed only if the current batch drained —
                    // no recipients still pending/rendering/sending. Prevents
                    // re-clicking Send Now mid-batch which would race the
                    // still-in-flight jobs.
                    if ($record->status === PlatformEmailCampaign::STATUS_SENDING) {
                        $inFlight = $record->recipients()
                            ->whereIn('status', [
                                \App\Models\PlatformEmailCampaignRecipient::STATUS_PENDING,
                                \App\Models\PlatformEmailCampaignRecipient::STATUS_RENDERING,
                                \App\Models\PlatformEmailCampaignRecipient::STATUS_SENDING,
                            ])
                            ->exists();
                        if ($inFlight) {
                            return false;
                        }
                    }
                    // Any status except in-flight-sending — check whether
                    // any list members haven't been targeted yet.
                    $listSize = $record->list?->activeMembers()->count() ?? 0;
                    $already = $record->recipients()->count();

                    return $listSize > $already;
                })
                ->action(function (array $data): void {
                    $limit = (int) ($data['limit'] ?? 0) ?: null;
                    $renderOnly = (bool) ($data['render_only'] ?? false);
                    $this->record->forceFill(['status' => PlatformEmailCampaign::STATUS_SENDING])->save();
                    ProcessPlatformCampaignJob::dispatch($this->record->id, $limit, $renderOnly);
                    Notification::make()
                        ->title($renderOnly
                            ? 'Preview render queued for '.($limit ?? 'all').' recipients'
                            : 'Campaign queued for '.($limit ?? 'all').' recipients')
                        ->body($renderOnly
                            ? 'Once rendered, review each row on the Recipients tab. Nothing has been emailed yet.'
                            : 'You can watch the counter row on the View page as sends drain.')
                        ->success()->send();
                    $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
                }),

            Actions\Action::make('cancel_campaign')
                ->label('Cancel campaign')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (PlatformEmailCampaign $record) => in_array($record->status, [
                    PlatformEmailCampaign::STATUS_SCHEDULED,
                    PlatformEmailCampaign::STATUS_SENDING,
                ], true))
                ->action(function (): void {
                    $this->record->forceFill(['status' => PlatformEmailCampaign::STATUS_CANCELLED])->save();
                    Notification::make()->title('Campaign cancelled — any queued sends will bail out.')->success()->send();
                }),
        ];
    }

    protected function sendTest(string $addresses): void
    {
        $emails = collect(preg_split('/[,\s]+/', $addresses))
            ->map(fn ($e) => mb_strtolower(trim((string) $e)))
            ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();

        if ($emails->isEmpty()) {
            Notification::make()->title('No valid addresses supplied')->danger()->send();

            return;
        }

        $personalizer = app(AiEmailPersonalizer::class);
        $campaign = $this->record;

        foreach ($emails as $email) {
            $recipient = new PlatformEmailCampaignRecipient([
                'campaign_id' => $campaign->id,
                'email' => $email,
                'name_hint' => 'Test recipient',
                'status' => PlatformEmailCampaignRecipient::STATUS_PENDING,
                'context' => ['list_name' => 'test-send', 'source_type' => 'test'],
            ]);
            $recipient->id = 0; // placeholder — this is not persisted

            $body = (string) $campaign->body_html;
            if ($campaign->ai_personalize && count(LlmProviderRegistry::available()) > 0) {
                try {
                    $r = $personalizer->personalize(
                        brief: $body,
                        promptExtra: (string) $campaign->ai_prompt_template,
                        recipient: ['email' => $email, 'name_hint' => 'Test recipient'],
                        context: ['list_name' => 'test-send', 'source_type' => 'test'],
                        modelKey: $campaign->ai_model,
                        language: (string) ($campaign->language ?: 'en'),
                    );
                    $body = $r->text;
                } catch (\Throwable $e) {
                    Notification::make()->title('AI render failed on test — falling back to plain body')->warning()->send();
                }
            } else {
                $body = $personalizer->fallbackTokenReplace($body, ['email' => $email, 'name_hint' => 'Test recipient'], ['list_name' => 'test-send']);
            }

            $unsubUrl = URL::signedRoute('platform.unsubscribe', ['campaign' => $campaign->id, 'email' => $email]);

            Mail::to($email)->send(new PlatformCampaignMail(
                campaign: $campaign,
                recipient: $recipient,
                renderedBody: $body,
                unsubscribeUrl: $unsubUrl,
            ));
        }

        Notification::make()->title('Test send(s) dispatched to '.$emails->count().' address(es)')->success()->send();
    }
}
