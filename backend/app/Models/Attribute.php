<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AttributeOptions> $attributeOptions
 * @property-read int|null $attribute_options_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attribute newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attribute newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attribute query()
 * @property int $id
 * @property string $name
 * @property string $type
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attribute whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attribute whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attribute whereType($value)
 * @mixin IdeHelperAttribute
 * @mixin \Eloquent
 */
class Attribute extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'attributes';

    protected $fillable = ['name', 'type'];

    public function attributeOptions(): HasMany
    {
        return $this->hasMany(AttributeOptions::class , 'attribute_id', 'id');
    }
}
