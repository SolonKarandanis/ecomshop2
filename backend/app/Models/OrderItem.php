<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property int $product_id
 * @property int $quantity
 * @property Money|null $unit_amount
 * @property Money|null $total_amount
 * @property array|null $attributes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
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
 *
 * @property-read Order $order
 * @property-read Product $product
 *
 * @mixin IdeHelperOrderItem
 * @mixin \Eloquent
 */
class OrderItem extends Model
{
    use HasFactory;

    /**
     * An unsaved Order Item for what's in a cart line: product, quantity, prices
     * and the chosen attributes. A named constructor rather than an override of
     * Eloquent's __construct(array $attributes), which other code relies on.
     */
    public static function fromCartItem(CartItem $cartItem): self
    {
        return new self([
            'product_id' => $cartItem->product_id,
            'quantity' => $cartItem->quantity,
            'unit_amount' => $cartItem->unit_price,
            'total_amount' => $cartItem->total_price,
            // getAttribute(), not ->attributes: inside a model class that reaches
            // Eloquent's protected $attributes array instead of the column.
            'attributes' => $cartItem->getAttribute('attributes'),
        ]);
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
