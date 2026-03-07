<?php

namespace Modules\Booking\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Booking\Models\Appointment;

class AppointmentActionController extends Controller
{
    /**
     * Handle appointment action from signed URL.
     */
    public function handle(Request $request, Appointment $appointment, string $action)
    {
        // Verify the signed URL
        if (!$request->hasValidSignature()) {
            return $this->renderResponse(
                'error',
                __('booking::appointments.messages.invalid_link'),
                __('booking::appointments.messages.link_expired_description')
            );
        }

        // Check if appointment can be modified
        if ($appointment->isCompleted() || $appointment->isCancelled()) {
            return $this->renderResponse(
                'error',
                __('booking::appointments.messages.cannot_modify'),
                __('booking::appointments.messages.appointment_already_processed')
            );
        }

        return match ($action) {
            'confirm' => $this->confirmAppointment($appointment),
            'cancel' => $this->cancelAppointment($appointment),
            'reschedule' => $this->rescheduleAppointment($appointment),
            default => $this->renderResponse('error', __('booking::appointments.messages.invalid_action')),
        };
    }

    /**
     * Confirm the appointment.
     */
    protected function confirmAppointment(Appointment $appointment)
    {
        if ($appointment->isConfirmed()) {
            return $this->renderResponse(
                'info',
                __('booking::appointments.messages.already_confirmed'),
                $this->getAppointmentDetails($appointment)
            );
        }

        $appointment->confirm();

        return $this->renderResponse(
            'success',
            __('booking::appointments.messages.confirmed_success'),
            $this->getAppointmentDetails($appointment)
        );
    }

    /**
     * Cancel the appointment.
     */
    protected function cancelAppointment(Appointment $appointment)
    {
        $appointment->update([
            'status' => Appointment::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Cancelled by patient via link',
        ]);

        return $this->renderResponse(
            'success',
            __('booking::appointments.messages.cancelled_success'),
            __('booking::appointments.messages.cancelled_description')
        );
    }

    /**
     * Show reschedule information.
     */
    protected function rescheduleAppointment(Appointment $appointment)
    {
        // For reschedule, we show contact information
        $branch = $appointment->branch;
        $phone = $branch?->phone ?? '';

        return $this->renderResponse(
            'info',
            __('booking::appointments.messages.reschedule_contact'),
            __('booking::appointments.messages.reschedule_description', ['phone' => $phone])
        );
    }

    /**
     * Get appointment details for display.
     */
    protected function getAppointmentDetails(Appointment $appointment): string
    {
        $date = $appointment->start_time->format('d/m/Y');
        $time = $appointment->start_time->format('h:i A');
        $service = $appointment->service?->name ?? '';

        return "📅 {$date} ⏰ {$time}\n🏥 {$service}";
    }

    /**
     * Render a simple response page.
     */
    protected function renderResponse(string $type, string $title, ?string $message = null)
    {
        $icon = match ($type) {
            'success' => '✅',
            'error' => '❌',
            'info' => 'ℹ️',
            default => '',
        };

        $bgColor = match ($type) {
            'success' => '#d4edda',
            'error' => '#f8d7da',
            'info' => '#d1ecf1',
            default => '#ffffff',
        };

        $textColor = match ($type) {
            'success' => '#155724',
            'error' => '#721c24',
            'info' => '#0c5460',
            default => '#000000',
        };

        return response()->view('booking::appointment-action', [
            'icon' => $icon,
            'title' => $title,
            'message' => $message,
            'bgColor' => $bgColor,
            'textColor' => $textColor,
        ]);
    }
}
