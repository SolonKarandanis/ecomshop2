<?php

namespace App\Http\Controllers\Auth;

use App\Data\UserData;
use App\Dtos\CreateUserDTO;
use App\Dtos\ResetPasswordDTO;
use App\Enums\RolesEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    public function register(RegisterUserRequest $request): Response
    {
        $dto = CreateUserDTO::fromRequest($request);

        $user = $dto->getRole() === RolesEnum::ROLE_SUPPLIER->value
            ? $this->userService->createSupplier($dto)
            : $this->userService->createBuyer($dto);

        Auth::login($user);
        $request->session()->regenerate();

        return UserData::from($user->load('roles'))
            ->wrap('data')
            ->toResponse($request)
            ->setStatusCode(201);
    }

    public function login(LoginRequest $request): UserData
    {
        $request->authenticate();

        $request->session()->regenerate();

        return UserData::from($request->user()->load('roles'))->wrap('data');
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(null, 204);
    }

    public function me(Request $request): UserData
    {
        return UserData::from($request->user()->load('roles'))->wrap('data');
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        return response()->json(
            ['message' => trans($status)],
            $status === Password::RESET_LINK_SENT ? 200 : 422,
        );
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $dto = ResetPasswordDTO::fromRequest($request);

        $status = $this->userService->resetPassword($dto);

        return response()->json(
            ['message' => trans($status)],
            $status === Password::PASSWORD_RESET ? 200 : 422,
        );
    }
}
