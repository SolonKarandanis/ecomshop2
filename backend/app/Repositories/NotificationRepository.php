<?php

namespace App\Repositories;

use App\Models\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationRepository
{
    /**
     * @return Builder<Notification>
     */
    public function modelQuery(): Builder
    {
        return Notification::query();
    }

    public function getUsersNotifications(int $userId, int $perPage = 20): LengthAwarePaginator|array
    {
        return $this->modelQuery()
            ->forUser($userId)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    public function getUsersUnreadNotifications(int $userId): DatabaseNotificationCollection
    {
        return $this->modelQuery()
            ->forUser($userId)
            ->onlyUnread()
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->take(5)
            ->get();
    }

    public function getUsersUnreadNotificationsCount(int $userId): int
    {
        return $this->modelQuery()
            ->forUser($userId)
            ->onlyUnread()
            ->count();
    }

    public function markNotificationsAsRead(int $userId, array $notificationIds): void
    {
        $this->modelQuery()
            ->forUser($userId)
            ->whereIn('id', $notificationIds)
            ->onlyUnread()
            ->update(['read_at' => now()]);
    }

    public function markAllNotificationsAsRead(int $userId): void
    {
        $this->modelQuery()
            ->forUser($userId)
            ->onlyUnread()
            ->update(['read_at' => now()]);
    }
}
