<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\Setting;
use App\Models\User;
use App\Services\Account\AccountService;
use App\Services\Auth\PasswordResetService;
use App\Services\Auth\RegistrationService;
use App\Services\Security\LoginTracker;
use App\Support\ApiResponse;
use App\Support\ValidationRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    private const TOKEN_TTL_DAYS = 90;

    public function __construct(private RegistrationService $registration) {}

    /** 1-qadam: ma'lumotlar + SMS. Javobda registration_token qaytadi. */
    public function register(RegisterRequest $request): JsonResponse
    {
        abort_unless(Setting::read('registration_open'), 403, 'Ro‘yxatdan o‘tish vaqtincha yopiq.');

        $token = $this->registration->start($request->validated(), $request->ip());

        return ApiResponse::success([
            'registration_token' => $token,
            'expires_in' => config('fikrlash.registration.pending_ttl_minutes') * 60,
        ], 'Tasdiqlash kodi SMS orqali yuborildi.', 202);
    }

    /** 2-qadam: OTP → akkaunt yaratiladi va API token beriladi. */
    public function verifyRegistration(Request $request): JsonResponse
    {
        $data = $request->validate([
            'registration_token' => ['required', 'string', 'max:100'],
            'code' => ValidationRules::otpCode(),
            'device_name' => ['nullable', 'string', 'max:60'],
        ]);

        $user = $this->registration->complete($data['registration_token'], $data['code']);
        app(LoginTracker::class)->record('register', $user, $request);

        return $this->tokenResponse($user, $data['device_name'] ?? 'api', 'Ro‘yxatdan o‘tdingiz.', 201);
    }

    public function resendRegistrationCode(Request $request): JsonResponse
    {
        $data = $request->validate(['registration_token' => ['required', 'string', 'max:100']]);
        $this->registration->resend($data['registration_token'], $request->ip());

        return ApiResponse::success(null, 'Yangi kod yuborildi.');
    }

    public function login(LoginRequest $request, AccountService $accounts): JsonResponse
    {
        $request->validate(['device_name' => ['nullable', 'string', 'max:60']]);
        $user = $request->resolveUser();
        $accounts->reactivateIfNeeded($user);
        $user->forceFill(['last_login_at' => now()])->save();
        app(LoginTracker::class)->record('login', $user, $request);

        return $this->tokenResponse($user, $request->input('device_name', 'api'), 'Xush kelibsiz!');
    }

    public function logout(Request $request): JsonResponse
    {
        app(LoginTracker::class)->record('logout', $request->user(), $request);
        $token = $request->user()->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return ApiResponse::success(null, 'Chiqildi.');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(new UserResource($request->user()));
    }

    public function forgotPassword(ForgotPasswordRequest $request, PasswordResetService $passwords): JsonResponse
    {
        $passwords->sendCode($request->validated('phone'), $request->ip());

        return ApiResponse::success(null, 'Agar raqam ro‘yxatdan o‘tgan bo‘lsa, unga kod yuborildi.');
    }

    public function resetPassword(ResetPasswordRequest $request, PasswordResetService $passwords): JsonResponse
    {
        $passwords->reset($request->validated('phone'), $request->validated('code'), $request->validated('password'));

        return ApiResponse::success(null, 'Parol yangilandi. Barcha qurilmalardan chiqildi.');
    }

    private function tokenResponse(User $user, string $device, string $message, int $status = 200): JsonResponse
    {
        $token = $user->createToken(mb_substr($device, 0, 60), ['*'], now()->addDays(self::TOKEN_TTL_DAYS));

        return ApiResponse::success([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => (new UserResource($user))->resolve(request()),
        ], $message, $status);
    }
}
