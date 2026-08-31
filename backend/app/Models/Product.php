<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Laravel\Scout\Searchable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Stripe\Climate\Supplier;

/**
 * @property int $id
 * @property int $category_id
 * @property int $brand_id
 * @property string $name
 * @property string $slug
 * @property string|null $images
 * @property string|null $description
 * @property numeric $price
 * @property int $is_active
 * @property int $is_featured
 * @property int $in_stock
 * @property int $on_sale
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereBrandId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereImages($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereInStock($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereIsFeatured($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereOnSale($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereUpdatedAt($value)
 * @property-read \App\Models\Brand $brand
 * @property-read \App\Models\Category $category
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OrderItem> $orderItems
 * @property-read int|null $order_items_count
 * @property-read \App\Models\ProductAttributeValues|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Attribute> $attributes
 * @property-read int|null $attributes_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProductAttributeValues> $productAttributeValues
 * @property-read int|null $product_attribute_values_count
 * @property-read mixed $color_attribute_values
 * @property-read mixed $gpu_attribute_values
 * @property-read mixed $hard_drive_attribute_values
 * @property-read mixed $keyboard_attribute_values
 * @property-read mixed $panel_type_attribute_values
 * @property-read mixed $ram_attribute_values
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @method static \Database\Factories\ProductFactory factory($count = null, $state = [])
 * @property numeric|null $average_rating
 * @property int $reviews_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereAverageRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereReviewsCount($value)
 * @mixin IdeHelperProduct
 * @property int $supplier_id
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereSupplierId($value)
 * @property-read \App\Models\User $supplier
 * @mixin \Eloquent
 */
class Product extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, Searchable;

    protected $fillable=[
        'category_id',
        'brand_id',
        'name',
        'slug',
        'description',
        'price',
        'is_active',
        'is_featured',
        'in_stock',
        'on_sale',
        'supplier_id',
    ];

    protected $casts=[];

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(100);
        $this->addMediaConversion('small')
            ->width(480);
        $this->addMediaConversion('large')
            ->width(1200);
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'brand_id' => $this->brand_id,
            'supplier_id' => $this->supplier_id,
            'price' => (float) $this->price,
            'is_featured' => (bool) $this->is_featured,
            'on_sale' => (bool) $this->on_sale,
            'created_at' => $this->created_at?->timestamp,
            'average_rating' => $this->average_rating !== null ? (float) $this->average_rating : null,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return (bool) $this->is_active;
    }

    public function searchableAs(): string
    {
        return 'idx_products';
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo{
        return $this->belongsTo(Brand::class);
    }

    public function orderItems(): HasMany{
        return $this->hasMany(OrderItem::class);
    }

    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'product_attribute_values', 'product_id', 'attribute_id')
            ->using(ProductAttributeValues::class);
    }

    public function productAttributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValues::class);
    }

    public function getColorAttributeValuesAttribute()
    {
        return $this->productAttributeValues->where('attribute.name', 'attribute.color');
    }

    public function getPanelTypeAttributeValuesAttribute()
    {
        return $this->productAttributeValues->where('attribute.name', 'attribute.panel.type');
    }

    public function getHardDriveAttributeValuesAttribute()
    {
        return $this->productAttributeValues->where('attribute.name', 'attribute.hard.drive');
    }

    public function getKeyboardAttributeValuesAttribute()
    {
        return $this->productAttributeValues->where('attribute.name', 'attribute.keyboard');
    }

    public function getRamAttributeValuesAttribute()
    {
        return $this->productAttributeValues->where('attribute.name', 'attribute.ram');
    }

    public function getGpuAttributeValuesAttribute()
    {
        return $this->productAttributeValues->where('attribute.name', 'attribute.gpu');
    }


    protected function checkIfProductAttributeValueExists(ProductAttributeValues|null $productAttributeValue):bool{
        return $productAttributeValue && $productAttributeValue->hasMedia('product-attribute-images');
    }

    public function getThumbnailImage():string
    {
        $productAttributeValue = $this->getColorAttributeValuesAttribute()->first();
        if ($this->checkIfProductAttributeValueExists($productAttributeValue)) {
            return $productAttributeValue->getFirstMediaUrl('product-attribute-images', 'thumb');
        }

        return '';
    }

    public function getSmallImage():string
    {
        $productAttributeValue = $this->getColorAttributeValuesAttribute()->first();
        if ($this->checkIfProductAttributeValueExists($productAttributeValue)) {
            return $productAttributeValue->getFirstMediaUrl('product-attribute-images', 'small');
        }

        return '';
    }

    public function getLargeImage():string
    {
        $productAttributeValue = $this->getColorAttributeValuesAttribute()->first();
        if ($this->checkIfProductAttributeValueExists($productAttributeValue)) {
            return $productAttributeValue->getFirstMediaUrl('product-attribute-images', 'large');
        }

        return '';
    }
}
