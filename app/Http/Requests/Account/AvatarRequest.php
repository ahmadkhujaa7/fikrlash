<?php

namespace App\Http\Requests\Account;

use App\Support\ValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class AvatarRequest extends FormRequest
{
    public function rules(): array
    {
        return ['avatar' => array_merge(['required'], array_slice(ValidationRules::image(), 1))];
    }
}
