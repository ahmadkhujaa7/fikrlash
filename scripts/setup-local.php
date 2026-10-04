<?php

/**
 * Lokal (Windows/macOS/Linux) tez sozlash: muhitni tekshiradi va .env ni SQLite bilan tayyorlaydi.
 * setup.bat / setup.sh tomonidan chaqiriladi. MySQL shart emas.
 */
$root = dirname(__DIR__);
$ok = true;

echo "\n== Fikrlash.uz: muhit tekshiruvi ==\n";

if (version_compare(PHP_VERSION, '8.3.0', '<')) {
    echo '  [X] PHP 8.3+ kerak, sizda: '.PHP_VERSION."\n";
    $ok = false;
} else {
    echo '  [OK] PHP '.PHP_VERSION."\n";
}

$required = ['pdo_sqlite', 'sqlite3', 'gd', 'intl', 'mbstring', 'fileinfo', 'openssl', 'zip', 'curl'];
foreach ($required as $ext) {
    if (extension_loaded($ext)) {
        echo "  [OK] {$ext}\n";
    } else {
        echo "  [X] {$ext} kengaytmasi yoqilmagan - php.ini da ';extension={$ext}' qatoridagi ';' ni olib tashlang\n";
        $ok = false;
    }
}

if (! extension_loaded('exif')) {
    echo "  [!] exif yoqilmagan (ixtiyoriy: telefon rasmlari burilishi uchun)\n";
}

if (! $ok) {
    echo "\nphp.ini manzili: ".(php_ini_loaded_file() ?: 'topilmadi')."\nTuzatib, qayta ishga tushiring.\n";
    exit(1);
}

$env = "{$root}/.env";
if (! file_exists($env)) {
    copy("{$root}/.env.example", $env);
    echo "\n  .env yaratildi\n";
}

$content = file_get_contents($env);
$set = function (string $key, string $value) use (&$content) {
    $line = "{$key}={$value}";
    $content = preg_match("/^#?\s*{$key}=.*/m", $content)
        ? preg_replace("/^#?\s*{$key}=.*/m", $line, $content, 1)
        : rtrim($content)."\n{$line}\n";
};

$set('APP_URL', 'http://localhost:8000');
$set('DB_CONNECTION', 'sqlite');
foreach (['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
    $content = preg_replace("/^{$key}=/m", "# {$key}=", $content);
}
// Lokal ko'rish uchun navbat darhol bajariladi (alohida worker shart emas).
$set('QUEUE_CONNECTION', 'sync');
$set('SMS_DRIVER', 'log');
$set('AI_PROVIDER', 'fake');

if (preg_match('/^APP_KEY=\s*$/m', $content)) {
    $set('APP_KEY', 'base64:'.base64_encode(random_bytes(32)));
    echo "  APP_KEY yaratildi\n";
}

file_put_contents($env, $content);

$db = "{$root}/database/database.sqlite";
if (! file_exists($db)) {
    touch($db);
    echo "  database/database.sqlite yaratildi\n";
}

echo "\n  Muhit tayyor.\n\n";
