<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

/**
 * API tokenlari "users_tokens" jadvalida saqlanadi.
 * Token faqat SHA-256 hash ko‘rinishida yoziladi — ochiq matn bir marta, yaratilganda qaytariladi.
 */
class PersonalAccessToken extends SanctumToken
{
    protected $table = 'users_tokens';
}
