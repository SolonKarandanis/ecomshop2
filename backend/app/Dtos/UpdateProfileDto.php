<?php

namespace App\Dtos;

use App\Attributes\Required;
use App\Http\Requests\UpdateProfileRequest;
use App\Validation\Concerns\ValidatesAttributes;

class UpdateProfileDto
{
    use ValidatesAttributes;
    #[Required]
    private string $name;
    #[Required]
    private string $email;

    public static function fromRequest(UpdateProfileRequest $request): self
    {
        $instance = new self();
        $instance->setName($request->input('name'));
        $instance->setEmail($request->input('email'));
        $instance->validate();
        return $instance;
    }

    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->setName($data['name']);
        $instance->setEmail($data['email']);
        $instance->validate();
        return $instance;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }
}
