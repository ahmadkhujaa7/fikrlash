/**
 * Qurilma imkoniyatlari: brauzer bildirishnomalari va ruxsatlar (bildirishnoma, mikrofon, kamera, joylashuv).
 *
 * Bildirishnomalar sayt ochiq turganda ishlaydi (boshqa oyna/ilovada bo‘lsangiz ham): menyu belgilari
 * yangilanganda (badges, ~30 soniya) eng so‘nggi o‘qilmagan bildirishnoma yoki xabar tizim bildirishnomasi
 * bo‘lib chiqadi. Android'da ko‘rsatish uchun Service Worker kerak (/sw.js — faqat bosilganda sahifani ochadi).
 */
import { api } from './api';
import { explainGeoError, explainMicError, locate } from './hardware';

const toast = (message, type) => window.toast?.(message, type);
const PUSH_KEY = 'fk:push';
const LAST_KEY = 'fk:push:last';
const OFFER_KEY = 'fk:push:offer-dismissed';

const store = {
    get(key) {
        try {
            return localStorage.getItem(key);
        } catch {
            return null;
        }
    },
    set(key, value) {
        try {
            if (value === null) localStorage.removeItem(key);
            else localStorage.setItem(key, value);
        } catch {
            /* xususiy rejim */
        }
    },
};

const supported = () => 'Notification' in window;
const permission = () => (supported() ? Notification.permission : 'unsupported');

let swRegistration = null;
async function registration() {
    if (!('serviceWorker' in navigator) || !window.isSecureContext) return null;
    if (swRegistration) return swRegistration;
    try {
        swRegistration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
        await navigator.serviceWorker.ready;
    } catch {
        swRegistration = null;
    }
    return swRegistration;
}

async function showSystemNotification(title, { body = '', url = '/', tag, icon } = {}) {
    const options = { body, tag, icon: icon || '/icons/icon-192.png', badge: '/icons/icon-192.png', data: { url } };
    const reg = await registration();
    if (reg?.showNotification) {
        await reg.showNotification(title, options);
        return;
    }
    const n = new Notification(title, options);
    n.onclick = () => {
        window.focus();
        if (url) location.href = url;
        n.close();
    };
}

export const push = {
    enabled() {
        return store.get(PUSH_KEY) === '1' && permission() === 'granted';
    },
    /** Ruxsat so‘raydi va yoqadi. Natija: true — yoqildi. */
    async enable() {
        if (!supported()) {
            toast('Brauzeringiz bildirishnomalarni qo‘llab-quvvatlamaydi.', 'error');
            return false;
        }
        if (!window.isSecureContext) {
            toast('Bildirishnomalar uchun sayt HTTPS orqali ochilishi kerak.', 'error');
            return false;
        }
        let result = Notification.permission;
        if (result === 'default') result = await Notification.requestPermission();
        if (result !== 'granted') {
            toast('Ruxsat berilmadi. Brauzer sozlamalaridan yoqishingiz mumkin.', 'error');
            return false;
        }
        store.set(PUSH_KEY, '1');
        store.set(LAST_KEY, null); // eski o‘qilmaganlar qayta chiqmasin — keyingi so‘rovda boshlang‘ich nuqta olinadi
        await registration();
        window.Alpine?.store('badges')?.refresh();
        return true;
    },
    disable() {
        store.set(PUSH_KEY, '0');
    },
    async test() {
        if (!this.enabled() && !(await this.enable())) return;
        await showSystemNotification('Fikrlash', { body: 'Bildirishnomalar ishlayapti! Yangi xabarlar shu yerda ko‘rinadi.', url: '/notifications', tag: 'fk-test' });
    },
    /** /badges?latest=1 javobi: yangi narsa bo‘lsa — tizim bildirishnomasi. */
    async handle(latest) {
        let last = {};
        try {
            last = JSON.parse(store.get(LAST_KEY) || 'null') ?? null;
        } catch {
            last = null;
        }
        const next = { n: Math.max(last?.n ?? 0, latest?.notification?.id ?? 0), m: Math.max(last?.m ?? 0, latest?.message?.id ?? 0) };
        store.set(LAST_KEY, JSON.stringify(next));
        if (last === null) return; // birinchi tekshiruv — faqat boshlang‘ich nuqta

        // Sahifa ko‘rinib turgan bo‘lsa — menyudagi belgi yetarli; boshqa oynada bo‘lsa — tizim bildirishnomasi.
        const away = document.hidden || !document.hasFocus();
        const m = latest?.message;
        if (m && m.id > (last.m ?? 0) && away) {
            await showSystemNotification(m.title, { body: m.body, url: m.url, tag: `msg-${m.url}`, icon: m.icon });
        }
        const n = latest?.notification;
        if (n && n.id > (last.n ?? 0) && away) {
            await showSystemNotification(n.title, { body: n.body, url: n.url, tag: `n-${n.id}`, icon: n.icon });
        }
    },
};

