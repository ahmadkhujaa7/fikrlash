/**
 * Mikrofon va joylashuv: xatoning HAQIQIY sababini aniqlash va tushunarli yo‘riqnoma.
 *
 * Eng ko‘p uchraydigan holat: saytga brauzerda ruxsat berilgan, lekin brauzerning o‘ziga
 * telefon/kompyuter sozlamalarida ruxsat yo‘q (Windows "Maxfiylik → Mikrofon/Joylashuv",
 * Android "Ilovalar → Chrome → Ruxsatlar", GPS o‘chiq). Shunda brauzer "ruxsat yo‘q" deydi.
 */

const ua = navigator.userAgent;
export const platform = /Android/i.test(ua)
    ? 'android'
    : /iPhone|iPad|iPod/i.test(ua) || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1)
      ? 'ios'
      : /Windows/i.test(ua)
        ? 'windows'
        : /Macintosh|Mac OS X/i.test(ua)
          ? 'mac'
          : 'other';

/** Telegram, Instagram, Facebook ichidagi brauzerlar mikrofon/joylashuvni ko‘pincha bermaydi. */
export const inAppBrowser = /Telegram|Instagram|FBAN|FBAV|FB_IAB|Line\//i.test(ua) ? (ua.match(/Telegram|Instagram|Line/i)?.[0] ?? 'Facebook') : null;

/** Sayt sarlavhasi (Permissions-Policy) bu imkoniyatni taqiqlaganmi. */
export function policyBlocks(feature) {
    try {
        const policy = document.permissionsPolicy ?? document.featurePolicy;
        return policy?.allowsFeature ? !policy.allowsFeature(feature) : false;
    } catch {
        return false;
    }
}

/** Brauzerdagi sayt ruxsati: granted | denied | prompt | unknown. */
export async function sitePermission(name) {
    try {
        return (await navigator.permissions.query({ name })).state;
    } catch {
        return 'unknown';
    }
}

const SYSTEM = {
    microphone: {
        windows: 'Windows: Sozlamalar → Maxfiylik va xavfsizlik → Mikrofon → «Mikrofonga kirish», «Ilovalarga ruxsat» va «Ish stoli ilovalariga ruxsat» ni yoqing. So‘ng brauzerni qayta oching.',
        mac: 'Mac: Tizim sozlamalari → Maxfiylik va xavfsizlik → Mikrofon → brauzeringizni (Chrome/Safari) yoqing va brauzerni qayta oching.',
        android: 'Telefon: Sozlamalar → Ilovalar → Chrome (yoki brauzeringiz) → Ruxsatlar → Mikrofon → «Ruxsat berish».',
        ios: 'iPhone: Sozlamalar → Safari → Mikrofon → «Ruxsat berish» yoki «So‘rash».',
        other: 'Qurilma sozlamalarida brauzerga mikrofondan foydalanishga ruxsat bering.',
    },
    geolocation: {
        windows: 'Windows: Sozlamalar → Maxfiylik va xavfsizlik → Joylashuv → «Joylashuv xizmatlari» va «Ish stoli ilovalariga joylashuvga ruxsat» ni yoqing. So‘ng brauzerni qayta oching.',
        mac: 'Mac: Tizim sozlamalari → Maxfiylik va xavfsizlik → Joylashuv xizmatlari → yoqing va ro‘yxatda brauzeringizni belgilang.',
        android: 'Telefon: yuqoridan pastga suring va «Joylashuv» (GPS) ni yoqing. Bo‘lmasa: Sozlamalar → Ilovalar → Chrome → Ruxsatlar → Joylashuv → «Ilova ishlatilganda».',
        ios: 'iPhone: Sozlamalar → Maxfiylik → Joylashuv xizmatlari → yoqing; pastda «Safari saytlari» → «Ilova ishlatilganda».',
        other: 'Qurilma sozlamalarida joylashuv xizmatini yoqing va brauzerga ruxsat bering.',
    },
};
const SITE = {
    microphone: 'Brauzerda: manzil satridagi qulf belgisini bosing → Mikrofon → «Ruxsat berish», so‘ng sahifani yangilang.',
    geolocation: 'Brauzerda: manzil satridagi qulf belgisini bosing → Joylashuv → «Ruxsat berish», so‘ng sahifani yangilang.',
};

const explain = (title, text) =>
    window.confirmAction
        ? window.confirmAction({ title, text, ok: 'Tushunarli', tone: 'primary', cancel: false })
        : (window.alert(`${title}\n\n${text}`), Promise.resolve(true));

