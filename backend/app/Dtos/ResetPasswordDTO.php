<?php

namespace App\Dtos;

use App\Http\Requests\Auth\ResetPasswordRequest;

class ResetPasswordDTO
{
    private string $email;
    private string $password;
    private string $passwordConfirmation;
    private string $token;

    public static function fromRequest(ResetPasswordRequest $request): self
    {
        $instance = new self();
        $instance->setEmail($request->input('email'));
        $instance->setPassword($request->input('password'));
        $instance->setPasswordConfirmation($request->input('password_confirmation'));
        $instance->setToken($request->input('token'));
        return $instance;
    }

    public static function fromArray(array $data): self{
        $instance = new self();
        $instance->setEmail($data['email']);
        $instance->setPassword($data['password']);
        $instance->setPasswordConfirmation($data['password_confirmation']);
        $instance->setToken($data['token']);
        return $instance;
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

    public function getPasswordConfirmation(): string
    {
        return $this->passwordConfirmation;
    }

    public function setPasswordConfirmation(string $passwordConfirmation): void
    {
        $this->passwordConfirmation = $passwordConfirmation;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }
}
