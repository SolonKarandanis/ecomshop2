<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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
 * @property string|null $image
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brand whereUpdatedAt($value)
 *
 * @property-read Collection<int, Product> $products
 * @property-read int|null $products_count
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 *
 * @method static \Database\Factories\BrandFactory factory($count = null, $state = [])
 *
 * @mixin IdeHelperBrand
 * @mixin \Eloquent
 */
class Brand extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = ['name', 'slug', 'image', 'is_active'];

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
        return $this->getFirstMediaUrl('brand-images', 'thumb') ?: 'https://via.placeholder.com/100';
    }

    public function getSmallImage(): string
    {
        return $this->getFirstMediaUrl('brand-images', 'small') ?: 'https://via.placeholder.com/100';
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
