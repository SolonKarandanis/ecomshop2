<?php

namespace App\Services;

use App\Dtos\ChangePasswordDto;
use App\Dtos\CreateUserDTO;
use App\Dtos\ResetPasswordDTO;
use App\Dtos\UpdateProfileDto;
use App\Enums\UserStatusEnum;
use App\Exceptions\ProfileException;
use App\Models\User;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

#[Singleton]
class UserService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly RoleRepository $roleRepository,
    ){}

    public function createUser(CreateUserDTO $dto):User{
        $user= $this->userRepository->createUser($dto);
        event(new Registered($user));
        return $user;
    }

    public function createBuyer(CreateUserDTO $dto):User{
        $user= $this->createUser($dto);
        $buyerRole=$this->roleRepository->getBuyerRole();
        $user->assignRole($buyerRole);
        return $user;
    }

    public function createSupplier(CreateUserDTO $dto):User{
        $user= $this->createUser($dto);
        $supplierRole=$this->roleRepository->getSupplierRole();
        $user->assignRole($supplierRole);
        return $user;
    }

    public function updateProfile(User $user, UpdateProfileDto $dto): void
    {
        if (User::where('email', $dto->getEmail())->where('id', '!=', $user->id)->exists()) {
            throw ProfileException::emailTaken();
        }
        $user->name  = $dto->getName();
        $user->email = $dto->getEmail();
        $this->userRepository->saveUser($user);
    }

    public function changePassword(User $user, ChangePasswordDto $dto): void
    {
        if (!Hash::check($dto->getCurrentPassword(), $user->password)) {
            throw ProfileException::wrongCurrentPassword();
        }
        $user->password = Hash::make($dto->getNewPassword());
        $this->userRepository->saveUser($user);
    }

    public function activateUser(User $user): void
    {
        $user->status = UserStatusEnum::ACTIVE;
        $this->userRepository->saveUser($user);
    }

    public function deactivateUser(User $user): void
    {
        $user->status = UserStatusEnum::INACTIVE;
        $this->userRepository->saveUser($user);
    }

    public function getUsersWithOrderedItems(): Collection{
        return $this->userRepository->getUsersWithOrderedItems();
    }

    public function getUserWithAddresses(int $userId): ?User{
        return $this->userRepository->getUserWithAddresses($userId);
    }

    public function resetPassword(ResetPasswordDTO $dto):string{
        return Password::reset([
            'email' => $dto->getEmail(),
            'password' => $dto->getPassword(),
            'password_confirmation' => $dto->getPasswordConfirmation(),
            'token' => $dto->getToken(),
        ], function (User $user, string $password) {
            $user->forceFill([
                'password' => Hash::make($password),
            ])->setRememberToken(Str::random(60));
            $this->userRepository->saveUser($user);
            event(new PasswordReset($user));
        });
    }
}
