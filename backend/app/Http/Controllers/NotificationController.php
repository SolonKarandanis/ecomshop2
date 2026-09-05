<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return NotificationResource::collection(
            $this->notificationService->getUsersNotifications($request->user()->id)
        );
    }

    public function unread(Request $request): AnonymousResourceCollection
    {
        $userId = $request->user()->id;
        $notifications = $this->notificationService->getUsersUnreadNotifications($userId);
        $this->notificationService->markNotificationsAsRead($userId, $notifications->pluck('id')->all());

        return NotificationResource::collection($notifications);
    }

    public function unreadCount(Request $request): array
    {
        return ['count' => $this->notificationService->getUsersUnreadNotificationsCount($request->user()->id)];
    }
}
