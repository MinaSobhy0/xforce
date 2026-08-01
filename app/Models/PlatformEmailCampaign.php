<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single platform-marketing broadcast. Composed by SuperAdmin staff,
 * materialized into per-recipient rows at Send Now / scheduler fire.
 */
class PlatformEmailCampaign extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_SCHEDULED => 'Scheduled',
        self::STATUS_SENDING => 'Sending',
        self::STATUS_SENT => 'Sent',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const LANG_EN = 'en';

    public const LANG_AR = 'ar';

    public const LANGUAGES = [
        self::LANG_EN => 'English',
        self::LANG_AR => 'العربية',
    ];

    public function isRtl(): bool
    {
        return $this->language === self::LANG_AR;
    }

    protected $fillable = [
        'name',
        'subject',
        'preheader',
        'language',
        'from_name',
        'from_address',
        'reply_to',
        'body_html',
        'body_text',
        'list_id',
        'status',
        'scheduled_at',
        'started_at',
        'finished_at',
        'ai_personalize',
        'ai_model',
        'ai_prompt_template',
        'ai_use_batch_api',
        'created_by_user_id',
    ];

    // In-memory defaults so freshly-created models act consistent with
    // the DB defaults defined in the migration. Prevents "$camp->status
    // is empty" until the record is re-fetched.
    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'language' => self::LANG_EN,
        'sent_count' => 0,
        'delivered_count' => 0,
        'opened_count' => 0,
        'clicked_count' => 0,
        'bounced_count' => 0,
        'unsubscribed_count' => 0,
        'complained_count' => 0,
        'failed_count' => 0,
        'ai_personalize' => false,
        'ai_use_batch_api' => false,
        'ai_total_cost_usd_cents' => 0,
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'ai_personalize' => 'boolean',
        'ai_use_batch_api' => 'boolean',
        'sent_count' => 'integer',
        'delivered_count' => 'integer',
        'opened_count' => 'integer',
        'clicked_count' => 'integer',
        'bounced_count' => 'integer',
        'unsubscribed_count' => 'integer',
        'complained_count' => 'integer',
        'failed_count' => 'integer',
        'ai_total_cost_usd_cents' => 'integer',
    ];

    public function list(): BelongsTo
    {
        return $this->belongsTo(PlatformEmailList::class, 'list_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(PlatformEmailCampaignRecipient::class, 'campaign_id');
    }

    public function sends(): HasMany
    {
        return $this->hasMany(PlatformEmailSend::class, 'campaign_id');
    }

    public function isEditable(): bool
    {
        if (in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SCHEDULED, self::STATUS_CANCELLED], true)) {
            return true;
        }

        // SENT campaigns with untargeted list members are still editable —
        // you might have run a canary batch of 10 and want to tweak the
        // subject / body / AI prompt before dispatching the remaining 8.
        // SENDING is deliberately excluded to avoid racing in-flight jobs.
        if ($this->status === self::STATUS_SENT) {
            $listSize = $this->list?->activeMembers()->count() ?? 0;
            $already = $this->recipients()->count();

            return $listSize > $already;
        }

        return false;
    }

    public function isSendable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SCHEDULED], true);
    }

    /**
     * Freeze the current list membership into per-recipient rows. Called
     * by ProcessPlatformCampaignJob at Send Now / scheduler fire time.
     * Idempotent — repeat calls only insert missing rows.
     *
     * Skips addresses on the global suppression list at snapshot time
     * so we don't even materialize rows we'll immediately reject.
     *
     * @param  ?int  $limit  Cap the number of NEW recipient rows created
     *                       during this call. NULL = no cap (freeze the
     *                       whole list). Used for canary / staged sends
     *                       where you want to email 5 first, review, then
     *                       re-run to email the remainder. Members are
     *                       picked in list-order (by member id ASC).
     */
    public function materialize(?int $limit = null): int
    {
        $listMembers = $this->list()
            ->first()
            ?->activeMembers()
            ->orderBy('id')
            ->get();

        if (! $listMembers || $listMembers->isEmpty()) {
            return 0;
        }

        $emails = $listMembers->pluck('email')->map(fn ($e) => mb_strtolower($e))->all();

        // Global suppression check — remove suppressed addresses in bulk.
        $suppressed = PlatformEmailSuppression::query()
            ->whereIn('email', $emails)
            ->pluck('email')
            ->all();
        $suppressedSet = array_flip($suppressed);

        // Addresses already materialized on this campaign (from a prior
        // canary run). Excluded so a partial-send only creates NEW rows.
        $already = $this->recipients()->pluck('email')->map(fn ($e) => mb_strtolower($e))->all();
        $alreadySet = array_flip($already);

        $inserted = 0;
        foreach ($listMembers as $member) {
            if ($limit !== null && $inserted >= $limit) {
                break;
            }
            $email = mb_strtolower($member->email);
            if (isset($suppressedSet[$email]) || isset($alreadySet[$email])) {
                continue;
            }
            $created = PlatformEmailCampaignRecipient::firstOrCreate(
                ['campaign_id' => $this->id, 'email' => $email],
                [
                    'name_hint' => $member->name_hint,
                    'status' => PlatformEmailCampaignRecipient::STATUS_PENDING,
                    'context' => $this->contextFor($member),
                ],
            );
            if ($created->wasRecentlyCreated) {
                $inserted++;
            }
        }

        return $inserted;
    }

    /**
     * Build the frozen tenant/plan snapshot used by the AI personalizer.
     * Extended per-source in the future — for now we surface the fields
     * we know exist across all list types.
     */
    protected function contextFor(PlatformEmailListMember $member): array
    {
        return [
            'source_type' => $member->source_type,
            'name_hint' => $member->name_hint,
            'list_name' => $this->list->name ?? null,
        ];
    }
}
