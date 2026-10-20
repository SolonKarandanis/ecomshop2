<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One change to an Order's order status or payment status, recorded by
 * OrderStatusHistoryObserver. Entries are never updated, so there's no updated_at.
 *
 * @property int $id
 * @property int $order_id
 * @property string $order_status
 * @property string|null $payment_status
 * @property int|null $changed_by
 * @property Carbon|null $created_at
 * @property-read Order $order
 * @property-read User|null $changedBy
 */
class OrderStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'order_id',
        'order_status',
        'payment_status',
        'changed_by',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
