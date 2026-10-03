<?php

namespace App\Http\Requests\Account;

use App\Support\ValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ValidationRules::password(),
        ];
    }
}
