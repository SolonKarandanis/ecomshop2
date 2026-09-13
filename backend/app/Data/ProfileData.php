<?php

namespace App\Data;

use App\Enums\UserStatusEnum;
use App\Models\User;
use Spatie\LaravelData\Lazy;

class ProfileData extends BaseData
{
    public function __construct(
        public string $name,
        public string $email,
        public Lazy|UserStatusEnum|null $status,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            name: $user->name,
            email: $user->email,
            // Only an Admin's own profile view needs their User Status; a Buyer/Supplier
            // has no in-app use for it (ADR-0001 API Resources).
            status: Lazy::when(fn () => $user->isAdmin(), fn () => $user->status),
        );
    }
}
