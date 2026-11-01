<?php

namespace App\Notifications;

use App\Enums\NotificationEventTypeEnum;
use App\Models\User;
use App\Support\FrontendUrl;
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

    /**
     * @param  User  $notifiable
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'event_type' => NotificationEventTypeEnum::SUPPLIER_APPROVED->value,
            'user_id' => $notifiable->id,
            'url' => $this->loginUrl($notifiable),
            'message_key' => NotificationEventTypeEnum::SUPPLIER_APPROVED->value,
            'message_params' => [],
        ];
    }

    /**
     * Sent in the Supplier's preferred locale (HasLocalePreference).
     *
     * @param  User  $notifiable
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('mail.supplier_approved.subject'))
            ->greeting(__('mail.greeting', ['name' => $notifiable->name]))
            ->line(__('mail.supplier_approved.line'))
            ->action(__('mail.supplier_approved.action'), $this->loginUrl($notifiable));
    }

    private function loginUrl(User $notifiable): string
    {
        return FrontendUrl::forUser($notifiable, '/login');
    }
}
