<?php

namespace App\Http\Controllers;

use App\Dtos\ChangePasswordDto;
use App\Dtos\UpdateProfileDto;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\AddressResource;
use App\Http\Resources\ProfileResource;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProfileController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    public function show(Request $request): ProfileResource
    {
        return new ProfileResource($request->user());
    }

    public function update(UpdateProfileRequest $request): ProfileResource
    {
        $dto = UpdateProfileDto::fromRequest($request);
        $this->userService->updateProfile($request->user(), $dto);

        return new ProfileResource($request->user());
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $dto = ChangePasswordDto::fromRequest($request);
        $this->userService->changePassword($request->user(), $dto);

        return response()->json(null, 204);
    }

    public function addresses(Request $request): AnonymousResourceCollection
    {
        $user = $this->userService->getUserWithAddresses($request->user()->id);

        return AddressResource::collection($user->addresses);
    }
}
