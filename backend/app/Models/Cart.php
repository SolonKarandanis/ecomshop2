<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CartItem> $cartItems
 * @property-read int|null $cart_items_count
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cart newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cart newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cart query()
 * @property int $id
 * @property int $user_id
 * @property \App\ValueObjects\Money $total_price
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cart whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cart whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cart whereTotalPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cart whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Cart whereUserId($value)
 * @mixin IdeHelperCart
 * @mixin \Eloquent
 */
class Cart extends Model
{
    use HasFactory;

    protected $table = 'carts';

    protected $fillable=[
        'user_id',
        'total_price'
    ];

    protected $casts = [
        'total_price' => MoneyCast::class,
    ];

    public function recalculateCartTotalPrice(): void
    {
        $total = Money::zero();
        foreach ($this->cartItems as $item) {
            $total = $total->add($item->total_price);
        }
        $this->total_price = $total;
    }

    public function user():BelongsTo{
        return $this->belongsTo(User::class);
    }

    public function cartItems():HasMany{
        return $this->hasMany(CartItem::class);
    }
}
