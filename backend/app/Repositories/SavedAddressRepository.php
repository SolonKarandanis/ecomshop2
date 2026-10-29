<?php

namespace App\Repositories;

use App\Models\SavedAddress;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class SavedAddressRepository
{
    /**
     * @return Builder<SavedAddress>
     */
    public function modelQuery(): Builder
    {
        return SavedAddress::query();
    }

    /**
     * @return Collection<int, SavedAddress>
     */
    public function getForUser(int $userId): Collection
    {
        return $this->modelQuery()
            ->where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();
    }

    // Another User's address is a 404, not a 403, so ids can't be probed.
    public function findForUser(int $userId, int $addressId): SavedAddress
    {
        return $this->modelQuery()->where('user_id', $userId)->findOrFail($addressId);
    }

    public function existsForUser(int $userId): bool
    {
        return $this->modelQuery()->where('user_id', $userId)->exists();
    }

    public function findIdentical(int $userId, array $fields): ?SavedAddress
    {
        return $this->modelQuery()->where('user_id', $userId)->where($fields)->first();
    }

    public function getMostRecentlyUpdated(int $userId): ?SavedAddress
    {
        return $this->modelQuery()
            ->where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
    }

    public function create(array $attributes): SavedAddress
    {
        return $this->modelQuery()->create($attributes);
    }

    public function update(SavedAddress $address, array $attributes): SavedAddress
    {
        $address->update($attributes);

        return $address;
    }

    public function delete(SavedAddress $address): void
    {
        $address->delete();
    }

    public function clearDefault(int $userId): void
    {
        $this->modelQuery()->where('user_id', $userId)->where('is_default', true)->update(['is_default' => false]);
    }

    /**
     * Locks the User's row until the transaction ends, so two requests can't both
     * decide the default (e.g. both see an empty address book and both become it).
     */
    public function lockAddressBook(int $userId): void
    {
        User::query()->whereKey($userId)->lockForUpdate()->first();
    }
}
