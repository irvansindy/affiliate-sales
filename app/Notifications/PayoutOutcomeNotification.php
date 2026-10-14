<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayoutOutcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Payout $payout) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $paid = $this->payout->status === PayoutStatus::Paid;

        return (new MailMessage)
            ->subject($paid ? __('payouts.mail_paid_subject') : __('payouts.mail_failed_subject'))
            ->line($paid ? __('payouts.mail_paid_body', ['amount' => number_format($this->payout->total_amount, 0, ',', '.')]) : __('payouts.mail_failed_body'));
    }
}
