<?php

/*
|--------------------------------------------------------------------------
| Fikrlash.uz platforma sozlamalari
|--------------------------------------------------------------------------
| Barcha biznes-limitlar shu yerda. Kodda "magic number" ishlatilmaydi.
*/

return [

    'phone' => [
        'country_code' => '998',
        'national_length' => 9,
    ],

    'otp' => [
        'length' => 6,
        'ttl_minutes' => (int) env('OTP_TTL_MINUTES', 5),
        'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
        'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN', 60),
        'daily_limit_per_phone' => (int) env('OTP_DAILY_LIMIT_PHONE', 8),
        'daily_limit_per_ip' => (int) env('OTP_DAILY_LIMIT_IP', 30),
    ],

    'registration' => [
        // Ro‘yxatdan o‘tish ma'lumotlari OTP tasdiqlanguncha keshda shuncha vaqt saqlanadi.
        'pending_ttl_minutes' => 30,
    ],

    'username' => [
        'min' => 3,
        'max' => 30,
        'reserved' => [
            'admin', 'administrator', 'api', 'app', 'auth', 'login', 'logout', 'register', 'signup',
            'settings', 'search', 'notifications', 'saved', 'drafts', 'posts', 'post', 'feed', 'explore',
            'trending', 'help', 'support', 'about', 'terms', 'privacy', 'fikrlash', 'root', 'system',
            'moderator', 'null', 'undefined', 'storage', 'compose', 'password', 'me', 'user', 'users',
            'categories', 'category', 'tags', 'tag', 'up', 'www', 'mail', 'official',
        ],
    ],

    'password' => [
        'min' => 8,
    ],

    'posts' => [
        'min_length' => 1,
        'max_length' => 5000,
        'max_tags' => 5,
        'edit_window_hours' => null, // null = cheklovsiz tahrirlash
        'card_preview_length' => 420,
        'daily_limit' => (int) env('POSTS_DAILY_LIMIT', 50),
    ],

    'comments' => [
        'max_length' => 2000,
    ],

    'profile' => [
        'bio_max' => 300,
        'name_max' => 100,
    ],

    'media' => [
        'disk' => env('MEDIA_DISK', 'public'),
        'max_upload_kb' => 5120,
        'max_pixels' => 40_000_000, // decompression-bomb himoyasi
        'post_max_width' => 1600,
        'avatar_size' => 400,
        'webp_quality' => 82,
    ],

    'feed' => [
        'per_page' => 20,
        'candidate_days' => 14,
        'candidate_limit' => 600,
        'cache_minutes' => 5,
        'ranked_size' => 300,
        // Rule-based ranking vaznlari (RecommendationService).
        'weights' => [
            'like' => 1.0,
            'comment' => 2.0,
            'save' => 3.0,
            'view' => 0.05,
            'gravity' => 1.5,
            'interest' => 0.6,
            'followed_author' => 0.8,
            'verified_author' => 0.15, // tasdiqlangan muallifga kichik ustunlik
            'seen_penalty' => 0.25,
            'ai_quality' => 0.3,
        ],
    ],

    'views' => [
        'dedupe_minutes' => 30,
        // "redis" — ko‘rishlar Redis'da yig‘iladi va har daqiqada DB'ga yoziladi; "direct" — darhol yoziladi.
        'buffer' => env('VIEWS_BUFFER', 'direct'),
        'retention_days' => 90,
        'max_read_seconds' => 1800,
    ],

    /*
     * Tavsiya algoritmi: foydalanuvchi didi faqat xatti-harakatdan o‘rganiladi.
     * signals — har bir harakat qancha "javob" balli beradi (exposure — 1 ko‘rsatish).
     */
    'taste' => [
        'signals' => [
            'open' => 1.5,          // postni ochib o‘qidi
            'dwell' => 0.6,         // lentada 4+ soniya to‘xtab o‘qidi
            'dwell_long' => 1.2,    // lentada 12+ soniya
            'read' => 1.0,          // post sahifasida 20+ soniya
            'like' => 2.0,
            'comment' => 3.0,
            'save' => 3.0,
            'follow' => 5.0,        // muallifga obuna bo‘ldi (faqat muallif vazni)
        ],
        'not_interested' => 8.0,    // "Qiziq emas": shuncha "javobsiz ko‘rsatish" qo‘shiladi
        'prior_rate' => 0.25,       // o‘rtacha javob darajasi (Bayes smoothing markazi)
        'prior_strength' => 4.0,    // kam ma'lumotda profil o‘rtachaga yaqin turadi
        'lift_min' => 0.15,
        'lift_max' => 4.0,
        'feature_weights' => ['category' => 1.0, 'tag' => 0.7, 'author' => 0.9],
        'explore_every' => 6,       // har 6-o‘rinda yangi mavzudan post (didni kengaytirish)
        'weekly_decay' => 0.9,
    ],

    'moderation' => [
        // Shuncha turli foydalanuvchi report qilsa, post avtomatik tekshiruvga yuboriladi.
        'auto_review_reports' => 5,
    ],

    'notifications' => [
        'retention_days' => 120,
        'per_page' => 30,
    ],

    'accounts' => [
        // O‘chirilgan akkaunt shuncha kundan keyin butunlay o‘chiriladi.
        'deletion_grace_days' => 30,
    ],

    'security' => [
        'csp' => (bool) env('SECURITY_CSP', true),
        'hsts' => (bool) env('SECURITY_HSTS', false),
    ],

    'admin' => [
        'phone' => env('ADMIN_PHONE'),
        'password' => env('ADMIN_PASSWORD'),
    ],
];
