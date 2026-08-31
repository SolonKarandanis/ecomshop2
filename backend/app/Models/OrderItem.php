<?php

namespace App\Models;

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
 * @property numeric|null $unit_amount
 * @property numeric|null $total_amount
 * @property string|null $attributes
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
        'attributes' => 'array',
    ];

    public function getAttributeNamesAndOptions(): array
    {
        if (!$this->attributes || !is_array($this->attributes)) {
            return [];
        }

        $result = [];
        foreach ($this->attributes as $attributeId => $optionId) {
            $attribute = Attribute::find($attributeId);
            $option = AttributeOptions::find($optionId);

            if ($attribute && $option) {
                $result[$attribute->name] = $option->option_name;
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
