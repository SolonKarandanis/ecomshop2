<?php

namespace App\Notifications;

use App\Enums\NotificationEventTypeEnum;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a Supplier an admin approved their account. Also sent by email, since
 * they couldn't log in to see a database Notification until now.
 */
class SupplierApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event_type' => NotificationEventTypeEnum::SUPPLIER_APPROVED->value,
            'user_id' => $notifiable->id,
            'url' => $this->loginUrl(),
            'message' => 'Your Supplier account has been approved.',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Supplier account is approved - EcomShop')
            ->greeting("Hello {$notifiable->name},")
            ->line('An admin has approved your Supplier account. You can now log in and start selling.')
            ->action('Log in', $this->loginUrl());
    }

    private function loginUrl(): string
    {
        return config('app.frontend_url').'/login';
    }
}
