<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $locale
 * @property string $name
 *
 * One language's name of a Category (#81). Managed through
 * Category's Translatable trait rather than used directly.
 */
class CategoryTranslation extends Model
{
    public $timestamps = false;

    protected $fillable = ['name'];
}
