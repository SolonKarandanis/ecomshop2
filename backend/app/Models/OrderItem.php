<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Attribute;
use App\Models\AttributeOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int $product_id
 * @property int $quantity
 * @property \App\ValueObjects\Money|null $unit_amount
 * @property \App\ValueObjects\Money|null $total_amount
 * @property array|null $attributes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereUnitAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereAttributes($value)
 * @property-read \App\Models\Order $order
 * @property-read \App\Models\Product $product
 * @mixin IdeHelperOrderItem
 * @mixin \Eloquent
 */
class OrderItem extends Model
{
    use HasFactory;


    public function __construct(CartItem $cartItem = null)
    {
        parent::__construct();
        if ($cartItem) {
            $this->product_id = $cartItem->product_id;
            $this->quantity = $cartItem->quantity;
            $this->unit_amount = $cartItem->unit_price;
            $this->total_amount = $cartItem->total_price;
            $this->setAttribute('attributes', $cartItem->attributes);
        }
    }

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'unit_amount',
        'total_amount',
        'attributes',
    ];

    protected $casts = [
        'unit_amount' => MoneyCast::class,
        'total_amount' => MoneyCast::class,
        'attributes' => 'array',
    ];

    public function getAttributeNamesAndOptions(): array
    {
        // getAttribute(), not $this->attributes: that's Eloquent's array of every column.
        $chosen = $this->getAttribute('attributes');
        if (! $chosen || ! is_array($chosen)) {
            return [];
        }

        $attributeNames = Attribute::whereIn('id', array_keys($chosen))->pluck('name', 'id');
        $optionNames = AttributeOptions::whereIn('id', array_values($chosen))->pluck('option_name', 'id');

        $result = [];
        foreach ($chosen as $attributeId => $optionId) {
            if (isset($attributeNames[$attributeId], $optionNames[$optionId])) {
                $result[$attributeNames[$attributeId]] = $optionNames[$optionId];
            }
        }

        return $result;
    }

    public function order():BelongsTo{
        return $this->belongsTo(Order::class);
    }

    public function product():BelongsTo{
        return $this->belongsTo(Product::class);
    }
}
