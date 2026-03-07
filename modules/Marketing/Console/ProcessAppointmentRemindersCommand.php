<?php

namespace Modules\Marketing\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Booking\Models\Appointment;
use Modules\Core\Models\Tenant;
use Modules\Marketing\Models\AutomationRule;
use Modules\Marketing\Models\NotificationLog;
use Modules\Marketing\Services\NotificationService;

class ProcessAppointmentRemindersCommand extends Command
{
    protected $signature = 'automation:process-reminders
                            {--tenant= : Process reminders for specific tenant only}
                            {--dry-run : Show what would be sent without actually sending}';

    protected $description = 'Process scheduled appointment reminders based on automation rules';

    public function __construct(
        protected NotificationService $notificationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Processing appointment reminders...');

        $dryRun = $this->option('dry-run');
        $tenantIdentifier = $this->option('tenant');
        $totalSent = 0;

        // Get tenants to process
        if ($tenantIdentifier) {
            $tenants = Tenant::where('id', $tenantIdentifier)
                ->orWhere('slug', $tenantIdentifier)
                ->get();
        } else {
            $tenants = Tenant::whereIn('status', ['active', 'trial'])->get();
        }

        if ($tenants->isEmpty()) {
            $this->info('No tenants to process.');
            return self::SUCCESS;
        }

        $this->info("Processing {$tenants->count()} tenant(s)...");

        foreach ($tenants as $tenant) {
            $sent = $this->processRemindersForTenant($tenant, $dryRun);
            $totalSent += $sent;

            if ($sent > 0 || $dryRun) {
                $action = $dryRun ? 'would send' : 'sent';
                $this->line("  {$tenant->name}: {$sent} reminder(s) {$action}");
            }
        }

        $this->info("Processed {$totalSent} appointment reminder(s).");

        return self::SUCCESS;
    }

    protected function processRemindersForTenant(Tenant $tenant, bool $dryRun = false): int
    {
        // Use the database_name field which stores the actual schema name
        $schemaName = $tenant->database_name ?? ('tenant_' . str_replace('-', '_', $tenant->slug));

        try {
            // Switch to tenant schema
            DB::statement("SET search_path TO \"{$schemaName}\", public");

            // Find active appointment_reminder rules for this tenant
            $rules = AutomationRule::query()
                ->where('trigger_type', AutomationRule::TRIGGER_APPOINTMENT_REMINDER)
                ->where('is_active', true)
                ->orderBy('priority', 'desc')
                ->with('template')
                ->get();

            if ($rules->isEmpty()) {
                DB::statement("SET search_path TO public");
                return 0;
            }

            $sentCount = 0;

            foreach ($rules as $rule) {
                if (!$rule->template || !$rule->template->is_active) {
                    continue;
                }

                $sent = $this->processRuleReminders($rule, $tenant, $dryRun);
                $sentCount += $sent;
            }

            // Reset search path
            DB::statement("SET search_path TO public");

            return $sentCount;

        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            Log::error("Failed to process appointment reminders for tenant {$tenant->slug}: " . $e->getMessage());
            $this->error("  Error processing {$tenant->name}: " . $e->getMessage());
            return 0;
        }
    }

