<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Marketing\Models\Campaign;
use Modules\Marketing\Models\CampaignRecipient;
use Modules\Patients\Models\Patient;

class CampaignService
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Populate recipients for a campaign based on audience filters.
     */
    public function populateRecipients(Campaign $campaign): int
    {
        $query = Patient::query()
            ->where('tenant_id', $campaign->tenant_id);

        // Apply audience filters
        $filters = $campaign->audience_filters_json ?? [];

        foreach ($filters as $filter) {
            $field = $filter['field'] ?? null;
            $operator = $filter['operator'] ?? 'equals';
            $value = $filter['value'] ?? null;

            if (!$field || $value === null) {
                continue;
            }

            $this->applyFilter($query, $field, $operator, $value);
        }

        // Filter by channel availability
        if ($campaign->channel === 'email') {
            $query->whereNotNull('email')->where('email', '!=', '');
        } else {
            $query->whereNotNull('phone')->where('phone', '!=', '');
        }

        // Get matching patients and create recipients
        $count = 0;
        $query->chunk(100, function ($patients) use ($campaign, &$count) {
            foreach ($patients as $patient) {
                $recipientAddress = $campaign->channel === 'email'
                    ? $patient->email
                    : ($patient->international_phone ?? $patient->phone);

                if (!$recipientAddress) {
                    continue;
                }

                CampaignRecipient::create([
                    'tenant_id' => $campaign->tenant_id,
                    'campaign_id' => $campaign->id,
                    'patient_id' => $patient->id,
                    'recipient_address' => $recipientAddress,
                    'variables_json' => [
                        'patient_name' => $patient->full_name,
                        'patient_first_name' => $patient->first_name,
                        'patient_phone' => $patient->phone,
                        'patient_email' => $patient->email,
                    ],
                    'status' => CampaignRecipient::STATUS_PENDING,
                ]);
                $count++;
            }
        });

        // Update campaign total
        $campaign->update(['total_recipients' => $count]);

        return $count;
    }

    /**
     * Apply a single filter to the query.
     */
    protected function applyFilter($query, string $field, string $operator, $value): void
    {
        switch ($field) {
            case 'last_visit':
                // Value is number of days
                $date = now()->subDays((int) $value);
                $query->whereHas('appointments', function ($q) use ($date, $operator) {
                    $q->where('status', 'completed');
                    if ($operator === 'greater_than') {
                        $q->where('start_time', '<', $date);
                    } else {
                        $q->where('start_time', '>=', $date);
                    }
                });
                break;

            case 'total_spent_min':
                $query->whereHas('invoices', function ($q) use ($value) {
                    $q->where('status', 'paid')
                      ->havingRaw('SUM(total) >= ?', [(float) $value]);
                });
                break;

            case 'total_spent_max':
                $query->whereHas('invoices', function ($q) use ($value) {
                    $q->where('status', 'paid')
                      ->havingRaw('SUM(total) <= ?', [(float) $value]);
                });
                break;

            case 'service':
            case 'treatment':
                $query->whereHas('appointments', function ($q) use ($value, $operator) {
                    if ($operator === 'in' && is_array($value)) {
                        $q->whereIn('service_id', $value);
                    } else {
                        $q->where('service_id', $value);
                    }
                });
                break;

            case 'branch':
                if ($operator === 'in' && is_array($value)) {
                    $query->whereIn('branch_id', $value);
                } else {
                    $query->where('branch_id', $value);
                }
                break;

            case 'gender':
                $query->where('gender', $value);
                break;

            case 'is_vip':
                $query->where('is_vip', filter_var($value, FILTER_VALIDATE_BOOLEAN));
                break;

            case 'tags':
                if (is_array($value)) {
                    $query->whereJsonContains('tags', $value);
                }
                break;
        }
    }

    /**
     * Process pending recipients and send messages.
     */
    public function processRecipients(Campaign $campaign, int $batchSize = 50): array
    {
        if (!$campaign->isSending()) {
            return ['processed' => 0, 'sent' => 0, 'failed' => 0];
        }

        $template = $campaign->template;
        if (!$template) {
            Log::error('Campaign has no template', ['campaign_id' => $campaign->id]);
            return ['processed' => 0, 'sent' => 0, 'failed' => 0, 'error' => 'No template'];
        }

        $recipients = CampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', CampaignRecipient::STATUS_PENDING)
            ->limit($batchSize)
            ->get();

        $stats = ['processed' => 0, 'sent' => 0, 'failed' => 0];

        foreach ($recipients as $recipient) {
            if ($campaign->isPaused() || $campaign->isCancelled()) {
                break;
            }

            $stats['processed']++;

            try {
                $patient = $recipient->patient;
                if (!$patient) {
                    $recipient->markAsFailed('Patient not found');
                    $stats['failed']++;
                    continue;
                }

                $variables = array_merge(
                    $recipient->variables_json ?? [],
                    ['clinic_name' => config('app.name')]
                );

                $log = $this->notificationService->sendTemplate(
                    $template,
                    $patient,
                    $variables,
                    'campaign',
                    (string) $campaign->id
                );

                if ($log->status === 'sent' || $log->status === 'delivered') {
                    $recipient->markAsSent($log->provider_message_id);
                    $stats['sent']++;
                } else {
                    $recipient->markAsFailed($log->error_message ?? 'Send failed');
                    $stats['failed']++;
                }

            } catch (\Exception $e) {
                Log::error('Campaign recipient send failed', [
                    'campaign_id' => $campaign->id,
                    'recipient_id' => $recipient->id,
                    'error' => $e->getMessage(),
                ]);
                $recipient->markAsFailed($e->getMessage());
                $stats['failed']++;
            }

            // Rate limiting delay
            $this->applyRateLimit($campaign->channel);
        }

        // Update campaign statistics
        $this->updateCampaignStats($campaign);

        // Check if campaign is complete
        $pending = CampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', CampaignRecipient::STATUS_PENDING)
            ->count();

        if ($pending === 0 && $campaign->isSending()) {
            $campaign->complete();
        }

        return $stats;
    }

    /**
     * Apply rate limiting delay based on channel.
     */
    protected function applyRateLimit(string $channel): void
    {
        $limits = config('marketing.rate_limits', [
            'whatsapp' => 80,
            'sms' => 60,
            'email' => 100,
        ]);

        $perMinute = $limits[$channel] ?? 60;
        $delayMs = (60 / $perMinute) * 1000;

        usleep((int) ($delayMs * 1000)); // Convert to microseconds
    }

    /**
     * Update campaign statistics from recipients.
     */
    public function updateCampaignStats(Campaign $campaign): void
    {
        $stats = CampaignRecipient::where('campaign_id', $campaign->id)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status IN ('sent', 'delivered', 'read') THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status IN ('delivered', 'read') THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            ")
            ->first();

        $campaign->update([
            'total_recipients' => $stats->total ?? 0,
            'sent_count' => $stats->sent ?? 0,
            'delivered_count' => $stats->delivered ?? 0,
            'read_count' => $stats->read ?? 0,
            'failed_count' => $stats->failed ?? 0,
        ]);
    }

    /**
     * Start a campaign - populate recipients and begin sending.
     */
    public function startCampaign(Campaign $campaign): bool
    {
        if (!$campaign->canStart()) {
            return false;
        }

        DB::transaction(function () use ($campaign) {
            // Populate recipients if not already done
            $existingCount = CampaignRecipient::where('campaign_id', $campaign->id)->count();
            if ($existingCount === 0) {
                $this->populateRecipients($campaign);
            }

            // Start sending
            $campaign->startSending();
        });

        return true;
    }

    /**
     * Process all scheduled campaigns that are due.
     */
    public function processScheduledCampaigns(): int
    {
        $campaigns = Campaign::where('status', Campaign::STATUS_SCHEDULED)
            ->where('scheduled_at', '<=', now())
            ->get();

        $count = 0;
        foreach ($campaigns as $campaign) {
            if ($this->startCampaign($campaign)) {
                $count++;
            }
        }

        return $count;
    }
}
