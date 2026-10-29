<?php

namespace App\Data;

use App\Models\SavedAddress;

class SavedAddressData extends BaseData
{
    public function __construct(
        public int $id,
        public string $first_name,
        public string $last_name,
        public string $phone,
        public string $street_address,
        public string $city,
        public string $country,
        public string $postal_code,
        public bool $is_default,
    ) {}

    public static function fromModel(SavedAddress $address): self
    {
        return new self(
            id: $address->id,
            first_name: $address->first_name,
            last_name: $address->last_name,
            phone: $address->phone,
            street_address: $address->street_address,
            city: $address->city,
            country: $address->country,
            postal_code: $address->postal_code,
            is_default: $address->is_default,
        );
    }
}
