# Firebase (push bildirishnomalar)

Bu papkaga Firebase konsolidan olingan **`google-services.json`** qo‘yiladi
(Project settings → Your apps → Android ilova `uz.fikrlash.app` → google-services.json).
Bu fayl maxfiy emas — ilova ichiga kiradi, repoda saqlash mumkin.

**Service account** kaliti (`firebase-service-account.json`) esa MAXFIY — u repoga emas,
faqat serverga qo‘yiladi: `storage/app/private/firebase-service-account.json`
(yoki `.env` da `FCM_CREDENTIALS=/to‘liq/yo‘l/fayl.json`).
