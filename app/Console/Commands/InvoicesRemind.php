<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Core\Models\Tenant;

class InvoicesRemind extends Command
{
    protected $signature = 'invoices:remind
                            {--tenant= : Send reminders for specific tenant only}
                            {--overdue-days=7 : Send reminders for invoices overdue by this many days}
                            {--dry-run : Preview what would be sent without sending}';

    protected $description = 'Send payment reminder notifications for overdue invoices';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $overdueDays = (int) $this->option('overdue-days');
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
        $this->info("Looking for invoices overdue by at least {$overdueDays} days...");
        $this->newLine();

        $totalReminders = 0;
        $totalAmount = 0;

        foreach ($tenants as $tenant) {
            [$count, $amount] = $this->processRemindersForTenant($tenant, $overdueDays, $dryRun);
            $totalReminders += $count;
            $totalAmount += $amount;

            if ($count > 0) {
                $this->line("  {$tenant->name}: {$count} reminders " . ($dryRun ? 'would be ' : '') . "sent");
            }
        }

        $this->newLine();
        $this->info("Total reminders " . ($dryRun ? 'to be ' : '') . "sent: {$totalReminders}");
        $this->info("Total outstanding amount: EGP " . number_format($totalAmount / 100, 2));

        return 0;
    }

    protected function processRemindersForTenant(Tenant $tenant, int $overdueDays, bool $dryRun): array
    {
        $schemaName = 'tenant_' . str_replace('-', '_', $tenant->slug);

        try {
            // Switch to tenant schema
            DB::statement("SET search_path TO \"{$schemaName}\", public");

            // Get overdue invoices
            $overdueDate = now()->subDays($overdueDays);

            $invoices = DB::table('invoices')
                ->join('patients', 'invoices.patient_id', '=', 'patients.id')
                ->where('invoices.status', 'unpaid')
                ->where('invoices.due_date', '<=', $overdueDate)
                ->whereNull('invoices.last_reminder_sent_at')
                ->orWhere('invoices.last_reminder_sent_at', '<=', now()->subDays(7)) // Don't spam, wait 7 days between reminders
                ->select([
                    'invoices.id',
                    'invoices.invoice_number',
                    'invoices.total_amount_minor',
                    'invoices.due_date',
                    'invoices.last_reminder_sent_at',
                    'patients.name as patient_name',
                    'patients.email as patient_email',
                    'patients.phone as patient_phone',
                ])
                ->get();

            $sentCount = 0;
            $totalAmount = 0;

            foreach ($invoices as $invoice) {
                $totalAmount += $invoice->total_amount_minor;

                if ($dryRun) {
                    $this->line("    Would remind: {$invoice->patient_name} - Invoice #{$invoice->invoice_number} - EGP " .
                        number_format($invoice->total_amount_minor / 100, 2) .
                        " (Due: " . \Carbon\Carbon::parse($invoice->due_date)->format('M j, Y') . ")");
                    $sentCount++;
                    continue;
                }

                // Send email notification
                if ($invoice->patient_email) {
                    $this->sendEmailReminder($invoice, $tenant);

                    DB::table('invoices')
                        ->where('id', $invoice->id)
                        ->update(['last_reminder_sent_at' => now()]);

                    $sentCount++;
                }
            }

            // Reset search path
            DB::statement("SET search_path TO public");

            return [$sentCount, $totalAmount];

        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            Log::error("Failed to process invoice reminders for tenant {$tenant->slug}: " . $e->getMessage());
            return [0, 0];
        }
    }

    protected function sendEmailReminder($invoice, Tenant $tenant): void
    {
        try {
            $amount = number_format($invoice->total_amount_minor / 100, 2);
            $dueDate = \Carbon\Carbon::parse($invoice->due_date)->format('M j, Y');

            Mail::raw(
                "Dear {$invoice->patient_name},\n\n" .
                "This is a friendly reminder that your invoice #{$invoice->invoice_number} " .
                "for EGP {$amount} was due on {$dueDate}.\n\n" .
                "Please settle your outstanding balance at your earliest convenience.\n\n" .
                "If you have already made this payment, please disregard this message.\n\n" .
                "If you have any questions about your invoice, please contact us.\n\n" .
                "Best regards,\n{$tenant->name}",
                function ($message) use ($invoice, $tenant) {
                    $message->to($invoice->patient_email)
                        ->subject("Payment Reminder: Invoice #{$invoice->invoice_number} - {$tenant->name}");
                }
            );
        } catch (\Exception $e) {
            Log::error("Failed to send invoice reminder email: " . $e->getMessage());
        }
    }
}
