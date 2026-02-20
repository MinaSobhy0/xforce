<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Message;

class EmailService
{
    /**
     * Check if Email is enabled.
     */
    public function isEnabled(): bool
    {
        return config('marketing.email.enabled', true);
    }

    /**
     * Send an email.
     */
    public function send(
        string $to,
        string $subject,
        string $htmlContent,
        ?string $textContent = null,
        array $attachments = []
    ): array {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'error' => 'Email is not configured',
            ];
        }

        try {
            $fromName = config('marketing.email.from_name') ?? config('mail.from.name');
            $fromAddress = config('marketing.email.from_address') ?? config('mail.from.address');
            $replyTo = config('marketing.email.reply_to');

            Mail::send([], [], function (Message $message) use (
                $to,
                $subject,
                $htmlContent,
                $textContent,
                $attachments,
                $fromName,
                $fromAddress,
                $replyTo
            ) {
                $message->to($to)
                    ->from($fromAddress, $fromName)
                    ->subject($subject)
                    ->html($htmlContent);

                if ($textContent) {
                    $message->text($textContent);
                }

                if ($replyTo) {
                    $message->replyTo($replyTo);
                }

                foreach ($attachments as $attachment) {
                    if (isset($attachment['path'])) {
                        $message->attach($attachment['path'], [
                            'as' => $attachment['name'] ?? null,
                            'mime' => $attachment['mime'] ?? null,
                        ]);
                    } elseif (isset($attachment['data'])) {
                        $message->attachData(
                            $attachment['data'],
                            $attachment['name'],
                            ['mime' => $attachment['mime'] ?? 'application/octet-stream']
                        );
                    }
                }
            });

            return [
                'success' => true,
                'message_id' => md5($to . $subject . time()), // Generate pseudo ID
            ];
        } catch (\Exception $e) {
            Log::error('Email send failed', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send a templated email using a Blade view.
     */
    public function sendTemplate(
        string $to,
        string $subject,
        string $template,
        array $data = [],
        array $attachments = []
    ): array {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'error' => 'Email is not configured',
            ];
        }

        try {
            $htmlContent = view($template, $data)->render();
            return $this->send($to, $subject, $htmlContent, null, $attachments);
        } catch (\Exception $e) {
            Log::error('Email template send failed', [
                'to' => $to,
                'template' => $template,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build appointment reminder email HTML.
     */
    public function buildAppointmentReminderHtml(array $variables): string
    {
        $patientName = $variables['patient_name'] ?? 'Valued Patient';
        $appointmentDate = $variables['appointment_date'] ?? '';
        $appointmentTime = $variables['appointment_time'] ?? '';
        $treatmentName = $variables['treatment_name'] ?? '';
        $clinicName = $variables['clinic_name'] ?? '';
        $branchName = $variables['branch_name'] ?? '';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Reminder</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 10px 10px 0 0;">
        <h1 style="color: white; margin: 0; text-align: center;">Appointment Reminder</h1>
    </div>

    <div style="background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px;">
        <p>Dear <strong>{$patientName}</strong>,</p>

        <p>This is a friendly reminder about your upcoming appointment:</p>

        <div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #667eea;">
            <p style="margin: 5px 0;"><strong>Date:</strong> {$appointmentDate}</p>
            <p style="margin: 5px 0;"><strong>Time:</strong> {$appointmentTime}</p>
            <p style="margin: 5px 0;"><strong>Treatment:</strong> {$treatmentName}</p>
            <p style="margin: 5px 0;"><strong>Location:</strong> {$branchName}</p>
        </div>

        <p>Please arrive 10 minutes before your scheduled time. If you need to reschedule or cancel, please contact us as soon as possible.</p>

        <p>Best regards,<br><strong>{$clinicName}</strong></p>
    </div>

    <div style="text-align: center; padding: 20px; color: #888; font-size: 12px;">
        <p>This is an automated message. Please do not reply directly to this email.</p>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Build invoice receipt email HTML.
     */
    public function buildInvoiceReceiptHtml(array $variables): string
    {
        $patientName = $variables['patient_name'] ?? 'Valued Patient';
        $invoiceNumber = $variables['invoice_number'] ?? '';
        $invoiceTotal = $variables['invoice_total'] ?? '';
        $paymentAmount = $variables['payment_amount'] ?? '';
        $clinicName = $variables['clinic_name'] ?? '';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); padding: 30px; border-radius: 10px 10px 0 0;">
        <h1 style="color: white; margin: 0; text-align: center;">Payment Received</h1>
    </div>

    <div style="background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px;">
        <p>Dear <strong>{$patientName}</strong>,</p>

        <p>Thank you for your payment. Here are the details:</p>

        <div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #11998e;">
            <p style="margin: 5px 0;"><strong>Invoice Number:</strong> {$invoiceNumber}</p>
            <p style="margin: 5px 0;"><strong>Invoice Total:</strong> {$invoiceTotal}</p>
            <p style="margin: 5px 0;"><strong>Amount Paid:</strong> {$paymentAmount}</p>
        </div>

        <p>If you have any questions about this payment, please don't hesitate to contact us.</p>

        <p>Best regards,<br><strong>{$clinicName}</strong></p>
    </div>

    <div style="text-align: center; padding: 20px; color: #888; font-size: 12px;">
        <p>This is an automated message. Please do not reply directly to this email.</p>
    </div>
</body>
</html>
HTML;
    }
}
