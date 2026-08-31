<?php

namespace App\Repositories;

use App\Models\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationRepository
{

    public function modelQuery(): Builder| Notification{
        return Notification::query();
    }

    public function getUsersNotifications(int $userId):LengthAwarePaginator|array{
        return $this->modelQuery()
            ->forUser($userId)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
    }

    public function getUsersUnreadNotifications(int $userId): DatabaseNotificationCollection{
        return $this->modelQuery()
            ->forUser($userId)
            ->where('read_at', null)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();
    }

    public function getUsersUnreadNotificationsCount(int $userId):int{
        return $this->modelQuery()
            ->forUser($userId)
            ->where('read_at', null)
            ->count();
    }

    public function markNotificationAsRead(int $userId,int $notificationId){
        $notification = $this->modelQuery()->forUser($userId)->find($notificationId);
        $notification->read_at = now();
        $notification->save();
    }

    public function markNotificationsAsRead(int $userId, array $notificationIds): void
    {
        $this->modelQuery()
            ->forUser($userId)
            ->whereIn('id', $notificationIds)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
