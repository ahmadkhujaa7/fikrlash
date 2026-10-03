<?php

return [

    /*
    | Drayverlar: "log" (development — SMS matni logga yoziladi),
    | "eskiz" (Eskiz.uz), "array" (testlar uchun, xotirada saqlanadi).
    */
    'driver' => env('SMS_DRIVER', 'log'),

    'sender_name' => env('SMS_SENDER', '4546'),

    // OTP xabari matni. Eskiz'da shablon oldindan tasdiqlangan bo‘lishi shart.
    'otp_template' => env('SMS_OTP_TEMPLATE', 'Fikrlash.uz tasdiqlash kodi: :code. Kodni hech kimga bermang.'),

    'eskiz' => [
        'base_url' => env('ESKIZ_BASE_URL', 'https://notify.eskiz.uz/api'),
        'email' => env('ESKIZ_EMAIL'),
        'password' => env('ESKIZ_PASSWORD'),
        'timeout' => 10,
    ],
];
