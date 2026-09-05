<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::query()->create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'role' => UserRole::Customer,
        ]);

        $token = $user->createToken('auth')->plainTextToken;

        return ApiResponse::success([
            'user' => UserResource::make($user)->resolve(),
            'token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if ($user === null || ! Hash::check($request->validated('password'), $user->password)) {
            return ApiResponse::error('Invalid credentials.', 401);
        }

        if ($user->is_active === false) {
            return ApiResponse::error('Account deactivated.', 403);
        }

        $token = $user->createToken('auth')->plainTextToken;

        return ApiResponse::success([
            'user' => UserResource::make($user)->resolve(),
            'token' => $token,
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $email = $request->validated('email');
        $user = User::query()->where('email', $email)->first();

        if ($user !== null && $user->is_active) {
            Password::sendResetLink(['email' => $email]);
        }

        return ApiResponse::success([
            'status' => 'accepted',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $payload = $request->safe()->only(['email', 'password', 'password_confirmation', 'token']);
        $user = User::query()->where('email', $payload['email'])->first();

        if ($user === null || $user->is_active === false) {
            return ApiResponse::error('Unable to reset password.', 422);
        }

        $status = Password::reset($payload, function (User $resetUser, string $password): void {
            $resetUser->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            $resetUser->tokens()->delete();

            event(new PasswordReset($resetUser));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return ApiResponse::error('Unable to reset password.', 422);
        }

        return ApiResponse::success([
            'status' => 'reset',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $plainToken = $request->bearerToken();

        if (is_string($plainToken) && $plainToken !== '') {
            PersonalAccessToken::findToken($plainToken)?->delete();
        }

        return ApiResponse::success(null);
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(
            UserResource::make($request->user())->resolve()
        );
    }
}
