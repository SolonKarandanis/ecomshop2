<?php

namespace App\Repositories;

use App\Dtos\CreateUserDTO;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class UserRepository
{

    public function modelQuery(): Builder| User{
        return User::query();
    }

    public function createUser(CreateUserDTO $dto):User{
        return $this->modelQuery()->create([
            'name' => $dto->getName(),
            'email' => $dto->getEmail(),
            'password' => Hash::make($dto->getPassword()),
        ]);
    }

    public function saveUser(User $user): User{
        $user->save();
        return $user;
    }

    public function getUserWithAddresses(int $userId): ?User
    {
        return $this->modelQuery()
            ->with(['addresses' => fn($q) => $q->with('order')->latest()->limit(5)])
            ->find($userId);
    }

    public function getUsersWithOrderedItems(): Collection{
        return $this->modelQuery()
            ->select('users.name')
            ->selectRaw('GROUP_CONCAT(products.name SEPARATOR ",") as items')
            ->join('orders', 'users.id', '=', 'orders.user_id')
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->groupBy('users.name')
            ->get();
    }
}