/** Mikrofon xatosi → tushuntirish oynasi. */
export async function explainMicError(err) {
    const name = err?.name ?? '';
    const message = String(err?.message ?? '');
    const site = await sitePermission('microphone');

    if (!window.isSecureContext) return explain('Ovoz yozib bo‘lmaydi', 'Ovoz yozish faqat sayt https:// orqali ochilganda ishlaydi.');
    if (policyBlocks('microphone')) {
        return explain('Mikrofon sayt sozlamasida o‘chirilgan', 'Server javobidagi Permissions-Policy sarlavhasi mikrofonni taqiqlagan. Sayt administratori serverdagi (nginx/hosting) sozlamani tekshirishi kerak: "microphone=(self)" bo‘lishi lozim.');
    }
    if (inAppBrowser && (name === 'NotAllowedError' || !navigator.mediaDevices?.getUserMedia)) {
        return explain(`${inAppBrowser} ichidagi brauzer`, `${inAppBrowser} ichida ochilgan sahifada ovoz yozish ishlamaydi. «⋯» menyusidan «Brauzerda ochish» ni tanlang (Chrome yoki Safari).`);
    }
    if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
        return explain('Brauzer ovoz yozishni qo‘llamaydi', 'Brauzeringizni yangilang yoki Chrome / Safari / Firefox’ning so‘nggi versiyasidan foydalaning.');
    }
    if (name === 'NotFoundError' || name === 'OverconstrainedError' || name === 'DevicesNotFoundError') {
        return explain('Mikrofon topilmadi', 'Qurilmaga mikrofon (yoki quloqchin) ulanganini tekshiring.');
    }
    if (name === 'NotReadableError' || name === 'TrackStartError' || name === 'AbortError') {
        return explain('Mikrofon band', 'Mikrofonni boshqa dastur ishlatayotgan bo‘lishi mumkin (Zoom, Telegram qo‘ng‘irog‘i, diktofon). Uni yoping va qayta urinib ko‘ring. Yordam bermasa: ' + SYSTEM.microphone[platform]);
    }
    if (name === 'NotAllowedError' || name === 'PermissionDeniedError' || name === 'SecurityError') {
        // Sayt uchun ruxsat bor (yoki brauzer "tizim" deydi) — demak qurilma sozlamasi to‘sgan.
        if (site === 'granted' || /system|dismiss/i.test(message)) {
            return explain('Qurilma mikrofonni bermayapti', `Saytga brauzerda ruxsat berilgan, lekin ${platform === 'android' || platform === 'ios' ? 'telefon' : 'kompyuter'} sozlamalari brauzerga mikrofonni bermayapti.\n\n${SYSTEM.microphone[platform]}`);
        }
        return explain('Mikrofonga ruxsat berilmagan', `${SITE.microphone}\n\nAgar ruxsat berilgan bo‘lsa: ${SYSTEM.microphone[platform]}`);
    }
    return explain('Ovoz yozib bo‘lmadi', `${message || name || 'Noma’lum xato'}.\n\n${SYSTEM.microphone[platform]}`);
}

/** Joylashuv xatosi → tushuntirish oynasi. */
export async function explainGeoError(err) {
    const site = await sitePermission('geolocation');

    if (!window.isSecureContext) return explain('Joylashuv aniqlanmaydi', 'Joylashuv faqat sayt https:// orqali ochilganda ishlaydi.');
    if (policyBlocks('geolocation')) {
        return explain('Joylashuv sayt sozlamasida o‘chirilgan', 'Server javobidagi Permissions-Policy sarlavhasi joylashuvni taqiqlagan. Sayt administratori serverdagi (nginx/hosting) sozlamani tekshirishi kerak: "geolocation=(self)" bo‘lishi lozim.');
    }
    if (!navigator.geolocation) return explain('Brauzer joylashuvni aniqlay olmaydi', 'Brauzeringizni yangilang yoki boshqa brauzerdan foydalaning.');
    if (inAppBrowser && err?.code === 1) {
        return explain(`${inAppBrowser} ichidagi brauzer`, `${inAppBrowser} ichida ochilgan sahifada joylashuv ishlamasligi mumkin. «⋯» menyusidan «Brauzerda ochish» ni tanlang.`);
    }
    if (err?.code === 1) {
        if (site === 'granted') {
            return explain('Qurilma joylashuvni bermayapti', `Saytga brauzerda ruxsat berilgan, lekin ${platform === 'android' || platform === 'ios' ? 'telefonda' : 'kompyuterda'} joylashuv xizmati o‘chiq yoki brauzerga ruxsat yo‘q.\n\n${SYSTEM.geolocation[platform]}`);
        }
        return explain('Joylashuvga ruxsat berilmagan', `${SITE.geolocation}\n\nAgar ruxsat berilgan bo‘lsa: ${SYSTEM.geolocation[platform]}`);
    }
    if (err?.code === 3) {
        return explain('Joylashuv aniqlanmadi', 'Juda uzoq kutildi. Ochiq joyga chiqing yoki Wi‑Fi’ni yoqing (kompyuterda Wi‑Fi bo‘lsa aniqroq topiladi) va qayta urinib ko‘ring.');
    }
    return explain('Joylashuv aniqlanmadi', `Qurilma joylashuvni topa olmadi.\n\n${SYSTEM.geolocation[platform]}`);
}

/**
 * Joylashuvni olish: avval aniq (GPS), bo‘lmasa — taxminiy (Wi‑Fi/tarmoq) usulda qayta urinadi.
 * Kompyuterlarda GPS yo‘q — aniq usul ko‘pincha xato beradi, taxminiysi esa ishlaydi.
 */
export function locate() {
    const attempt = (options) => new Promise((resolve, reject) => navigator.geolocation.getCurrentPosition(resolve, reject, options));
    return attempt({ enableHighAccuracy: true, timeout: 12000, maximumAge: 30000 }).catch((err) => {
        if (err?.code === 1) throw err; // ruxsat yo‘q — qayta urinish befoyda
        return attempt({ enableHighAccuracy: false, timeout: 20000, maximumAge: 300000 });
    });
}

/** "±35 m", "±2,4 km". */
export function formatAccuracy(meters) {
    const m = Math.round(Number(meters) || 0);
    if (!m) return '';
    return m < 1000 ? `±${m} m` : `±${(m / 1000).toLocaleString('uz', { maximumFractionDigits: m < 10000 ? 1 : 0 })} km`;
}