    protected function processRuleReminders(AutomationRule $rule, Tenant $tenant, bool $dryRun = false): int
    {
        // Calculate the time window for appointments that need reminders
        $now = Carbon::now();

        // Calculate when the appointment should start for this rule to fire now
        $appointmentStartWindow = $this->calculateAppointmentWindow($rule, $now);

        if (!$appointmentStartWindow) {
            return 0;
        }

        [$windowStart, $windowEnd] = $appointmentStartWindow;

        // Find appointments within this window
        $appointments = Appointment::query()
            ->whereIn('status', [
                Appointment::STATUS_SCHEDULED,
                Appointment::STATUS_CONFIRMED,
            ])
            ->where(function ($query) use ($windowStart, $windowEnd) {
                // Match appointments where date+start_time falls within the window
                $query->whereRaw(
                    "(date + start_time) BETWEEN ? AND ?",
                    [$windowStart->toDateTimeString(), $windowEnd->toDateTimeString()]
                );
            })
            ->with(['patient', 'service', 'practitioner', 'branch'])
            ->get();

        if ($appointments->isEmpty()) {
            return 0;
        }

        $sentCount = 0;

        foreach ($appointments as $appointment) {
            $patient = $appointment->patient;

            if (!$patient) {
                continue;
            }

            // Check if patient has consented to messaging
            if (!$this->hasMessagingConsent($patient)) {
                continue;
            }

            // Check if reminder already sent for this appointment + rule combination
            if ($this->reminderAlreadySent($appointment, $rule)) {
                continue;
            }

            // Build context for condition matching
            $context = [
                'service_id' => $appointment->service_id,
                'practitioner_id' => $appointment->practitioner_id,
                'branch_id' => $appointment->branch_id,
                'patient_gender' => $patient->gender,
                'patient_status' => $patient->status,
            ];

            // Check conditions
            if (!$rule->matchesConditions($context)) {
                continue;
            }

            if ($dryRun) {
                $this->info("    [DRY RUN] Would send reminder to {$patient->full_name} for {$appointment->code} at {$appointment->start_date_time?->format('Y-m-d H:i')}");
                $sentCount++;
                continue;
            }

            // Send the reminder
            try {
                $variables = $this->buildTemplateVariables($appointment);

                $log = $this->notificationService->sendTemplate(
                    $rule->template,
                    $patient,
                    $variables,
                    'appointment',
                    (string) $appointment->id
                );

                // Mark the type as appointment_reminder for tracking
                $log->update(['type' => NotificationLog::TYPE_APPOINTMENT_REMINDER]);

                Log::info('Appointment reminder sent', [
                    'tenant' => $tenant->slug,
                    'appointment_id' => $appointment->id,
                    'appointment_code' => $appointment->code,
                    'rule_id' => $rule->id,
                    'template_id' => $rule->template_id,
                    'notification_log_id' => $log->id,
                    'status' => $log->status,
                ]);

                $sentCount++;
            } catch (\Exception $e) {
                Log::error('Failed to send appointment reminder', [
                    'tenant' => $tenant->slug,
                    'appointment_id' => $appointment->id,
                    'rule_id' => $rule->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sentCount;
    }

    /**
     * Calculate the appointment time window based on the rule timing.
     *
     * For example, if the rule is "24 hours before", we need to find appointments
     * starting in approximately 24 hours from now.
     *
     * Returns [windowStart, windowEnd] or null if timing doesn't apply.
     */
    protected function calculateAppointmentWindow(AutomationRule $rule, Carbon $now): ?array
    {
        // Only handle "before" timing for appointment reminders
        if ($rule->timing_type !== AutomationRule::TIMING_BEFORE) {
            // For immediate, we don't process here (handled by AppointmentConfirmed event)
            return null;
        }

        $timingValue = $rule->timing_value ?? 0;
        $timingUnit = $rule->timing_unit ?? 'hours';

        if ($timingValue <= 0) {
            return null;
        }

        // The appointment should start at: now + timing_value timing_unit
        $appointmentTime = match ($timingUnit) {
            'minutes' => $now->copy()->addMinutes($timingValue),
            'hours' => $now->copy()->addHours($timingValue),
            'days' => $now->copy()->addDays($timingValue),
            default => $now->copy()->addHours($timingValue),
        };

        // Window: -5 minutes to +5 minutes around the target time
        // This ensures we don't miss appointments when the scheduler runs every minute
        $windowStart = $appointmentTime->copy()->subMinutes(5);
        $windowEnd = $appointmentTime->copy()->addMinutes(5);

        return [$windowStart, $windowEnd];
    }

    /**
     * Check if a reminder has already been sent for this appointment.
     */
    protected function reminderAlreadySent(Appointment $appointment, AutomationRule $rule): bool
    {
        return NotificationLog::query()
            ->where('reference_type', 'appointment')
            ->where('reference_id', $appointment->id)
            ->where('type', NotificationLog::TYPE_APPOINTMENT_REMINDER)
            ->where('template_id', $rule->template_id)
            ->whereIn('status', [
                NotificationLog::STATUS_QUEUED,
                NotificationLog::STATUS_SENDING,
                NotificationLog::STATUS_SENT,
                NotificationLog::STATUS_DELIVERED,
                NotificationLog::STATUS_READ,
            ])
            ->exists();
    }

    /**
     * Check if patient has consented to receive messages.
     */
    protected function hasMessagingConsent($patient): bool
    {
        return $patient->marketing_consent
            || $patient->sms_consent
            || $patient->whatsapp_consent
            || $patient->email_consent;
    }

    /**
     * Build template variables from appointment data.
     */
    protected function buildTemplateVariables($appointment): array
    {
        return [
            'appointment_date' => $appointment->date?->format('d/m/Y'),
            'appointment_time' => $appointment->start_time?->format('h:i A'),
            'appointment_datetime' => $appointment->start_date_time?->format('d/m/Y h:i A'),
            'service_name' => $appointment->service?->getTranslation('name', app()->getLocale()) ?? '',
            'practitioner_name' => $appointment->practitioner?->name ?? '',
            'branch_name' => $appointment->branch?->name ?? '',
            'branch_address' => $appointment->branch?->address ?? '',
            'branch_phone' => $appointment->branch?->phone ?? '',
            'appointment_id' => $appointment->id,
            'appointment_code' => $appointment->code,
            'confirmation_code' => $appointment->code,
        ];
    }
}
