<?php

namespace App\Models;

use App\Models\Concerns\FallsBackPerAttribute;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Product> $products
 * @property-read int|null $products_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereUpdatedAt($value)
 *
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 *
 * @method static \Database\Factories\CategoryFactory factory($count = null, $state = [])
 *
 * @mixin IdeHelperCategory
 * @mixin \Eloquent
 */
class Category extends Model implements HasMedia, TranslatableContract
{
    use FallsBackPerAttribute, HasFactory, InteractsWithMedia, Translatable {
        FallsBackPerAttribute::getAttributeOrFallback insteadof Translatable;
    }

    // Stored per language in category_translations (#81).
    public array $translatedAttributes = ['name'];

    // Shown wherever a Category is, so its translations always load with it.
    protected $with = ['translations'];

    protected $fillable = ['slug', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(100);
        $this->addMediaConversion('small')
            ->width(480);
        $this->addMediaConversion('large')
            ->width(1200);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function getThumbnailImage(): string
    {
        return $this->getFirstMediaUrl('category-images', 'thumb') ?: 'https://via.placeholder.com/100';
    }

    public function getSmallImage(): string
    {
        return $this->getFirstMediaUrl('category-images', 'small') ?: 'https://via.placeholder.com/100';
    }
}
