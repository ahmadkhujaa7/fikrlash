<?php

namespace App\Support;

use App\Enums\PostVisibility;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Bir nechta Form Request'da takrorlanadigan qoidalar (web va API uchun bir xil). */
final class ValidationRules
{
    public static function name(): array
    {
        // Tasdiqlangan belgisini ism ichida soxtalashtirib bo‘lmaydi.
        return ['required', 'string', 'min:2', 'max:'.config('fikrlash.profile.name_max'), 'not_regex:/[\x{2713}\x{2714}\x{2611}\x{2705}\x{1F5F8}\x{1F5F9}\x{2714}\x{FE0F}]/u'];
    }

    public static function username(?int $ignoreUserId = null): array
    {
        return [
            'required', 'string',
            'min:'.config('fikrlash.username.min'), 'max:'.config('fikrlash.username.max'),
            // faqat lotin harflari, raqam va _ ; kamida bitta harf (faqat raqamdan iborat bo‘lmasin — ID bilan chalkashmaydi)
            'regex:/^(?=.*[a-z])[a-z0-9_]+$/',
            Rule::notIn(config('fikrlash.username.reserved')),
            Rule::unique('users', 'username')->ignore($ignoreUserId),
        ];
    }

    /** Telefon prepareForValidation'da normallashtiriladi; noto‘g‘ri bo‘lsa — null bo‘ladi. */
    public static function phone(bool $unique = true): array
    {
        $rules = ['required', 'string', function (string $attribute, mixed $value, Closure $fail) {
            if (! PhoneNumber::isValid($value)) {
                $fail('Telefon raqam noto‘g‘ri. Namuna: +998 90 123 45 67');
            }
        }];

        if ($unique) {
            $rules[] = Rule::unique('users', 'phone');
        }

        return $rules;
    }

    public static function password(): array
    {
        return ['required', 'string', 'confirmed', Password::defaults(), 'max:128'];
    }

    public static function otpCode(): array
    {
        return ['required', 'string', 'digits:'.config('fikrlash.otp.length')];
    }

    public static function postContent(): array
    {
        return ['required', 'string', 'min:'.config('fikrlash.posts.min_length'), 'max:'.config('fikrlash.posts.max_length')];
    }

    public static function category(): array
    {
        return ['nullable', 'integer', Rule::exists('categories', 'id')->where('is_active', true)];
    }

    public static function visibility(): array
    {
        return ['nullable', Rule::enum(PostVisibility::class)];
    }

    public static function image(): array
    {
        return [
            'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp',
            'max:'.config('fikrlash.media.max_upload_kb'), 'dimensions:min_width=50,min_height=50,max_width=10000,max_height=10000',
        ];
    }

    public static function tags(): array
    {
        return ['nullable', 'array', 'max:'.config('fikrlash.posts.max_tags')];
    }

    public static function tagItem(): array
    {
        return ['string', 'max:50', 'regex:/^#?[\p{L}\p{N}_‘’ʻʼ\']+$/u'];
    }
}
