<?php

namespace App\Http\Requests\Auth;

use App\Support\PhoneNumber;
use App\Support\ValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'username' => mb_strtolower(trim((string) $this->input('username'), " \t\n\r\0\x0B@")),
            'phone' => PhoneNumber::normalize($this->input('phone')) ?? $this->input('phone'),
        ]);

        // Veb-formada parol bitta maydonda ("ko‘rsatish" tugmasi bilan) — takrorlash maydoni bo‘lmasa, o‘zi bilan tenglashtiriladi.
        if (! $this->has('password_confirmation')) {
            $this->merge(['password_confirmation' => $this->input('password')]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ValidationRules::name(),
            'username' => ValidationRules::username(),
            'phone' => ValidationRules::phone(),
            'password' => ValidationRules::password(),
            'terms' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'Username faqat lotin harflari, raqamlar va _ belgisidan iborat bo‘lishi va kamida bitta harf bo‘lishi kerak.',
            'username.not_in' => 'Bu username band qilingan. Boshqasini tanlang.',
            'terms.accepted' => 'Foydalanish shartlariga rozilik bildiring.',
        ];
    }
}
