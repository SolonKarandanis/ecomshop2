<?php

namespace App\Notifications;

use App\Enums\NotificationEventTypeEnum;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Tells an admin that a Supplier registered and is waiting for approval.
 */
class SupplierAwaitingApprovalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $supplierId,
        private readonly string $supplierName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event_type' => NotificationEventTypeEnum::SUPPLIER_AWAITING_APPROVAL->value,
            'user_id' => $this->supplierId,
            // Admins approve Suppliers in Filament.
            'url' => route('filament.admin.resources.users.view', $this->supplierId),
            'message' => "Supplier {$this->supplierName} has registered and is awaiting approval.",
        ];
    }
}
