<?php

namespace App\Http\Controllers;

use App\Data\NotificationData;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Spatie\LaravelData\DataCollection;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function index(Request $request): DataCollection
    {
        return NotificationData::collect(
            $this->notificationService->getUsersNotifications($request->user()->id),
            DataCollection::class,
        )->wrap('data');
    }

    public function unread(Request $request): DataCollection
    {
        $userId = $request->user()->id;
        $notifications = $this->notificationService->getUsersUnreadNotifications($userId);
        $this->notificationService->markNotificationsAsRead($userId, $notifications->pluck('id')->all());

        return NotificationData::collect($notifications, DataCollection::class)->wrap('data');
    }

    public function unreadCount(Request $request): array
    {
        return ['count' => $this->notificationService->getUsersUnreadNotificationsCount($request->user()->id)];
    }
}
