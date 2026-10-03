<?php

namespace App\Http\Requests\Account;

use App\Support\PhoneNumber;
use App\Support\ValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class ChangePhoneRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => PhoneNumber::normalize($this->input('phone')) ?? $this->input('phone')]);
    }

    public function rules(): array
    {
        return ['phone' => ValidationRules::phone()];
    }
}
