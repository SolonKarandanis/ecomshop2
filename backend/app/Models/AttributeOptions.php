<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read \App\Models\Attribute|null $attribute
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttributeOptions newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttributeOptions newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttributeOptions query()
 * @property int $id
 * @property int $attribute_id
 * @property string $option_name
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttributeOptions whereAttributeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttributeOptions whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AttributeOptions whereOptionName($value)
 * @mixin IdeHelperAttributeOptions
 * @mixin \Eloquent
 */
class AttributeOptions extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'attribute_options';

    protected $fillable = [
        'attribute_id',
        'option_name'
    ];

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class, 'attribute_id', 'id');
    }
}
