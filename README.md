# Fikrlash.uz

O‘zbek tilidagi fikr almashish platformasi: foydalanuvchilar fikr, g‘oya va savollarini yozadi, boshqalar o‘qiydi, muhokama qiladi. Postlar fon rejimida AI orqali tahlil qilinadi, Foydalanuvchi faqat yozadi — mavzuni AI aniqlaydi, lenta esa har bir foydalanuvchining xatti-harakatidan o‘rganib, unga mos postlarni ko‘rsatadi.

**Stack:** PHP 8.3+ · Laravel 12 · MySQL 8 · Redis · Blade + Alpine.js + Tailwind CSS 4 · Filament 5 (admin) · Laravel Sanctum (API)

---

## Mundarija

1. [Tez boshlash (Windows / macOS / Linux)](#1-tez-boshlash)
2. [Arxitektura](#2-arxitektura)
3. [Muhit (.env)](#3-muhit-env)
4. [Database](#4-database)
5. [Autentifikatsiya va OTP](#5-autentifikatsiya-va-otp)
6. [API](#6-api)
7. [AI tizimi](#7-ai-tizimi)
8. [Queue](#8-queue)
9. [Scheduler (cron)](#9-scheduler-cron)
10. [Testlar](#10-testlar)
11. [Production deploy](#11-production-deploy)
12. [Xavfsizlik](#12-xavfsizlik)
13. [Muammolarni hal qilish](#13-muammolarni-hal-qilish)
14. [Yo‘l xaritasi](#14-yol-xaritasi)

---

## 1. Tez boshlash

### Windows: ikki marta bosish bilan

1. PHP 8.3+, Composer va Node.js o‘rnatilgan bo‘lsin — eng osoni [Laravel Herd](https://herd.laravel.com) yoki [Laragon](https://laragon.org).
2. **`setup.bat`** ni ikki marta bosing — muhitni tekshiradi, SQLite bazani yaratadi, demo ma'lumotlarni yuklaydi, CSS/JS'ni yig‘adi (MySQL shart emas).
3. **`start.bat`** ni ikki marta bosing — brauzerda http://localhost:8000 ochiladi.

macOS/Linux: `./setup.sh`, so‘ng `php artisan serve`.

### Qo‘lda o‘rnatish (MySQL bilan)

**Kerak:** PHP 8.3+ (`gd`, `intl`, `mbstring`, `pdo_mysql`, `exif`, `fileinfo`, `zip` kengaytmalari), Composer 2, Node.js 20+, MySQL 8 (yoki sinov uchun SQLite).
Windows'da eng oson yo‘l — [Laragon](https://laragon.org) yoki [Laravel Herd](https://herd.laravel.com): PHP, MySQL va Composer bitta o‘rnatishda keladi.

```bash
git clone https://github.com/USERNAME/fikrlash.git
cd fikrlash

composer install
npm install
npm run build

cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate

# .env da DB_DATABASE=fikrlash, DB_USERNAME, DB_PASSWORD ni sozlang va bazani yarating:
#   mysql -u root -e "CREATE DATABASE fikrlash CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan migrate --seed
php artisan storage:link

composer dev                  # server + queue + vite bir vaqtda
```

Sayt: http://localhost:8000 · Admin: http://localhost:8000/admin

**Lokal admin:** `+998900000001` / `admin12345` (yoki `.env` dagi `ADMIN_PHONE`, `ADMIN_PASSWORD`).
Seeder lokal muhitda demo ma'lumot yaratadi: 40 foydalanuvchi (parol: `password`), 150 post, izohlar, like'lar, shikoyatlar.

**SMS kodi qayerda?** Lokal muhitda `SMS_DRIVER=log` — kod `storage/logs/laravel.log` fayliga yoziladi:
```
[SMS] +998901234567: Fikrlash.uz tasdiqlash kodi: 482913. Kodni hech kimga bermang.
```

> SQLite bilan tez sinash: `.env` da `DB_CONNECTION=sqlite` qilib, boshqa `DB_*` qatorlarini o‘chiring. Qidiruv LIKE orqali ishlaydi (MySQL'da FULLTEXT).

---

## 2. Arxitektura

Monolit Laravel ilova. Controllerlar yupqa: so‘rov → validatsiya (Form Request) → avtorizatsiya (Policy) → servis → javob (Blade yoki API Resource).

```
app/
├── Console/Commands/     scheduler buyruqlari (views:flush, posts:refresh-scores, ...)
├── Contracts/            SmsProvider, AiProvider interfeyslari
├── Enums/                UserStatus, PostStatus, ReportReason ... (o‘zbekcha label bilan)
├── Events/ Listeners/    PostCreated → AI + mention, PostLiked → bildirishnoma, ...
├── Filament/             admin panel: resurslar, dashboard, AI analitika, sozlamalar
├── Http/
│   ├── Controllers/      web (Blade) va Api/V1 (JSON)
│   ├── Middleware/       SecurityHeaders (CSP), EnsureUserIsActive
│   ├── Requests/         Form Request validatsiya (web va API uchun umumiy)
│   └── Resources/        API javob formatlari (model to‘g‘ridan-to‘g‘ri JSON qilinmaydi)
├── Jobs/                 AnalyzePostJob, SendOtpJob
├── Models/
├── Policies/             PostPolicy, CommentPolicy, UserPolicy
├── Services/
│   ├── Auth/             OtpService, RegistrationService, PasswordResetService
│   ├── Ai/               AiManager, ClaudeProvider, OpenAiProvider, FakeProvider
│   ├── Feed/             FeedService, RecommendationService, SearchService, ViewRecorder
│   ├── Moderation/       ModerationService (har bir qaror audit log'ga yoziladi)
│   ├── Posts/ Social/ Account/ Media/ Sms/
└── Support/              ContentFormatter (XSS-xavfsiz), PhoneNumber, TextNormalizer, ApiResponse
config/fikrlash.php       barcha biznes-limitlar (post uzunligi, OTP, feed vaznlari...)
config/ai.php, sms.php    provayder sozlamalari
```

**Asosiy qarorlar:**

| Qaror | Sabab |
|---|---|
| Ro‘yxatdan o‘tishda akkaunt OTP tasdiqlangandan **keyin** yaratiladi | Begona telefon raqamini "band qilib qo‘yish" mumkin emas |
| API tokenlar — Sanctum, `users_tokens` jadvalida | Token SHA-256 hash ko‘rinishida saqlanadi; muddat, oxirgi foydalanish, bekor qilish tayyor |
| Like/save/follow — `unique` indeks + `insertOrIgnore` + atomik `increment` | Race condition'da hisoblagich buzilmaydi; tungi `fikrlash:reconcile-counters` qo‘shimcha kafolat |
| Ko‘rishlar — 30 daqiqalik dedup, muallif hisoblanmaydi, ixtiyoriy Redis buffer | Haqiqiy statistika, har refreshda DB'ga yozilmaydi |
| Lenta — yashirin "did profili" (`TasteService`): kategoriya, teg va muallif bo‘yicha *ko‘rsatildi / javob berdi* nisbati, Bayes smoothing | Foydalanuvchi hech narsa sozlamaydi; e’tiborsiz qolgan mavzular o‘zi kamayadi, "Qiziq emas" darhol ta’sir qiladi, har 6-o‘rinda yangi mavzu (kashfiyot) |
| Qidiruv — `search_text` ustuni (apostroflar olib tashlangan) + FULLTEXT | "o‘qish", "oʻqish", "o'qish" bir-birini topadi |
| Rasmlar GD orqali WebP'ga qayta encode qilinadi | EXIF/GPS o‘chadi, zararli fayllar zararsizlanadi |
| AI faqat signal beradi | Xavfli post moderator tekshiruviga tushadi, foydalanuvchi avtomatik jazolanmaydi |

---

## 3. Muhit (.env)

| O‘zgaruvchi | Lokal | Production |
|---|---|---|
| `APP_ENV` / `APP_DEBUG` | `local` / `true` | `production` / `false` |
| `DB_CONNECTION` | `mysql` yoki `sqlite` | `mysql` |
| `CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER` | `database` | `redis`, `redis`, `redis` yoki `database` |
| `SMS_DRIVER` | `log` | `eskiz` (+ `ESKIZ_EMAIL`, `ESKIZ_PASSWORD`) |
| `AI_PROVIDER` | `fake` | `claude` (+ `ANTHROPIC_API_KEY`) yoki `openai` |
| `MEDIA_DISK` | `public` | `public` yoki `s3` |
| `VIEWS_BUFFER` | `direct` | `redis` |
| `SECURITY_HSTS` | `false` | `true` (SSL sozlangandan keyin) |
| `SESSION_SECURE_COOKIE` | `false` | `true` |
| `TRUSTED_PROXIES` | `127.0.0.1` | load balancer IP'lari |

`.env` hech qachon git'ga qo‘shilmaydi (`.gitignore`da). Barcha limitlar `config/fikrlash.php` da.

**S3-compatible storage**ga o‘tish: `composer require league/flysystem-aws-s3-v3`, `.env` da `MEDIA_DISK=s3` va `AWS_*` qiymatlari. Kod o‘zgarmaydi.

---

## 4. Database

Migratsiyalar: `database/migrations/`. Asosiy jadvallar:

- `users` — telefon E.164 (`+998…`) formatida, `status` (active/suspended/blocked/deactivated), `role` (user/admin), soft delete
- `phone_verifications` — OTP (faqat HMAC hash), urinishlar, muddati
- `users_tokens` — API tokenlar (hash)
- `posts` — status (published/draft/hidden/pending_moderation), visibility (public/followers), hisoblagichlar, `score`, AI maydonlari, soft delete
- `comments` — bir darajali javoblar (`parent_id` + `reply_to_user_id`)
- `post_likes`, `comment_likes`, `saved_posts`, `follows` — `unique` cheklovlar bilan
- `categories` — ichki mavzular (faqat tizim va admin uchun; foydalanuvchiga ko‘rsatilmaydi), `tags`, `post_tag`
- `user_affinities` — foydalanuvchi didi: `kind` (category/tag/author), `score` (javoblar), `exposures` (ko‘rsatishlar); haftalik so‘nadi
- `user_interests` — eski kategoriya vaznlari (yangi jadvalga ko‘chirilgan, endi ishlatilmaydi)
- `post_views` — kim nimani ko‘rgani, o‘qish vaqti va "Qiziq emas" belgisi (90 kun saqlanadi)
- `post_ai_analyses` — AI tahlil tarixi, tokenlar, xatolar
- `login_events` — kirishlar tarixi (admin kuzatuvi, 180 kun)
- `users.admin_note` — adminlarning ichki izohi (shifrlangan)
- `notifications`, `reports`, `audit_logs`, `settings`

O‘zgartirishlar faqat migratsiya orqali: `php artisan make:migration ...` → `php artisan migrate`.

---

## 5. Autentifikatsiya va OTP

- **Ro‘yxatdan o‘tish:** ism, username, telefon, parol → SMS kod → tasdiqlangach akkaunt yaratiladi.
- **Kirish:** telefon *yoki* username + parol. Xato xabari doim bir xil (qaysi qismi noto‘g‘ri ekani aytilmaydi).
- **Parolni tiklash:** telefon → SMS kod → yangi parol. Barcha sessiya va API tokenlar bekor qilinadi.
- **OTP himoyasi:** 6 xonali, 5 daqiqa amal qiladi, 5 urinish (atomik hisob), 60s qayta yuborish cooldown, telefon bo‘yicha kuniga 8 ta, IP bo‘yicha 30 ta SMS, bir kod bir marta.
- **Telefonni almashtirish:** sozlamalarda, yangi raqamga OTP orqali.

### Eskiz.uz ulash
1. eskiz.uz da akkaunt oching, "4546" (yoki o‘z) jo‘natuvchi nomini va SMS shablonini tasdiqlating. Shablon `.env` dagi `SMS_OTP_TEMPLATE` bilan **aynan** mos bo‘lishi kerak.
2. `.env`: `SMS_DRIVER=eskiz`, `ESKIZ_EMAIL=...`, `ESKIZ_PASSWORD=...`.
3. Boshqa provayder qo‘shish: `App\Contracts\SmsProvider` ni implement qiling va `AppServiceProvider` dagi `match` ga qo‘shing.

---

## 6. API

- Manzil: `/api/v1/`, hujjat: **`/docs/api`** sahifasi va `/docs/openapi.json` (OpenAPI 3.1, Postman/Swagger'ga import qilinadi).
- Autentifikatsiya: `Authorization: Bearer <token>` (`POST /api/v1/auth/login`). Foydalanuvchi sozlamalarida token bo‘limi yo‘q; tokenlarni admin boshqaradi (Admin → API tokenlar). Hujjatlar (`/docs/api`) faqat adminlarga ochiq.
- Javob formati:
  ```json
  {"success": true, "message": "OK", "data": {...}, "meta": {"pagination": {"next_cursor": "...", "has_more": true}}}
  {"success": false, "message": "...", "errors": {"content": ["Matn to‘ldirilishi shart."]}}
  ```
- O‘z saytimiz ham shu API'dan foydalanadi (like, save, follow, shikoyat) — sessiya cookie + CSRF orqali (Sanctum SPA).

```bash
TOKEN=$(curl -s -X POST localhost:8000/api/v1/auth/login -H "Accept: application/json" \
  -d login=admin -d password=admin12345 -d device_name=cli | php -r 'echo json_decode(stream_get_contents(STDIN))->data->token;')
curl -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" localhost:8000/api/v1/feed?tab=latest
```

---

## 7. AI tizimi

```
Post yaratildi → PostCreated event → AnalyzePostJob (queue: ai) → AiProvider → validatsiya → post_ai_analyses
                                                                              ↓
                                                    post.ai_* maydonlari, avto-kategoriya, moderatsiya signali
```

- **Provayderlar:** `claude` (tool-use orqali qat'iy JSON), `openai` (json_object), `fake` (kalitsiz, kalit so‘z qoidalari), `null`. Yangi provayder: `App\Contracts\AiProvider` + `AiManager` ga bitta qator.
- **Tahlil:** mavzu, kategoriya, kayfiyat, sifat, toksiklik, spam, ta'limiy qiymat, engagement, qisqacha mazmun, kalit so‘zlar.
- **Ko‘rinish:** AI natijalari faqat admin panelda ko‘rinadi. Foydalanuvchi interfeysi, API va SEO'da AI ma'lumoti yo‘q; AI kalit so‘zlari teg bo‘lmaydi. Tizim ichida faqat moderatsiya signali, lenta tartibi (sifat bahosi, mavzu) uchun ishlatiladi.
- **Xavfsizlik:** post matni prompt'da `<post>` ichida "ishonchsiz ma'lumot" sifatida beriladi; AI javobi har doim validatsiya qilinadi (tur, 0–100 oraliq, ruxsat etilgan kategoriyalar). AI'ga faqat post matni yuboriladi — telefon, ism yuborilmaydi.
- **Xarajat nazorati:** bir postga bir vaqtda bitta job; bir xil matn qayta tahlil qilinmaydi (content hash, boshqa postdagi nusxa — keshdan); daqiqalik (`AI_REQUESTS_PER_MINUTE`) va kunlik (`AI_DAILY_LIMIT`) limit; 3 marta qayta urinish. Admin → **AI analitika** sahifasida tokenlar, taxminiy narx, xatolar.
- **Moderatsiya:** toksiklik ≥ 70 yoki spam ≥ 80 bo‘lsa post `pending_moderation` holatiga o‘tadi va admin tekshiruvini kutadi (sozlamalarda o‘chirish mumkin). Muallifga bildirishnoma boradi.
- AI ishlamasa ham post chop etiladi; tahlil qilinmaganlar `ai:retry-pending` orqali har soatda qayta navbatga qo‘yiladi.

---

## 8. Queue

| Navbat | Ishlar |
|---|---|
| `high` | OTP SMS (`SendOtpJob`, payload shifrlangan) |
| `default` | bildirishnomalar, mention'lar (queued listenerlar) |
| `ai` | `AnalyzePostJob` |

```bash
php artisan queue:work --queue=high,default      # asosiy
php artisan queue:work --queue=ai --timeout=120  # AI (alohida)
php artisan queue:failed                         # muvaffaqiyatsiz joblar
php artisan queue:retry all
```

Production'da Supervisor: `deploy/supervisor.conf`.

---

## 9. Scheduler (cron)

Serverda bitta cron yozuvi (`deploy/crontab`):
```
* * * * * cd /var/www/fikrlash/current && php artisan schedule:run >> /dev/null 2>&1
```

| Buyruq | Qachon | Vazifa |
|---|---|---|
| `views:flush` | har daqiqa | Redis'dagi ko‘rishlarni DB'ga yozish |
| `posts:refresh-scores` | 10 daqiqa | Trending/tavsiya ballari |
| `users:lift-suspensions` | 10 daqiqa | Muddati tugagan cheklovlar |
| `ai:retry-pending` | har soat | Tahlil qilinmagan postlar |
| `fikrlash:reconcile-counters` | 03:10 | Hisoblagichlarni qayta hisoblash |
| `fikrlash:prune` | 03:30 | Eski OTP, ko‘rishlar, o‘qilgan bildirishnomalar, muddati o‘tgan tokenlar |
| `accounts:purge-deleted` | 04:00 | 30 kundan oshgan o‘chirilgan akkauntlar |
| `interests:decay` | haftalik | Did profili so‘nadi (eski qiziqishlar unutiladi) |

Qo‘lda: `php artisan schedule:list`. Admin yaratish: `php artisan fikrlash:create-admin +998901234567`.

---

## 10. Testlar

```bash
php artisan test          # 112 test: auth, OTP, postlar, izohlar, like/follow, feed, qidiruv,
                          # bildirishnomalar, shikoyatlar, admin, API, AI, ko‘rishlar, akkaunt, xavfsizlik
vendor/bin/pint           # kod uslubi (PSR-12 / Laravel)
```

Testlar SQLite xotirada ishlaydi (SMS — `array`, AI — `fake` drayver). GitHub Actions (`.github/workflows/ci.yml`) har push'da testlarni **SQLite va MySQL 8** da ishga tushiradi.

---

## 11. Production deploy

**Server:** Ubuntu 24.04, Nginx, PHP 8.3-FPM, MySQL 8, Redis, Supervisor, Certbot.

> ⚖️ O‘zbekistonning "Shaxsga doir ma'lumotlar to‘g‘risida"gi qonuni fuqarolarning shaxsiy ma'lumotlarini O‘zbekiston hududidagi serverlarda saqlashni talab qiladi. Server va storage joylashuvini yurist bilan tasdiqlang.

```bash
# 1. Paketlar
sudo apt install nginx mysql-server redis-server supervisor certbot python3-certbot-nginx \
  php8.3-fpm php8.3-{mysql,redis,gd,intl,mbstring,xml,curl,zip,bcmath,exif}
# Composer va Node.js 22 ni rasmiy saytlaridan o‘rnating.

# 2. Baza
sudo mysql -e "CREATE DATABASE fikrlash CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'fikrlash'@'localhost' IDENTIFIED BY 'KUCHLI_PAROL';
  GRANT ALL ON fikrlash.* TO 'fikrlash'@'localhost';"

# 3. Papkalar
sudo mkdir -p /var/www/fikrlash/{releases,shared/storage}
sudo chown -R www-data:www-data /var/www/fikrlash
# shared/.env ni yarating (.env.example asosida, APP_ENV=production, APP_DEBUG=false ...)
# shared/storage ichida: app/public, framework/{cache,sessions,views}, logs

# 4. Deploy (har safar)
REPO=git@github.com:USERNAME/fikrlash.git ./deploy/deploy.sh

# 5. Birinchi marta
php artisan fikrlash:create-admin +998XXXXXXXXX
php artisan db:seed --class=CategorySeeder --force

# 6. Nginx + SSL
sudo cp deploy/nginx.conf /etc/nginx/sites-available/fikrlash.uz
sudo ln -s /etc/nginx/sites-available/fikrlash.uz /etc/nginx/sites-enabled/
sudo certbot --nginx -d fikrlash.uz -d www.fikrlash.uz
sudo nginx -t && sudo systemctl reload nginx

# 7. Queue va cron
sudo cp deploy/supervisor.conf /etc/supervisor/conf.d/fikrlash.conf
sudo supervisorctl reread && sudo supervisorctl update
sudo crontab -u www-data deploy/crontab
```

**Production .env tekshiruv ro‘yxati:** `APP_DEBUG=false`, `APP_URL=https://fikrlash.uz`, `SESSION_SECURE_COOKIE=true`, `SECURITY_HSTS=true`, `SMS_DRIVER=eskiz`, `AI_PROVIDER=claude`, `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `VIEWS_BUFFER=redis`, `SANCTUM_STATEFUL_DOMAINS=fikrlash.uz`, `LOG_LEVEL=warning`.

**Monitoring:** `storage/logs/laravel-*.log` (xatolar), `security-*.log` (login, OTP, bloklash), `ai-*.log` (AI xatolari), `queue:failed`. Kelajakda: Laravel Horizon (Redis queue dashboard), Sentry/Flare (xatolar).

**Backup:** har kuni `mysqldump --single-transaction fikrlash | gzip` + `shared/storage/app/public` ni boshqa joyga nusxalash.

---

## 12. Xavfsizlik

- **Parollar** bcrypt bilan hash; **OTP** — HMAC-SHA256 (app key bilan); **API tokenlar** — SHA-256.
- **XSS:** foydalanuvchi matni DB'da xom saqlanadi, chiqishda har bo‘lak escape qilinadi (`ContentFormatter`); faqat `http(s)` havolalar, `rel="nofollow ugc noopener"`.
- **CSP** (nonce asosida), `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, HSTS (`SecurityHeaders` middleware).
- **CSRF** barcha formalarda; **SQL injection** — Eloquent/binding, `LIKE` belgilari escape qilinadi.
- **Rate limiting:** login, register, OTP yuborish/tekshirish, parol tiklash, post, izoh, like, follow, qidiruv, shikoyat, yuklash, API, AI (`AppServiceProvider::configureRateLimiting`).
- **Fayllar:** MIME + kengaytma + o‘lcham + piksel soni tekshiruvi, GD orqali qayta encode, UUID nom.
- **Avtorizatsiya:** Policy'lar (post, izoh, follow), admin panel faqat `role=admin` va faol foydalanuvchilar uchun.
- **Audit:** barcha admin/moderatsiya amallari `audit_logs` da (kim, nima, oldingi/yangi qiymat, IP).
- **Privacy:** jins/tug‘ilgan sana ixtiyoriy va ochiq ko‘rsatilmaydi; telefon faqat egasiga (maskalangan); ma'lumot eksporti va akkaunt o‘chirish mavjud.
- Production'da stack trace, SQL va yo‘llar foydalanuvchiga ko‘rsatilmaydi (`APP_DEBUG=false`, API uchun umumiy xato javobi).

**Tavsiya (keyingi qadam):** adminlar uchun 2FA, `@alpinejs/csp` build (CSP'dan `unsafe-eval` ni olib tashlash), WAF/Cloudflare.

---

## 13. Muammolarni hal qilish

| Belgi | Yechim |
|---|---|
| `Vite manifest not found` | `npm install && npm run build` (yoki dev uchun `npm run dev`) |
| Rasmlar ko‘rinmaydi | `php artisan storage:link`, `APP_URL` to‘g‘ri ekanini tekshiring |
| SMS kelmayapti (lokal) | Kod `storage/logs/laravel.log` da; queue ishlayotganini tekshiring (`composer dev`) |
| AI tahlil bo‘lmayapti | `php artisan queue:work --queue=ai`, `storage/logs/ai-*.log`, admin → AI analitika |
| Like/follow bosganda 419 | Sahifani yangilang; `SANCTUM_STATEFUL_DOMAINS` da domen (va port) borligini tekshiring |
| `Too Many Requests` | Rate limit; `php artisan cache:clear` (lokal) |
| Admin panel 403 | Foydalanuvchi `role=admin` emas: `php artisan fikrlash:create-admin +998...` |
| Kesh o‘zgarishlardan keyin eskicha | `php artisan optimize:clear` |
| Windows'da `ext-gd` yo‘q | `php.ini` da `extension=gd` ni yoqing |

---

## 14. Yo‘l xaritasi

### Admin panel: boshqaruv va kuzatuv

- **Boshqaruv paneli** — jonli (30 soniyada yangilanadi): hozir onlayn foydalanuvchilar va mehmonlar, bugun faollar, yangi a’zolar, postlar, kutilayotgan shikoyatlar, moderatsiya navbati, noto‘g‘ri parollar; 7/30/90 kunlik o‘sish grafigi; onlayn foydalanuvchilar, so‘nggi kirishlar va yangi a’zolar ro‘yxati.
- **Foydalanuvchilar** — “Yangi foydalanuvchi” tugmasi; tablar (Onlayn, Bugun qo‘shilgan, Tasdiqlangan, Cheklangan, Adminlar); filtrlar (holat, rol, faollik, ro‘yxatdan o‘tgan sana, ustidan shikoyat, ko‘p noto‘g‘ri parol); CSV eksport; ommaviy tasdiqlash, xabar yuborish, bloklash. Ctrl+K — global qidiruv.
- **Foydalanuvchini tahrirlash** — istalgan maydon: avatar, ism, username, email, jins, tug‘ilgan sana, bio, telefon, yangi parol, rol, holat (+ cheklov muddati), tasdiq belgisi, ichki izoh (shifrlangan, faqat adminlar ko‘radi). Har bir o‘zgarish audit log’ga “eski → yangi” ko‘rinishida yoziladi (parol qiymati yozilmaydi). Admin o‘z rolini/holatini o‘zgartira olmaydi, yagona admin rolini olib bo‘lmaydi; parol o‘zgarsa — foydalanuvchi barcha qurilmalardan chiqariladi.
- **360° sahifa** — ko‘rsatkichlar, kirish va xavfsizlik (oxirgi kirish joyi/qurilmasi, noto‘g‘ri parollar, sessiyalar, tokenlar), shaxsiy ma’lumotlar, algoritm o‘rgangan qiziqishlar; tablar: postlar, izohlar, kirishlar tarixi, faol qurilmalar (istalganini tugatish), unga shikoyatlar, admin amallari.
- **Foydalanuvchi nomidan ko‘rish** — sayt aynan u ko‘rgandek ochiladi; pastda “Admin’ga qaytish” paneli turadi; parol/telefon/akkauntni o‘chirish bu rejimda taqiqlangan; adminlar va bloklanganlar nomidan kirib bo‘lmaydi; har bir kirish audit va kirishlar tarixiga yoziladi.
- **Kuzatuv** bo‘limi — Xavfsizlik markazi (kirishlar statistikasi, 24 soatda 5+ noto‘g‘ri urinish qilgan shubhali IP’lar), Kirishlar tarixi (`login_events`: login, noto‘g‘ri parol, bloklangan akkauntga urinish, chiqish, ro‘yxatdan o‘tish, parol tiklash, impersonatsiya; IP, qurilma, kanal — sayt/ilova/admin; 180 kun saqlanadi), Faol sessiyalar (barcha brauzer sessiyalari, tugatish), Admin amallari (audit, o‘qiladigan nomlar va o‘zgarishlar bilan).

### Tavsiya algoritmi qanday ishlaydi

Foydalanuvchi mavzu, teg yoki qiziqish tanlamaydi — post yozish faqat matn va rasmdan iborat. Postning mavzusi va kalit so‘zlarini (teglar) AI aniqlaydi, lenta esa har bir foydalanuvchining xatti-harakatidan o‘rganadi:

| Signal | Qayerdan | Ta’siri |
|---|---|---|
| Ko‘rsatildi (exposure) | Lentada kartochka 1.5 s ko‘rindi | Javob bo‘lmasa nisbat pasayadi |
| To‘xtab o‘qidi | Kartochka 4+ s / 12+ s ekranda | +0.6 / +1.2 |
| Ochdi | Post sahifasi | +1.5 |
| O‘qidi | Post sahifasida 20+ s | +1.0 |
| Like / izoh / saqlash | Tugmalar | +2 / +3 / +3 |
| Obuna | Muallifga | muallif +5 |
| "Qiziq emas" | Post menyusi | post yashiriladi, +8 javobsiz ko‘rsatish |

Har bir kategoriya, teg va muallif uchun `lift = ((score + k·μ) / (exposures + k)) / μ` hisoblanadi (μ = 0.25, k = 4): ma’lumot kam bo‘lsa lift ≈ 1, ko‘p ko‘rsatilib e’tiborsiz qolgan narsa < 1, sevimli mavzu > 1. Post bahosi: `hot × kat.lift × teg.lift^0.7 × muallif.lift^0.9 × obuna × AI sifat × (ko‘rilgan bo‘lsa 0.25)`. Keyin bitta muallif ketma-ket ikkitadan ko‘p chiqmaydi va har 6-o‘ringa foydalanuvchi deyarli ko‘rmagan mavzudan post qo‘yiladi. Barcha koeffitsientlar `config/fikrlash.php` → `taste`.

Mavzular (kategoriyalar) faqat tizim ichida ishlatiladi: AI har bir postga mavzu belgilaydi, algoritm shu orqali didni o‘rganadi, admin panelda statistika ko‘rinadi. Foydalanuvchi interfeysida, API va sitemap'da mavzular yo‘q.

### Tasdiqlangan akkauntlar, teglar va brending

- **Tasdiqlangan belgisi** (`users.verified_at`, `verified_by`): admin panelda foydalanuvchi qatoridagi “Tasdiqlash” amali yoki bir nechtasini birdan (bulk). Belgi lentada, post va izohlarda, profilda, qidiruv va takliflarda, bildirishnomalarda, API'da (`is_verified`) ko‘rinadi. Tasdiqlangan mualliflar qidiruvda birinchi va tavsiyada kichik ustunlikka ega. Har bir o‘zgarish audit log'ga yoziladi, foydalanuvchiga bildirishnoma boradi. Ismga ✓ kabi belgilar yozib soxtalashtirib bo‘lmaydi.
- **Admin foydalanuvchi yaratadi:** Foydalanuvchilar → “Yangi”. Telefon, parol, rol, holat va tasdiq belgisi bir formada; telefon tasdiqlangan hisoblanadi.
- **Teglar:** foydalanuvchi matnda `#so‘z` yozadi yoki “+ Teg qo‘shish” maydonidan mavjud tegni tanlaydi / yangisini yaratadi (ko‘pi bilan 5 ta). `#` va `@` yozilganda takliflar chiqadi (`/compose/tags`, `/compose/users`). AI teg qo‘shmaydi — teglar faqat foydalanuvchining o‘zidan.
- **Yozish sahifasi:** qoralama brauzerda avtomatik saqlanadi, rasmni sudrab tashlash va Ctrl+V, “Ko‘rinishi” (oldindan ko‘rish), so‘z soni va o‘qish vaqti, belgilar halqasi, Ctrl+Enter.
- **Brending:** Tizim sozlamalari → Brending: sayt nomi, logo (yorug‘ va tungi rejim uchun), favicon. Logo yuklanmasa — standart “fikrlash.” so‘z belgisi. Fayllar `storage/app/public/branding` da (`php artisan storage:link` kerak).
- **Kun savoli:** Tizim sozlamalari → Bosh sahifa. Lenta tepasida chiqadi va yozishga undaydi.

### Lentadan postga o‘tish

Lentadagi post bosilganda sahifa almashmaydi: post lenta ustidagi oynada ochiladi (URL `/posts/{id}` ga o‘zgaradi — ulashish mumkin; serverdan `X-Fragment: post` bilan faqat post bo‘lagi olinadi). “Orqaga”, Esc yoki brauzerning orqaga tugmasi lentaga aynan o‘sha joyga qaytaradi — yuklangan postlar, scroll va yozilayotgan matn saqlanadi, kelgan post bir lahza belgilanadi. Oynada qo‘yilgan like/saqlash va izohlar soni lentadagi kartochkada ham darhol yangilanadi. Lentada turib “Lenta”/logo bosilsa — yuqoriga silliq qaytadi.

### Qidiruv

Qidiruv real vaqtda ishlaydi: foydalanuvchi yozishni to‘xtatgach (250 ms) natijalar orqa fonda yangilanadi, sahifa qayta yuklanmaydi va URL o‘zgaradi (`/search/live`). Sarlavhadagi qidiruv maydoni yozish bilan odamlar, teglar va fikrlar bo‘yicha tezkor takliflarni ko‘rsatadi (`/search/suggest`, ↑/↓ bilan tanlash, Esc — yopish, Enter — to‘liq natijalar). Eski so‘rovlar bekor qilinadi (AbortController), limit — daqiqasiga 150 so‘rov.

**MVP (tayyor):** autentifikatsiya + SMS OTP, profil, postlar (matn + rasm; mavzu va teglarni AI aniqlaydi), izoh va javoblar, like, saqlash, obuna, o‘rganuvchi shaxsiy lenta, qidiruv, bildirishnomalar va mention'lar, shikoyatlar, admin panel, audit log, REST API + tokenlar, AI tahlil va moderatsiya signali, xatti-harakatdan o‘rganadigan tavsiyalar ("Qiziq emas", kashfiyot), o‘qish vaqti, trend teglar, SEO (meta, OpenGraph, JSON-LD, sitemap), dark mode.

**V2:** real-time bildirishnomalar (Laravel Reverb), Horizon, Meilisearch (Laravel Scout), kirill ↔ lotin qidiruv, haftalik dayjest, foydalanuvchini bloklash/mute, admin 2FA, rasmlar uchun CDN va bir nechta o‘lcham.

**V3:** ML tavsiya modeli (`RecommendationService::rank()` o‘rniga), AI yordamchi, ovozli/video postlar, hamjamiyatlar, shaxsiy xabarlar, badge va gamification, muallif analitikasi, premium.
