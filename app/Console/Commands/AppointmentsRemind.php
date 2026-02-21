<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Core\Models\Tenant;

class AppointmentsRemind extends Command
{
    protected $signature = 'appointments:remind
                            {--tenant= : Send reminders for specific tenant only}
                            {--hours=24 : Send reminders for appointments within this many hours}
                            {--dry-run : Preview what would be sent without sending}';

    protected $description = 'Send appointment reminder notifications to patients';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $hours = (int) $this->option('hours');
        $tenantIdentifier = $this->option('tenant');

        if ($dryRun) {
            $this->info("[DRY RUN] No notifications will be sent.");
            $this->newLine();
        }

        // Get tenants to process
        if ($tenantIdentifier) {
            $tenants = Tenant::where('id', $tenantIdentifier)
                ->orWhere('slug', $tenantIdentifier)
                ->get();
        } else {
            $tenants = Tenant::whereIn('status', ['active', 'trial'])->get();
        }

        if ($tenants->isEmpty()) {
            $this->info("No tenants to process.");
            return 0;
        }

        $this->info("Processing {$tenants->count()} tenants...");
        $this->newLine();

        $totalReminders = 0;

        foreach ($tenants as $tenant) {
            $count = $this->processRemindersForTenant($tenant, $hours, $dryRun);
            $totalReminders += $count;

            if ($count > 0) {
                $this->line("  {$tenant->name}: {$count} reminders " . ($dryRun ? 'would be ' : '') . "sent");
            }
        }

        $this->newLine();
        $this->info("Total reminders " . ($dryRun ? 'to be ' : '') . "sent: {$totalReminders}");

        return 0;
    }

    protected function processRemindersForTenant(Tenant $tenant, int $hours, bool $dryRun): int
    {
        $schemaName = 'tenant_' . str_replace('-', '_', $tenant->slug);

        try {
            // Switch to tenant schema
            DB::statement("SET search_path TO \"{$schemaName}\", public");

            // Get settings
            $settings = DB::table('settings')
                ->where('group', 'booking')
                ->pluck('payload', 'name')
                ->map(fn($v) => json_decode($v, true))
                ->toArray();

            $reminderHours = $settings['reminder_hours_before'] ?? $hours;
            $sendWhatsApp = $settings['send_whatsapp_reminders'] ?? false;
            $sendSms = $settings['send_sms_reminders'] ?? false;
            $sendEmail = $settings['send_email_reminders'] ?? true;

            // Get appointments needing reminders
            $now = now();
            $reminderWindow = now()->addHours($reminderHours);

            $appointments = DB::table('appointments')
                ->join('patients', 'appointments.patient_id', '=', 'patients.id')
                ->leftJoin('users as providers', 'appointments.provider_id', '=', 'providers.id')
                ->where('appointments.status', 'confirmed')
                ->where('appointments.start_time', '>=', $now)
                ->where('appointments.start_time', '<=', $reminderWindow)
                ->whereNull('appointments.reminder_sent_at')
                ->select([
                    'appointments.id',
                    'appointments.start_time',
                    'patients.name as patient_name',
                    'patients.email as patient_email',
                    'patients.phone as patient_phone',
                    'providers.name as provider_name',
                ])
                ->get();

            $sentCount = 0;

            foreach ($appointments as $appointment) {
                if ($dryRun) {
                    $this->line("    Would remind: {$appointment->patient_name} for " .
                        \Carbon\Carbon::parse($appointment->start_time)->format('M j, Y h:i A'));
                    $sentCount++;
                    continue;
                }

                // Send notifications based on settings
                $sent = false;

                if ($sendEmail && $appointment->patient_email) {
                    $this->sendEmailReminder($appointment, $tenant);
                    $sent = true;
                }

                // TODO: Implement WhatsApp and SMS when services are available
                // if ($sendWhatsApp && $appointment->patient_phone) {
                //     $this->sendWhatsAppReminder($appointment, $tenant);
                // }
                // if ($sendSms && $appointment->patient_phone) {
                //     $this->sendSmsReminder($appointment, $tenant);
                // }

                if ($sent) {
                    DB::table('appointments')
                        ->where('id', $appointment->id)
                        ->update(['reminder_sent_at' => now()]);
                    $sentCount++;
                }
            }

            // Reset search path
            DB::statement("SET search_path TO public");

            return $sentCount;

        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            Log::error("Failed to process reminders for tenant {$tenant->slug}: " . $e->getMessage());
            return 0;
        }
    }

    protected function sendEmailReminder($appointment, Tenant $tenant): void
    {
        try {
            // Simple mail notification - in production, use proper Mailable class
            Mail::raw(
                "Dear {$appointment->patient_name},\n\n" .
                "This is a reminder for your upcoming appointment at {$tenant->name}.\n\n" .
                "Date: " . \Carbon\Carbon::parse($appointment->start_time)->format('M j, Y') . "\n" .
                "Time: " . \Carbon\Carbon::parse($appointment->start_time)->format('h:i A') . "\n" .
                ($appointment->provider_name ? "Provider: {$appointment->provider_name}\n" : "") .
                "\nPlease arrive 10 minutes early.\n\n" .
                "Best regards,\n{$tenant->name}",
                function ($message) use ($appointment, $tenant) {
                    $message->to($appointment->patient_email)
                        ->subject("Appointment Reminder - {$tenant->name}");
                }
            );
        } catch (\Exception $e) {
            Log::error("Failed to send email reminder: " . $e->getMessage());
        }
    }
}
