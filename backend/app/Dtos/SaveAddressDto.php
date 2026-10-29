<?php

namespace App\Dtos;

use App\Http\Requests\StoreSavedAddressRequest;
use App\Models\SavedAddress;
use Illuminate\Support\Arr;

class SaveAddressDto
{
    /** @var array<string, string> Only the address columns that were sent. */
    private array $fields;

    public static function fromRequest(StoreSavedAddressRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->fields = Arr::only($data, SavedAddress::ADDRESS_FIELDS);

        return $instance;
    }

    // Checkout uses its own field names (firstName, address, zipCode, …).
    public static function fromCheckout(CheckoutDTO $dto): self
    {
        return self::fromArray([
            'first_name' => $dto->getFirstName(),
            'last_name' => $dto->getLastName(),
            'phone' => $dto->getPhone(),
            'street_address' => $dto->getAddress(),
            'city' => $dto->getCity(),
            'country' => $dto->getCountry(),
            'postal_code' => $dto->getZipCode(),
        ]);
    }

    public function getFields(): array
    {
        return $this->fields;
    }
}
