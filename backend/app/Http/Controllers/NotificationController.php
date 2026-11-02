<?php

namespace App\Http\Controllers;

use App\Data\NotificationData;
use App\Http\Requests\MarkNotificationsReadRequest;
use App\Http\Requests\NotificationSearchRequest;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\PaginatedDataCollection;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function index(NotificationSearchRequest $request): PaginatedDataCollection
    {
        return NotificationData::collect(
            $this->notificationService->getUsersNotifications($request->user()->id, $request->integer('per_page', 20)),
            PaginatedDataCollection::class,
        );
    }

    // Read-only: the header bell lists these, and a Notification is marked
    // read only when the user opens it (markRead) or clears them all.
    public function unread(Request $request): DataCollection
    {
        return NotificationData::collect(
            $this->notificationService->getUsersUnreadNotifications($request->user()->id),
            DataCollection::class,
        )->wrap('data');
    }

    /**
     * @return array{count: int}
     */
    public function unreadCount(Request $request): array
    {
        return ['count' => $this->notificationService->getUsersUnreadNotificationsCount($request->user()->id)];
    }

    /**
     * @return array{count: int} the unread count afterwards, for the header bell
     */
    public function markRead(MarkNotificationsReadRequest $request): array
    {
        $userId = $request->user()->id;
        $this->notificationService->markNotificationsAsRead($userId, $request->validated('ids'));

        return ['count' => $this->notificationService->getUsersUnreadNotificationsCount($userId)];
    }

    /**
     * @return array{count: int}
     */
    public function markAllRead(Request $request): array
    {
        $userId = $request->user()->id;
        $this->notificationService->markAllNotificationsAsRead($userId);

        return ['count' => $this->notificationService->getUsersUnreadNotificationsCount($userId)];
    }
}
