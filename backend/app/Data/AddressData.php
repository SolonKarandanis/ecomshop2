<?php

namespace App\Data;

use App\Models\Address;

class AddressData extends BaseData
{
    public function __construct(
        public string $first_name,
        public string $last_name,
        public string $phone,
        public string $street_address,
        public string $city,
        public string $country,
        public string $postal_code,
    ) {}

    public static function fromModel(Address $address): self
    {
        return new self(
            first_name: $address->first_name,
            last_name: $address->last_name,
            phone: $address->phone,
            street_address: $address->street_address,
            city: $address->city,
            country: $address->country,
            postal_code: $address->postal_code,
        );
    }
}
