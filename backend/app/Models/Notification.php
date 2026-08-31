<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification as BaseNotification;

/**
 * @property string $id
 * @property string $type
 * @property string $notifiable_type
 * @property int $notifiable_id
 * @property array $data
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model $notifiable
 * @method static \Illuminate\Notifications\DatabaseNotificationCollection<int, static> all($columns = ['*'])
 * @method static Builder<static>|Notification forUser(\App\Models\User|string|int $user)
 * @method static \Illuminate\Notifications\DatabaseNotificationCollection<int, static> get($columns = ['*'])
 * @method static Builder<static>|Notification newModelQuery()
 * @method static Builder<static>|Notification newQuery()
 * @method static Builder<static>|Notification ofType(string $type)
 * @method static Builder<static>|Notification onlyRead()
 * @method static Builder<static>|Notification onlyUnread()
 * @method static Builder<static>|Notification query()
 * @method static Builder<static>|Notification read()
 * @method static Builder<static>|Notification unread()
 * @method static Builder<static>|Notification whereCreatedAt($value)
 * @method static Builder<static>|Notification whereData($value)
 * @method static Builder<static>|Notification whereId($value)
 * @method static Builder<static>|Notification whereNotifiableId($value)
 * @method static Builder<static>|Notification whereNotifiableType($value)
 * @method static Builder<static>|Notification whereReadAt($value)
 * @method static Builder<static>|Notification whereType($value)
 * @method static Builder<static>|Notification whereUpdatedAt($value)
 * @mixin IdeHelperNotification
 * @mixin \Eloquent
 */
class Notification extends BaseNotification
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'notifications';

    /**
     * Scope a query to only include unread notifications.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeOnlyUnread(Builder $query): Builder
    {
        $query->whereNull('read_at');

        return $query;
    }

    /**
     * Scope a query to only include read notifications.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeOnlyRead(Builder $query): Builder
    {
        $query->whereNotNull('read_at');

        return $query;
    }

    /**
     * Scope a query to only include notifications of a certain type.
     *
     * @param Builder $query
     * @param string $type
     * @return Builder
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        $query->where('type', $type);

        return $query;
    }

    /**
     * Scope a query to only include notifications for a specific user.
     *
     * @param Builder $query
     * @param int|string|User $user
     * @return Builder
     */
    public function scopeForUser(Builder $query, User|int|string $user): Builder
    {
        $userId = $user instanceof User ? $user->id : $user;
        $query->where('notifiable_type', User::class)
            ->where('notifiable_id', $userId);

        return $query;
    }
}
