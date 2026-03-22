<?php

namespace Modules\MobileApi\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Modules\MobileApi\Services\PushNotificationService;
use Modules\Payroll\Events\PayrollPaid;

class SendPayslipReadyPush implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The queue name for this job.
     */
    public string $queue = 'notifications';

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Handle the payroll paid event.
     */
    public function handle(PayrollPaid $event): void
    {
        $payrollRun = $event->payrollRun;

        // Load payroll lines with staff profiles and users
        $payrollRun->loadMissing(['payrollLines.staffProfile.user']);

        $pushService = app(PushNotificationService::class);

        // Format the period for display
        $period = $payrollRun->period_start->format('M Y');

        foreach ($payrollRun->payrollLines as $line) {
            $user = $line->staffProfile?->user;

            if (! $user) {
                continue;
            }

            try {
                $pushService->sendPayslipReadyNotification(
                    user: $user,
                    payroll: $line,
                    period: $period
                );
            } catch (\Exception $e) {
                Log::error('Failed to send payslip ready notification', [
                    'payroll_line_id' => $line->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(PayrollPaid $event, \Throwable $exception): void
    {
        Log::error('Failed to send payslip ready push notifications', [
            'payroll_run_id' => $event->payrollRun->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
