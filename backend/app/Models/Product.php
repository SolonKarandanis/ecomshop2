<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Laravel\Scout\Searchable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property int $category_id
 * @property int $brand_id
 * @property string $name
 * @property string $slug
 * @property string|null $images
 * @property string|null $description
 * @property Money $price
 * @property bool $is_active
 * @property bool $is_featured
 * @property bool $in_stock
 * @property bool $on_sale
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
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
 *
 * @property-read Brand $brand
 * @property-read Category $category
 * @property-read Collection<int, OrderItem> $orderItems
 * @property-read int|null $order_items_count
 * @property-read ProductAttributeValues|null $pivot
 * @property-read Collection<int, Attribute> $attributes
 * @property-read int|null $attributes_count
 * @property-read Collection<int, ProductAttributeValues> $productAttributeValues
 * @property-read int|null $product_attribute_values_count
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 *
 * @method static \Database\Factories\ProductFactory factory($count = null, $state = [])
 *
 * @property numeric|null $average_rating
 * @property int $reviews_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereAverageRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereReviewsCount($value)
 *
 * @mixin IdeHelperProduct
 *
 * @property int $supplier_id
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereSupplierId($value)
 *
 * @property-read User $supplier
 *
 * @mixin \Eloquent
 */
class Product extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, Searchable;

    // The Spatie Media Library collection Filament's ProductForm uploads to.
    public const IMAGES_COLLECTION = 'product-images';

    public const MAX_IMAGES = 5;

    // Per image, in kilobytes (Laravel's `max` rule for files).
    public const MAX_IMAGE_KB = 5120;

    protected $fillable = [
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

    protected $casts = [
        'price' => MoneyCast::class,
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'in_stock' => 'boolean',
        'on_sale' => 'boolean',
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

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'brand_id' => $this->brand_id,
            'supplier_id' => $this->supplier_id,
            'price' => $this->price->toFloat(),
            'is_featured' => $this->is_featured,
            'on_sale' => $this->on_sale,
            'created_at' => $this->created_at?->timestamp,
            'average_rating' => $this->average_rating !== null ? (float) $this->average_rating : null,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->is_active;
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

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function orderItems(): HasMany
    {
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

    public function getThumbnailImage(): string
    {
        return $this->colorImageUrl('thumb');
    }

    public function getSmallImage(): string
    {
        return $this->colorImageUrl('small');
    }

    public function getLargeImage(): string
    {
        return $this->colorImageUrl('large');
    }

    /**
     * A Product's images are attached to its color attribute value, so its picture
     * is that value's image in the given media conversion, or '' if it has none.
     */
    private function colorImageUrl(string $conversion): string
    {
        $colorValue = $this->productAttributeValues->firstWhere('attribute.name', 'attribute.color');

        return $colorValue?->hasMedia('product-attribute-images')
            ? $colorValue->getFirstMediaUrl('product-attribute-images', $conversion)
            : '';
    }
}
