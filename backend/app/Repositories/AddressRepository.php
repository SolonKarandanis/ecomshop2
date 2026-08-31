<?php

namespace App\Repositories;

use App\Dtos\CheckoutDTO;
use App\Models\Address;
use Illuminate\Database\Eloquent\Builder;

class AddressRepository
{

    public function modelQuery(): Builder| Address{
        return Address::query();
    }

    public function create(int $orderId,CheckoutDTO $checkoutDTO):void{
        $this->modelQuery()->create([
            'user_id'=>auth()->user()->id,
            'order_id' => $orderId,
            'first_name' => $checkoutDTO->getFirstName(),
            'last_name' => $checkoutDTO->getLastName(),
            'street_address' => $checkoutDTO->getAddress(),
            'phone' => $checkoutDTO->getPhone(),
            'city' => $checkoutDTO->getCity(),
            'country' => $checkoutDTO->getCountry(),
            'postal_code' => $checkoutDTO->getZipCode(),
        ]);
    }
}
