<?php

namespace App\Http\Controllers;

use App\Data\AddressData;
use App\Data\ProfileData;
use App\Dtos\ChangePasswordDto;
use App\Dtos\UpdateProfileDto;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\LaravelData\DataCollection;

class ProfileController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    public function show(Request $request): ProfileData
    {
        return ProfileData::from($request->user())->wrap('data');
    }

    public function update(UpdateProfileRequest $request): ProfileData
    {
        $dto = UpdateProfileDto::fromRequest($request);
        $this->userService->updateProfile($request->user(), $dto);

        return ProfileData::from($request->user())->wrap('data');
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $dto = ChangePasswordDto::fromRequest($request);
        $this->userService->changePassword($request->user(), $dto);

        return response()->json(null, 204);
    }

    public function addresses(Request $request): DataCollection
    {
        $user = $this->userService->getUserWithAddresses($request->user()->id);

        return AddressData::collect($user->addresses, DataCollection::class)->wrap('data');
    }
}
