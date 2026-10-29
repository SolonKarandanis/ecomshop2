<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property \App\ValueObjects\Money|null $grand_total
 * @property int|null $payment_method_id
 * @property string|null $payment_status
 * @property string $order_status
 * @property string|null $currency
 * @property \App\ValueObjects\Money|null $shipping_amount
 * @property string|null $shipping_method
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereGrandTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereOrderStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order wherePaymentMethodId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order wherePaymentStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereShippingAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereShippingMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereUserId($value)
 *
 * @property-read Address|null $address
 * @property-read Collection<int, OrderItem> $items
 * @property-read int|null $items_count
 * @property-read User $user
 * @property-read PaymentMethod $paymentMethod
 * @property-read StripeOrderDetail|null $stripeOrderDetail
 * @property-read Collection<int, OrderStatusHistory> $statusHistory
 * @property-read mixed $created_at_diff
 *
 * @method static \Database\Factories\OrderFactory factory($count = null, $state = [])
 *
 * @mixin IdeHelperOrder
 *
 * @property int $supplier_id
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereSupplierId($value)
 *
 * @mixin \Eloquent
 */
class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'supplier_id',
        'grand_total',
        'payment_method_id',
        'payment_status',
        'order_status',
        'currency',
        'shipping_method',
        'shipping_amount',
        'notes',
    ];

    protected $casts = [
        'grand_total' => MoneyCast::class,
        'shipping_amount' => MoneyCast::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function address(): HasOne
    {
        return $this->hasOne(Address::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function stripeOrderDetail(): HasOne
    {
        return $this->hasOne(StripeOrderDetail::class);
    }

    /**
     * Oldest first. The id breaks ties between entries written in the same second
     * (e.g. an Order created and paid in one request).
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->oldest()->orderBy('id');
    }

    public function createdAtDiff(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->created_at->diffForHumans()
        );
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supplier_id');
    }
}
