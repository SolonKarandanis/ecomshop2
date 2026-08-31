<?php

namespace App\Dtos;

use App\Enums\RolesEnum;
use App\Http\Requests\Auth\RegisterUserRequest;

class CreateUserDTO
{
    private string $name;
    private string $email;
    private string $password;

    private string $role;

    public static function fromArray(array $data): self{
        $instance = new self();
        $instance->setName($data['name']);
        $instance->setEmail($data['email']);
        $instance->setPassword($data['password']);
        $instance->setRole($data['role']?? RolesEnum::ROLE_BUYER->value);
        return $instance;
    }

    public static function fromRequest(RegisterUserRequest $request): self
    {
        $instance = new self();
        $instance->setName($request->input('name'));
        $instance->setEmail($request->input('email'));
        $instance->setPassword($request->input('password'));
        $instance->setRole($request->filled('role')? $request->input('role') : RolesEnum::ROLE_BUYER->value);
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

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
    }
}
