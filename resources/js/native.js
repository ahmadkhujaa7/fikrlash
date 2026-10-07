/**
 * Mobil ilova (Capacitor) bilan bog‘lanish. Sayt ilova ichida ochilganda Capacitor `window.Capacitor`
 * ni qo‘shadi — plaginlar shu orqali chaqiriladi (@capacitor/core'ni saytga qo‘shish shart emas).
 * Brauzerda bu fayl hech narsa qilmaydi.
 *
 * Ilovada:
 *  - push bildirishnomalar: ruxsat so‘raladi, token serverga yuboriladi, bosilganda kerakli sahifa ochiladi;
 *  - havolalar (fikrlash.uz/...) ilovada ochiladi;
 *  - "Orqaga" tugmasi avval ochiq oynani yopadi, keyin oldingi sahifaga qaytadi;
 *  - telefonning "Ulashish" menyusi; splash ekran sahifa tayyor bo‘lganda yopiladi.
 */
import { api } from './api';

const cap = () => window.Capacitor;
export const isApp = () => Boolean(cap()?.isNativePlatform?.());
export const appPlatform = () => (isApp() ? cap().getPlatform?.() : null);
/** Ilova Firebase bilan yig‘ilganmi (aks holda push ro‘yxatdan o‘tkazilmaydi — ilova yiqilmasin). */
const pushCapable = () => /FikrlashApp\/[^ ]+ \([^)]*push/.test(navigator.userAgent);
const appVersion = () => navigator.userAgent.match(/FikrlashApp\/([\w.-]+)/)?.[1] ?? null;

const call = (plugin, method, options = {}) => cap().nativePromise(plugin, method, options);
const on = (plugin, event, callback) => cap().addListener?.(plugin, event, callback) ?? cap().nativeCallback(plugin, 'addListener', { eventName: event }, callback);

const SITE_HOSTS = [location.host, 'fikrlash.uz', 'www.fikrlash.uz'];
const toast = (message, type) => window.toast?.(message, type);

/** Sayt ichidagi manzilga o‘tish (bildirishnoma yoki havola orqali). */
function openUrl(raw) {
    if (!raw) return;
    let url;
    try {
        url = new URL(raw, location.href);
    } catch {
        return;
    }
    if (!SITE_HOSTS.includes(url.host)) return;
    const target = url.pathname + url.search + url.hash;
    if (target !== location.pathname + location.search + location.hash) location.href = target;
}

/** Ochiq oyna/menyu bo‘lsa — Esc yuboriladi (hamma oynalar Esc bilan yopiladi). */
function closeOverlay() {
    const store = window.Alpine?.store?.bind(window.Alpine);
    if (store?.('lightbox')?.open) {
        store('lightbox').close();
        return true;
    }
    if (store?.('confirm')?.open) {
        store('confirm').answer(false);
        return true;
    }
    const visible = [...document.querySelectorAll('[role="dialog"], [role="alertdialog"], [role="menu"], [aria-modal="true"]')].some((el) => el.getClientRects().length && getComputedStyle(el).display !== 'none');
    if (visible) {
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        return true;
    }
    return false;
}

let pushListening = false;

async function setupPush() {
    if (!document.body.dataset.auth || !pushCapable()) return;

    try {
        // Android: ikki kanal — foydalanuvchi telefon sozlamalarida alohida o‘chira oladi.
        if (appPlatform() === 'android') {
            await call('PushNotifications', 'createChannel', { id: 'messages', name: 'Xabarlar', description: 'Shaxsiy xabarlar', importance: 4, visibility: 0, vibration: true });
            await call('PushNotifications', 'createChannel', { id: 'activity', name: 'Bildirishnomalar', description: 'Yoqtirishlar, izohlar, obunalar va e’lonlar', importance: 3, visibility: 1 });
        }

        let { receive } = await call('PushNotifications', 'checkPermissions');
        if (receive === 'prompt' || receive === 'prompt-with-rationale') {
            ({ receive } = await call('PushNotifications', 'requestPermissions'));
        }
        if (receive !== 'granted') return;

        if (!pushListening) {
            pushListening = true;
            on('PushNotifications', 'registration', ({ value }) => {
                if (value) api('POST', '/devices', { token: value, platform: appPlatform(), app_version: appVersion() }).catch(() => {});
            });
            on('PushNotifications', 'registrationError', (err) => console.warn('Push ro‘yxatdan o‘tmadi', err));
        }
        await call('PushNotifications', 'register');
    } catch (err) {
        console.warn('Push sozlanmadi', err);
    }
}

function setupListeners() {
    // Ilova ochiq turganda kelgan bildirishnoma — sahifada qisqa xabar, belgilar yangilanadi.
    on('PushNotifications', 'pushNotificationReceived', (n) => {
        window.Alpine?.store('badges')?.refresh();
        const url = n?.data?.url;
        const here = url && new URL(url, location.href).pathname === location.pathname;
        if (!here) toast([n?.title, n?.body].filter(Boolean).join(': '));
    });
    // Bildirishnoma bosildi (ilova yopiq bo‘lsa ham — ochilgandan keyin keladi).
    on('PushNotifications', 'pushNotificationActionPerformed', (action) => openUrl(action?.notification?.data?.url));

    // fikrlash.uz havolasi bosildi — ilova ochildi.
    on('App', 'appUrlOpen', ({ url }) => openUrl(url));
    call('App', 'getLaunchUrl')
        .then((res) => res?.url && openUrl(res.url))
        .catch(() => {});

    // Android "Orqaga" tugmasi.
    on('App', 'backButton', ({ canGoBack }) => {
        if (closeOverlay()) return;
        if (canGoBack && history.length > 1) history.back();
        else call('App', 'minimizeApp').catch(() => call('App', 'exitApp'));
    });

    // Ilovaga qaytilganda — belgilar yangilanadi.
    on('App', 'resume', () => window.Alpine?.store('badges')?.refresh());
}

/** Ilova ichidagi native imkoniyatlar (ui.js va device.js shu orqali foydalanadi). */
export const native = {
    isApp,
    platform: appPlatform,
    pushCapable,
    share: (data) => call('Share', 'share', { dialogTitle: 'Ulashish', ...data }),
    pushPermission: async () => {
        if (!pushCapable()) return 'unsupported';
        try {
            return (await call('PushNotifications', 'checkPermissions')).receive;
        } catch {
            return 'unsupported';
        }
    },
    requestPush: async () => {
        await setupPush();
        return native.pushPermission();
    },
};

export function initNative() {
    if (!isApp()) return;
    window.fkNative = native;
    document.documentElement.classList.add('in-app', `in-app-${appPlatform()}`);

    const ready = () => {
        call('SplashScreen', 'hide', { fadeOutDuration: 180 }).catch(() => {});
        setupListeners();
        setupPush();
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready, { once: true });
    else ready();
}

// Darhol: share oynasi va device.js ilovani Alpine ishga tushishidan oldin tanisin.
if (isApp()) window.fkNative = native;