/** Ruxsat holati: granted | denied | prompt | unsupported | unknown. */
async function permissionState(name) {
    if (name === 'notifications') {
        const p = permission();
        return p === 'default' ? 'prompt' : p;
    }
    if (name === 'geolocation' && !navigator.geolocation) return 'unsupported';
    if ((name === 'microphone' || name === 'camera') && !navigator.mediaDevices?.getUserMedia) return window.isSecureContext ? 'unsupported' : 'insecure';
    try {
        const status = await navigator.permissions.query({ name });
        return status.state;
    } catch {
        return 'unknown'; // masalan, Firefox mikrofon holatini aytmaydi — so‘ralganda bilinadi
    }
}

async function requestPermission(name) {
    if (!window.isSecureContext) {
        toast('Ruxsatlar faqat HTTPS orqali ochilgan saytda so‘raladi.', 'error');
        return;
    }
    if (name === 'notifications') {
        await push.enable();
        return;
    }
    if (name === 'geolocation') {
        try {
            await locate();
            toast('Joylashuv ishlayapti.', 'success');
        } catch (err) {
            explainGeoError(err);
        }
        return;
    }
    try {
        const stream = await navigator.mediaDevices.getUserMedia(name === 'camera' ? { video: true } : { audio: true });
        stream.getTracks().forEach((t) => t.stop());
        toast(name === 'camera' ? 'Kamera ishlayapti.' : 'Mikrofon ishlayapti.', 'success');
    } catch (err) {
        if (name === 'microphone') explainMicError(err);
        else toast(err?.name === 'NotAllowedError' ? 'Kameraga ruxsat berilmadi — telefon/kompyuter sozlamalarida brauzerga kamera ruxsatini bering.' : 'Kamera topilmadi yoki band.', 'error');
    }
}

export function registerDevice(Alpine) {
    window.fkPush = push;

    // Bildirishnomani bosish — Service Worker sahifani ochadi; agar SW bo‘lmasa, oddiy Notification.
    if (push.enabled()) registration();

    /* Sozlamalar → Bildirishnomalar: shu qurilmada ko‘rsatish */
    Alpine.data('devicePush', () => ({
        state: permission(),
        on: push.enabled(),
        secure: window.isSecureContext,
        busy: false,
        async toggle() {
            if (this.busy) return;
            this.busy = true;
            if (this.on) {
                push.disable();
                this.on = false;
            } else {
                this.on = await push.enable();
            }
            this.state = permission();
            this.busy = false;
        },
        test() {
            push.test();
        },
    }));

    /* Bildirishnomalar sahifasidagi taklif */
    Alpine.data('pushOffer', () => ({
        visible: supported() && window.isSecureContext && permission() === 'default' && store.get(OFFER_KEY) !== '1' && store.get(PUSH_KEY) !== '0',
        async enable() {
            this.visible = false;
            if (await push.enable()) toast('Bildirishnomalar yoqildi.', 'success');
        },
        dismiss() {
            this.visible = false;
            store.set(OFFER_KEY, '1');
        },
    }));

    /* Sozlamalar → Ruxsatlar */
    Alpine.data('permissionsPage', () => ({
        secure: window.isSecureContext,
        items: [
            { key: 'notifications', state: 'unknown' },
            { key: 'microphone', state: 'unknown' },
            { key: 'camera', state: 'unknown' },
            { key: 'geolocation', state: 'unknown' },
        ],
        help: null,
        async init() {
            await this.refresh();
            // Brauzer sozlamalarida o‘zgartirib qaytilganda — yangilanadi.
            document.addEventListener('visibilitychange', () => !document.hidden && this.refresh());
        },
        async refresh() {
            for (const item of this.items) item.state = await permissionState(item.key);
        },
        stateOf(key) {
            return this.items.find((i) => i.key === key)?.state ?? 'unknown';
        },
        async request(key) {
            await requestPermission(key);
            await this.refresh();
            if (this.stateOf(key) === 'denied') this.help = key;
        },
    }));

    /* Admin e'loni: bosilganda ochiladi va "ochdi" deb belgilanadi */
    Alpine.data('announcement', (url) => ({
        open: false,
        sent: false,
        show() {
            this.open = true;
            document.documentElement.classList.add('overlay-lock');
            if (!this.sent) {
                this.sent = true;
                api('POST', url).catch(() => (this.sent = false));
            }
        },
        close() {
            this.open = false;
            document.documentElement.classList.remove('overlay-lock');
        },
    }));
}
