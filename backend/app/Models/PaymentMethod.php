<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $resource_key
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentMethod whereResourceKey($value)
 * @method static \Database\Factories\PaymentMethodFactory factory($count = null, $state = [])
 * @mixin IdeHelperPaymentMethod
 * @mixin \Eloquent
 */
class PaymentMethod extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $fillable = [
        'resource_key',
    ];

}
