<?php

namespace App\Http\Requests\Account;

use App\Enums\Gender;
use App\Support\ValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => mb_strtolower(trim((string) $this->input('username', $this->user()->username), ' @')),
            'name' => trim((string) $this->input('name', $this->user()->name)),
            'email' => $this->input('email') ?: null,
            'bio' => $this->filled('bio') ? trim((string) $this->input('bio')) : null,
        ]);
    }

    public function rules(): array
    {
        $id = $this->user()->id;

        return [
            'name' => ValidationRules::name(),
            'username' => ValidationRules::username($id),
            'bio' => ['nullable', 'string', 'max:'.config('fikrlash.profile.bio_max')],
            'email' => ['nullable', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'birth_date' => ['nullable', 'date', 'before:-13 years', 'after:1900-01-01'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'Username faqat lotin harflari, raqamlar va _ belgisidan iborat bo‘lishi kerak.',
            'username.not_in' => 'Bu username band qilingan.',
            'birth_date.before' => 'Platformadan 13 yoshdan foydalanish mumkin.',
        ];
    }
}
