<?php

namespace App\Casts;

use App\ValueObjects\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Reads a decimal(10,2) money column as Money, and writes Money or a number
 * (Filament forms and request input send numeric strings) back as '19.99'.
 *
 * @implements CastsAttributes<Money|null, Money|string|int|float|null>
 */
class MoneyCast implements CastsAttributes, SerializesCastableAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        return $value === null ? null : Money::fromDecimal($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof Money => $value->toDecimal(),
            is_numeric($value) => Money::fromDecimal($value)->toDecimal(),
            default => throw new InvalidArgumentException("{$key} must be Money or a number."),
        };
    }

    // toArray()/attributesToArray() (Filament form fills, cookies) get the same '19.99' a decimal column gives.
    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value?->toDecimal();
    }
}
