<?php

namespace App\Dtos;

use App\Http\Requests\ChangePasswordRequest;

class ChangePasswordDto
{
    private string $currentPassword;
    private string $newPassword;
    private string $newPasswordConfirmation;

    public static function fromRequest(ChangePasswordRequest $request): self
    {
        $instance = new self();
        $instance->setCurrentPassword($request->input('currentPassword'));
        $instance->setNewPassword($request->input('newPassword'));
        $instance->setNewPasswordConfirmation($request->input('newPasswordConfirmation'));
        return $instance;
    }

    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->setCurrentPassword($data['currentPassword']);
        $instance->setNewPassword($data['newPassword']);
        $instance->setNewPasswordConfirmation($data['newPasswordConfirmation']);
        return $instance;
    }

    public function getCurrentPassword(): string
    {
        return $this->currentPassword;
    }

    public function setCurrentPassword(string $currentPassword): void
    {
        $this->currentPassword = $currentPassword;
    }

    public function getNewPassword(): string
    {
        return $this->newPassword;
    }

    public function setNewPassword(string $newPassword): void
    {
        $this->newPassword = $newPassword;
    }

    public function getNewPasswordConfirmation(): string
    {
        return $this->newPasswordConfirmation;
    }

    public function setNewPasswordConfirmation(string $newPasswordConfirmation): void
    {
        $this->newPasswordConfirmation = $newPasswordConfirmation;
    }
}
