<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property string $session_id
 * @property string $payment_intent_id
 * @property string|null $customer_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Order $order
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StripeOrderDetail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StripeOrderDetail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StripeOrderDetail query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StripeOrderDetail whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StripeOrderDetail whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StripeOrderDetail whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StripeOrderDetail whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StripeOrderDetail wherePaymentIntentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StripeOrderDetail whereSessionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StripeOrderDetail whereUpdatedAt($value)
 * @mixin IdeHelperStripeOrderDetail
 * @mixin \Eloquent
 */
class StripeOrderDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'session_id',
        'payment_intent_id',
        'customer_id',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}