<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\Security\LoginTracker;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'max:128'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Telefon yoki username + parol. Xato xabari doim bir xil — qaysi qism noto‘g‘ri ekani aytilmaydi.
     *
     * @throws ValidationException
     */
    public function resolveUser(): User
    {
        $login = trim((string) $this->input('login'));
        $phone = PhoneNumber::normalize($login);

        $user = User::query()
            ->where($phone ? 'phone' : 'username', $phone ?? mb_strtolower(ltrim($login, '@')))
            ->first();

        if (! $user || ! Hash::check((string) $this->input('password'), $user->password)) {
            Log::channel('security')->notice('Muvaffaqiyatsiz login', ['ip' => $this->ip()]);
            app(LoginTracker::class)->record('failed', $user, $this, identifier: $login);

            throw ValidationException::withMessages(['login' => 'Telefon/username yoki parol noto‘g‘ri.']);
        }

        if (! $user->canSignIn()) {
            Log::channel('security')->notice('Bloklangan akkauntga kirish urinishi', ['user_id' => $user->id, 'ip' => $this->ip()]);
            app(LoginTracker::class)->record('blocked', $user, $this, identifier: $login);

            throw ValidationException::withMessages(['login' => 'Akkauntingiz bloklangan. Murojaat uchun: support@fikrlash.uz']);
        }

        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => $this->input('password')])->save();
        }

        return $user;
    }
}
