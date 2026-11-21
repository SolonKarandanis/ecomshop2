<?php

namespace App\Repositories;

use App\Dtos\CreateUserDTO;
use App\Enums\RolesEnum;
use App\Enums\UserStatusEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class UserRepository
{
    public function modelQuery(): Builder|User
    {
        return User::query();
    }

    public function createUser(CreateUserDTO $dto, UserStatusEnum $status = UserStatusEnum::ACTIVE): User
    {
        return $this->modelQuery()->create([
            'name' => $dto->getName(),
            'email' => $dto->getEmail(),
            'password' => Hash::make($dto->getPassword()),
            'status' => $status,
        ]);
    }

    public function saveUser(User $user): User
    {
        $user->save();

        return $user;
    }

    public function getAdmins(): Collection
    {
        // whereHas rather than Spatie's role() scope, which throws if the role isn't seeded.
        return $this->modelQuery()
            ->whereHas('roles', fn ($q) => $q->where('name', RolesEnum::ROLE_ADMIN->value))
            ->get();
    }
}
