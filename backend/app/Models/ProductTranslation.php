<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $locale
 * @property string $name
 * @property string|null $description
 *
 * One language's name and description of a Product (#81). Managed through
 * Product's Translatable trait rather than used directly.
 */
class ProductTranslation extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'description'];
}
