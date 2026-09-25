<?php

namespace App\Notifications;

use App\Models\Shift;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShiftCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Shift $shift,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Shift created')
            ->greeting('Hi there,')
            ->line('A new shift has been created with the following details:')
            ->line(
                "Employee: {$this->shift->employee_id}"
            )
            ->line(
                "Date: {$this->shift->date->format('Y-m-d')}"
            )
            ->line(
                "Time: {$this->shift->start_time} - {$this->shift->end_time}"
            )
            ->line(
                "Country: {$this->shift->country_code}"
            )
            ->line('Please make sure to review the shift details and take necessary actions.')
        ;
    }
}
