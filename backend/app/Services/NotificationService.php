<?php

namespace App\Services;

use App\Repositories\NotificationRepository;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationService
{

    public function __construct(
        private readonly NotificationRepository $notificationRepository
    ){}

    public function getUsersNotifications(int $userId):LengthAwarePaginator|array{
        return $this->notificationRepository->getUsersNotifications($userId);
    }

    public function getUsersUnreadNotifications(int $userId):DatabaseNotificationCollection{
        return $this->notificationRepository->getUsersUnreadNotifications($userId);
    }

    public function getUsersUnreadNotificationsCount(int $userId):int{
        return $this->notificationRepository->getUsersUnreadNotificationsCount($userId);
    }

    public function markNotificationsAsRead(int $userId, array $notificationIds): void
    {
        $this->notificationRepository->markNotificationsAsRead($userId, $notificationIds);
    }
}
