<?php

namespace App\Dtos;

use App\Http\Requests\CheckoutRequest;

class CheckoutDTO
{
    private string $firstName;

    private string $lastName;

    private string $phone;

    private string $address;

    private string $city;

    private string $country;

    private string $zipCode;

    private string $paymentMethod;

    public static function fromRequest(CheckoutRequest $request): self
    {
        $instance = new self();
        $instance->setFirstName($request->input('firstName'));
        $instance->setLastName($request->input('lastName'));
        $instance->setPhone($request->input('phone'));
        $instance->setAddress($request->input('address'));
        $instance->setCity($request->input('city'));
        $instance->setCountry($request->input('country'));
        $instance->setZipCode($request->input('zipCode'));
        $instance->setPaymentMethod($request->input('paymentMethod'));
        return $instance;
    }

    public static function fromArray(array $data): self{
        $instance = new self();
        $instance->setFirstName($data['firstName']);
        $instance->setLastName($data['lastName']);
        $instance->setPhone($data['phone']);
        $instance->setAddress($data['address']);
        $instance->setCity($data['city']);
        $instance->setCountry($data['country']);
        $instance->setZipCode($data['zipCode']);
        $instance->setPaymentMethod($data['paymentMethod']);
        return $instance;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): void
    {
        $this->phone = $phone;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(string $address): void
    {
        $this->address = $address;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): void
    {
        $this->city = $city;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): void
    {
        $this->country = $country;
    }

    public function getZipCode(): string
    {
        return $this->zipCode;
    }

    public function setZipCode(string $zipCode): void
    {
        $this->zipCode = $zipCode;
    }

    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(string $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }
}
