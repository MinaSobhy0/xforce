<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\Log;
use Modules\Marketing\Exceptions\QuotaExceededException;
use Modules\Marketing\Models\MessageTemplate;
use Modules\Marketing\Models\NotificationLog;
use Modules\Patients\Models\Patient;

class NotificationService
{
    public function __construct(
        protected WhatsAppService $whatsAppService,
        protected SmsService $smsService,
        protected EmailService $emailService,
        protected MessageQuotaService $quotaService
    ) {}

    /**
     * Send a notification using the specified channel.
     *
     * @throws QuotaExceededException
     */
    public function send(
        string $channel,
        string $recipientAddress,
        string $content,
        ?string $subject = null,
        array $options = []
    ): array {
        // Check quota before sending (unless explicitly skipped)
        $skipQuotaCheck = $options['skip_quota_check'] ?? false;
        $tenantId = $options['tenant_id'] ?? null;

        if (!$skipQuotaCheck) {
            $this->quotaService->checkQuota($channel, $tenantId);
        }

        $result = match ($channel) {
            NotificationLog::CHANNEL_WHATSAPP => $this->sendWhatsApp($recipientAddress, $content, $options),
            NotificationLog::CHANNEL_SMS => $this->sendSms($recipientAddress, $content),
            NotificationLog::CHANNEL_EMAIL => $this->sendEmail($recipientAddress, $subject ?? '', $content),
            default => ['success' => false, 'error' => 'Unknown channel'],
        };

        // Track usage if message was sent successfully
        if ($result['success'] && !$skipQuotaCheck) {
            $this->quotaService->trackUsage($channel, $tenantId);
        }

        return $result;
    }

    /**
     * Send a notification using a template.
     */
    public function sendTemplate(
        MessageTemplate $template,
        Patient $patient,
        array $variables = [],
        ?string $referenceType = null,
        ?string $referenceId = null
    ): NotificationLog {
        // Determine recipient address
        $recipientAddress = match ($template->channel) {
            MessageTemplate::CHANNEL_EMAIL => $patient->email,
            default => $patient->international_phone ?? $patient->phone,
        };

        if (!$recipientAddress) {
            return $this->createFailedLog(
                $template,
                $patient,
                'No recipient address available',
                $referenceType,
                $referenceId
            );
        }

        // Check quota before proceeding
        try {
            $this->quotaService->checkQuota($template->channel, $patient->tenant_id);
        } catch (QuotaExceededException $e) {
            return $this->createFailedLog(
                $template,
                $patient,
                $e->getMessage(),
                $referenceType,
                $referenceId
            );
        }

        // Merge patient variables
        $variables = array_merge([
            'patient_name' => $patient->full_name,
            'patient_first_name' => $patient->first_name,
            'patient_phone' => $patient->phone,
        ], $variables);

        // Render template
        $rendered = $template->render($variables, $patient->language ?? app()->getLocale());

        // Create notification log
        $log = NotificationLog::create([
            'tenant_id' => $patient->tenant_id,
            'patient_id' => $patient->id,
            'channel' => $template->channel,
            'type' => $template->type,
            'template_id' => $template->id,
            'recipient_address' => $recipientAddress,
            'subject' => $rendered['subject'],
            'content' => $rendered['content'],
            'variables_json' => $variables,
            'status' => NotificationLog::STATUS_QUEUED,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'queued_at' => now(),
        ]);

        // Send based on channel (skip quota check since we already checked)
        // For WhatsApp with buttons, use interactive message
        if ($template->channel === MessageTemplate::CHANNEL_WHATSAPP && $template->hasButtons()) {
            $result = $this->whatsAppService->sendMessageWithButtons(
                $recipientAddress,
                $template,
                $variables,
                $patient->language ?? app()->getLocale()
            );
        } else {
            $result = $this->send(
                $template->channel,
                $recipientAddress,
                $rendered['content'],
                $rendered['subject'],
                [
                    'whatsapp_template_name' => $template->whatsapp_template_name,
                    'variables' => $variables,
                    'skip_quota_check' => true,
                    'tenant_id' => $patient->tenant_id,
                ]
            );
        }

        // Update log with result
        if ($result['success']) {
            $log->markAsSent($result['message_id'] ?? null, $result['response'] ?? null);
            // Track usage after successful send
            $this->quotaService->trackUsage($template->channel, $patient->tenant_id);
        } else {
            $log->markAsFailed($result['error'] ?? 'Unknown error');
        }

        return $log;
    }

