<?php

namespace Modules\Auth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Carbon\Carbon;

class UserLimitExceededNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $currentCount,
        protected int $limit,
        protected Carbon $gracePeriodEnds
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $overageCount = $this->currentCount - $this->limit;
        $daysRemaining = now()->diffInDays($this->gracePeriodEnds, false);

        return (new MailMessage)
            ->subject(__('auth::limits.email.subject'))
            ->greeting(__('auth::limits.email.greeting', ['name' => $notifiable->first_name]))
            ->line(__('auth::limits.email.exceeded_message', [
                'current' => $this->currentCount,
                'limit' => $this->limit,
                'overage' => $overageCount,
            ]))
            ->line(__('auth::limits.email.grace_period', [
                'days' => max(0, $daysRemaining),
                'date' => $this->gracePeriodEnds->format('M d, Y'),
            ]))
            ->action(__('auth::limits.email.action'), url('/admin/settings/subscription'))
            ->line(__('auth::limits.email.contact_support'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'user_limit_exceeded',
            'title' => __('auth::limits.notification.title'),
            'body' => __('auth::limits.notification.body', [
                'current' => $this->currentCount,
                'limit' => $this->limit,
            ]),
            'current_count' => $this->currentCount,
            'limit' => $this->limit,
            'grace_period_ends' => $this->gracePeriodEnds->toIso8601String(),
            'action_url' => '/admin/settings/subscription',
        ];
    }
}
