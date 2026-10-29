<?php

namespace App\Services;

use App\Dtos\SaveAddressDto;
use App\Models\SavedAddress;
use App\Models\User;
use App\Repositories\SavedAddressRepository;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A User's address book. Whenever the User has any saved address, exactly one is the default.
 */
#[Singleton]
class SavedAddressService
{
    public function __construct(
        private readonly SavedAddressRepository $savedAddressRepository,
    ) {}

    /**
     * @return Collection<int, SavedAddress>
     */
    public function getAddresses(User $user): Collection
    {
        return $this->savedAddressRepository->getForUser($user->id);
    }

    public function createAddress(User $user, SaveAddressDto $dto): SavedAddress
    {
        return DB::transaction(function () use ($user, $dto) {
            $this->savedAddressRepository->lockAddressBook($user->id);

            return $this->createLocked($user->id, $dto);
        });
    }

    public function updateAddress(User $user, int $addressId, SaveAddressDto $dto): SavedAddress
    {
        $address = $this->savedAddressRepository->findForUser($user->id, $addressId);

        return $this->savedAddressRepository->update($address, $dto->getFields());
    }

    public function deleteAddress(User $user, int $addressId): void
    {
        DB::transaction(function () use ($user, $addressId) {
            $this->savedAddressRepository->lockAddressBook($user->id);
            $address = $this->savedAddressRepository->findForUser($user->id, $addressId);
            $wasDefault = $address->is_default;
            $this->savedAddressRepository->delete($address);

            if ($wasDefault) {
                $next = $this->savedAddressRepository->getMostRecentlyUpdated($user->id);
                if ($next !== null) {
                    $this->savedAddressRepository->update($next, ['is_default' => true]);
                }
            }
        });
    }

    public function makeDefault(User $user, int $addressId): SavedAddress
    {
        return DB::transaction(function () use ($user, $addressId) {
            $this->savedAddressRepository->lockAddressBook($user->id);
            $address = $this->savedAddressRepository->findForUser($user->id, $addressId);
            $this->savedAddressRepository->clearDefault($user->id);

            return $this->savedAddressRepository->update($address, ['is_default' => true]);
        });
    }

    // Checkout's "Save this address": skipped when the User already has an identical address.
    public function saveFromCheckout(int $userId, SaveAddressDto $dto): void
    {
        DB::transaction(function () use ($userId, $dto) {
            $this->savedAddressRepository->lockAddressBook($userId);

            if ($this->savedAddressRepository->findIdentical($userId, $dto->getFields()) === null) {
                $this->createLocked($userId, $dto);
            }
        });
    }

    // The caller holds the address-book lock. A User's first address becomes the default.
    private function createLocked(int $userId, SaveAddressDto $dto): SavedAddress
    {
        return $this->savedAddressRepository->create([
            ...$dto->getFields(),
            'user_id' => $userId,
            'is_default' => ! $this->savedAddressRepository->existsForUser($userId),
        ]);
    }
}