    /**
     * Send via WhatsApp.
     */
    protected function sendWhatsApp(string $to, string $content, array $options = []): array
    {
        // If we have a template name, use template message
        if (!empty($options['whatsapp_template_name'])) {
            return $this->whatsAppService->sendTemplateMessage(
                $to,
                $options['whatsapp_template_name'],
                $options['language_code'] ?? 'en',
                $options['components'] ?? []
            );
        }

        // Otherwise send text (only works in 24h window)
        return $this->whatsAppService->sendTextMessage($to, $content);
    }

    /**
     * Send via SMS.
     */
    protected function sendSms(string $to, string $content): array
    {
        return $this->smsService->send($to, $content);
    }

    /**
     * Send via Email.
     */
    protected function sendEmail(string $to, string $subject, string $content): array
    {
        return $this->emailService->send($to, $subject, $content);
    }

    /**
     * Send using best available channel based on priority.
     */
    public function sendWithFallback(
        Patient $patient,
        string $type,
        array $variables = [],
        ?string $referenceType = null,
        ?string $referenceId = null
    ): ?NotificationLog {
        $channelPriority = config('marketing.automation.channel_priority', ['whatsapp', 'sms', 'email']);

        foreach ($channelPriority as $channel) {
            // Check if channel is enabled
            $isEnabled = match ($channel) {
                'whatsapp' => $this->whatsAppService->isEnabled(),
                'sms' => $this->smsService->isEnabled(),
                'email' => $this->emailService->isEnabled(),
                default => false,
            };

            if (!$isEnabled) {
                continue;
            }

            // Check if patient has contact info for this channel
            $hasContact = match ($channel) {
                'email' => !empty($patient->email),
                default => !empty($patient->phone),
            };

            if (!$hasContact) {
                continue;
            }

            // Find template for this channel and type
            $template = MessageTemplate::where('tenant_id', $patient->tenant_id)
                ->where('channel', $channel)
                ->where('type', $type)
                ->where('is_active', true)
                ->first();

            if (!$template) {
                continue;
            }

            // Try to send
            $log = $this->sendTemplate($template, $patient, $variables, $referenceType, $referenceId);

            if ($log->status !== NotificationLog::STATUS_FAILED) {
                return $log;
            }

            // Log failure and try next channel
            Log::warning("Marketing notification failed on {$channel}, trying next channel", [
                'patient_id' => $patient->id,
                'type' => $type,
                'error' => $log->error_message,
            ]);
        }

        return null;
    }

    /**
     * Create a failed log entry.
     */
    protected function createFailedLog(
        MessageTemplate $template,
        Patient $patient,
        string $error,
        ?string $referenceType = null,
        ?string $referenceId = null
    ): NotificationLog {
        return NotificationLog::create([
            'tenant_id' => $patient->tenant_id,
            'patient_id' => $patient->id,
            'channel' => $template->channel,
            'type' => $template->type,
            'template_id' => $template->id,
            'recipient_address' => '',
            'content' => '',
            'status' => NotificationLog::STATUS_FAILED,
            'error_message' => $error,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'failed_at' => now(),
        ]);
    }

    /**
     * Get available channels.
     */
    public function getAvailableChannels(): array
    {
        $channels = [];

        if ($this->whatsAppService->isEnabled()) {
            $channels[] = 'whatsapp';
        }

        if ($this->smsService->isEnabled()) {
            $channels[] = 'sms';
        }

        if ($this->emailService->isEnabled()) {
            $channels[] = 'email';
        }

        return $channels;
    }

    /**
     * Get remaining quota for a channel.
     */
    public function getRemainingQuota(string $channel, ?string $tenantId = null): int
    {
        return $this->quotaService->getRemainingQuota($channel, $tenantId);
    }

    /**
     * Get messaging quota usage summary.
     */
    public function getQuotaUsageSummary(?string $tenantId = null): array
    {
        return $this->quotaService->getUsageSummary($tenantId);
    }

    /**
     * Check if quota is near limit for any channel.
     */
    public function hasQuotaWarnings(?string $tenantId = null): bool
    {
        $summary = $this->quotaService->getUsageSummary($tenantId);

        foreach ($summary as $channel => $data) {
            if ($data['near_limit']) {
                return true;
            }
        }

        return false;
    }
}
