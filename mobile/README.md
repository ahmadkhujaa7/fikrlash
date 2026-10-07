# Fikrlash — mobil ilova (Android)

Capacitor 8 asosida: ilova `https://fikrlash.uz` saytini ochadi, ustiga native imkoniyatlar qo‘shiladi —
push bildirishnomalar, havolalarni ilovada ochish, "Orqaga" tugmasi, telefonning "Ulashish" menyusi,
kamera, mikrofon (ovozli xabar), joylashuv, internet yo‘q bo‘lganda alohida sahifa.
Saytdagi har bir yangilanish ilovada darhol ko‘rinadi — ilovani qayta yuklash shart emas.

## Qanday yig‘iladi
`android/` papkasi repoda saqlanmaydi. GitHub Actions (`.github/workflows/android.yml`) har safar:
1. `npm install` → `npx cap add android`
2. `node scripts/prepare-android.mjs` — ikonkalar, ruxsatlar, havolalar, imzo, versiya
3. `npx cap sync android` → `./gradlew assembleRelease bundleRelease`

Natija: **Releases → "Android (sinov)" → fikrlash.apk** (telefonga o‘rnatish uchun) va
Actions'dagi fayllar ichida `fikrlash.aab` (Google Play'ga yuklash uchun).

## Imzolash
- `signing/upload.p12` — Google Play uchun yuklash kaliti (AES-256 bilan shifrlangan, parolsiz ochilmaydi).
  Parol GitHub'da: Settings → Secrets and variables → Actions → `ANDROID_KEYSTORE_PASSWORD`.
- `signing/debug.keystore` — sinov kaliti (parol `android`), secret qo‘yilmaguncha ishlatiladi.
- Ikkala kalitning SHA-256 izi `config/fikrlash.php` → `mobile.android_sha256` da (saytdagi
  `/.well-known/assetlinks.json` — havolalar ilovada ochilishi uchun). Google Play'ga chiqqach,
  Play Console → App integrity → "App signing key" izini `.env` dagi `MOBILE_ANDROID_SHA256` ga qo‘shing.

## Push
`firebase/README.md` ga qarang. `google-services.json` qo‘shilmaguncha ilova pushsiz yig‘iladi
(sayt buni User-Agent'dagi `; push` belgisidan biladi).

## Lokal (ixtiyoriy)
Node 22+, JDK 21, Android Studio: `npm install && npx cap add android && node scripts/prepare-android.mjs && npx cap open android`.
