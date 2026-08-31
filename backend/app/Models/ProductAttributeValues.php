<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property-read \App\Models\Attribute|null $attribute
 * @property-read \App\Models\AttributeOptions|null $attributeOption
 * @property-read \App\Models\Product|null $product
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductAttributeValues newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductAttributeValues newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductAttributeValues query()
 * @property int $id
 * @property int $product_id
 * @property int $attribute_id
 * @property int $attribute_option_id
 * @property string|null $attribute_value_method
 * @property float|null $attribute_value
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductAttributeValues whereAttributeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductAttributeValues whereAttributeOptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductAttributeValues whereAttributeValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductAttributeValues whereAttributeValueMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductAttributeValues whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductAttributeValues whereProductId($value)
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @mixin IdeHelperProductAttributeValues
 * @mixin \Eloquent
 */
class ProductAttributeValues extends Pivot implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;
    public $timestamps = false;

    protected $table = 'product_attribute_values';

    protected $fillable=[
        'product_id',
        'attribute_id',
        'attribute_option_id',
        'attribute_value_method',
        'attribute_value'
    ];

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(100);
        $this->addMediaConversion('small')
            ->width(480);
        $this->addMediaConversion('large')
            ->width(1200);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function attribute(): BelongsTo{
        return $this->belongsTo(Attribute::class, 'attribute_id', 'id');
    }
    public function attributeOption(): BelongsTo{
        return $this->belongsTo(AttributeOptions::class, 'attribute_option_id', 'id');
    }
}
