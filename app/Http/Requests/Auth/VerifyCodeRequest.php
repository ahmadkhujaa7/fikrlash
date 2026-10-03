<?php

namespace App\Http\Requests\Auth;

use App\Support\ValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class VerifyCodeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['code' => preg_replace('/\D+/', '', (string) $this->input('code'))]);
    }

    public function rules(): array
    {
        return ['code' => ValidationRules::otpCode()];
    }
}
