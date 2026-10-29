<?php

namespace App\Services;

use App\Dtos\ChangePasswordDto;
use App\Dtos\CreateUserDTO;
use App\Dtos\ResetPasswordDTO;
use App\Dtos\UpdateProfileDto;
use App\Enums\UserStatusEnum;
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
        private readonly NotificationHandlerService $notificationHandlerService,
    ){}

    public function createUser(CreateUserDTO $dto, UserStatusEnum $status = UserStatusEnum::ACTIVE):User{
        $user= $this->userRepository->createUser($dto, $status);
        event(new Registered($user));
        return $user;
    }

    public function createBuyer(CreateUserDTO $dto):User{
        $user= $this->createUser($dto);
        $buyerRole=$this->roleRepository->getBuyerRole();
        $user->assignRole($buyerRole);
        return $user;
    }

    // A Supplier can't log in or sell until an admin approves them.
    public function createSupplier(CreateUserDTO $dto):User{
        $user= $this->createUser($dto, UserStatusEnum::PENDING);
        $supplierRole=$this->roleRepository->getSupplierRole();
        $user->assignRole($supplierRole);
        $this->notificationHandlerService->supplierAwaitingApproval($user, $this->userRepository->getAdmins());
        return $user;
    }

    // UpdateProfileRequest has already rejected an email another User has.
    public function updateProfile(User $user, UpdateProfileDto $dto): void
    {
        $user->name  = $dto->getName();
        $user->email = $dto->getEmail();
        $this->userRepository->saveUser($user);
    }

    // ChangePasswordRequest has already checked the current password.
    public function changePassword(User $user, ChangePasswordDto $dto): void
    {
        $user->password = Hash::make($dto->getNewPassword());
        $this->userRepository->saveUser($user);
    }

    public function activateUser(User $user): void
    {
        $wasPending = $user->status === UserStatusEnum::PENDING;
        $user->status = UserStatusEnum::ACTIVE;
        $this->userRepository->saveUser($user);
        if ($wasPending) {
            $this->notificationHandlerService->supplierApproved($user);
        }
    }

    public function deactivateUser(User $user): void
    {
        $user->status = UserStatusEnum::INACTIVE;
        $this->userRepository->saveUser($user);
    }

    public function getUsersWithOrderedItems(): Collection{
        return $this->userRepository->getUsersWithOrderedItems();
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
