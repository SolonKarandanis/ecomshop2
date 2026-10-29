<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An address in a User's address book. Unlike {@see Address}, it isn't tied to an Order.
 *
 * @property int $id
 * @property int $user_id
 * @property string $first_name
 * @property string $last_name
 * @property string $phone
 * @property string $street_address
 * @property string $city
 * @property string $country
 * @property string $postal_code
 * @property bool $is_default
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 */
class SavedAddress extends Model
{
    use HasFactory;

    public const ADDRESS_FIELDS = [
        'first_name',
        'last_name',
        'phone',
        'street_address',
        'city',
        'country',
        'postal_code',
    ];

    protected $fillable = [
        'user_id',
        ...self::ADDRESS_FIELDS,
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
