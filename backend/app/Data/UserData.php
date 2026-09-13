<?php

namespace App\Data;

use App\Enums\UserStatusEnum;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Lazy;

class UserData extends BaseData
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public UserStatusEnum $status,
        public Lazy|Collection|null $roles,
        public ?Carbon $email_verified_at,
        public ?Carbon $created_at,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            status: $user->status,
            roles: Lazy::whenLoaded('roles', $user, fn () => $user->getRoleNames()),
            email_verified_at: $user->email_verified_at,
            created_at: $user->created_at,
        );
    }
}
