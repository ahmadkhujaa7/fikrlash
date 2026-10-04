/**
 * Sahifalar orasida yurish — mobil ilovadagidek.
 *
 *  1. Post alohida sahifada ochiladi: havola ulashish, yangilash, "orqaga" — hammasi tabiiy ishlaydi.
 *  2. O‘tish animatsiyasi — brauzerning View Transitions API'si (CSS: app.css → "Sahifa o‘tishlari"):
 *     postga kirish — o‘ngdan suriladi, orqaga — teskari; pastki menyu bo‘limlari — yumshoq almashinadi.
 *     Qo‘llamaydigan brauzerda oddiy o‘tish bo‘ladi.
 *  3. Orqaga qaytilganda lenta o‘sha joyidan davom etadi: avvalo brauzer keshi (bfcache) — sahifa
 *     xotiradan aynan qanday bo‘lsa shunday tiklanadi; kesh bo‘lmasa — yuklangan sahifalar va qaysi
 *     postda turganingiz sessionStorage'dan tiklanadi.
 */

const FEED_TTL = 30 * 60 * 1000;
const MAX_FEED_CHARS = 8_000_000;
const NAV_KEY = 'fikrlash:nav';

const session = {
    get(key) {
        try {
            return JSON.parse(sessionStorage.getItem(key) ?? 'null');
        } catch {
            return null;
        }
    },
    set(key, value) {
        try {
            sessionStorage.setItem(key, JSON.stringify(value));
        } catch {
            /* joy tugagan yoki xususiy rejim — shunchaki saqlanmaydi */
        }
    },
    remove(key) {
        try {
            sessionStorage.removeItem(key);
        } catch {
            /* e'tiborsiz */
        }
    },
};

/* ---------- Lenta holati ---------- */

let feed = null; // { key, root, list, html, next, restoredAnchor }
let lastOpened = null; // bosilgan post kartochkasining id'si
let persistTimer = null;

const feedKey = () => `fikrlash:feed:${location.pathname}${location.search}`;
const anchorKey = () => `fikrlash:feed-anchor:${location.pathname}${location.search}`;
const canCompress = 'CompressionStream' in window && 'DecompressionStream' in window;

/** Lenta HTML'i juda "suvli" (≈10 KB/post) — gzip bilan ~25 marta kichrayadi va sessionStorage'ga bemalol sig‘adi. */
async function pack(text) {
    if (!canCompress) return { raw: text };
    const stream = new Blob([text]).stream().pipeThrough(new CompressionStream('gzip'));
    const bytes = new Uint8Array(await new Response(stream).arrayBuffer());
    let binary = '';
    for (let i = 0; i < bytes.length; i += 0x8000) binary += String.fromCharCode(...bytes.subarray(i, i + 0x8000));
    return { gz: btoa(binary) };
}

async function unpack(saved) {
    if (typeof saved.raw === 'string') return saved.raw;
    const bytes = Uint8Array.from(atob(saved.gz), (c) => c.charCodeAt(0));
    const stream = new Blob([bytes]).stream().pipeThrough(new DecompressionStream('gzip'));
    return new Response(stream).text();
}

function persistFeed() {
    clearTimeout(persistTimer);
    persistTimer = setTimeout(async () => {
        if (!feed || feed.html.length > MAX_FEED_CHARS) return;
        try {
            session.set(feed.key, { ...(await pack(feed.html)), next: feed.next, at: Date.now() });
        } catch {
            /* siqib bo‘lmadi — tiklash bo‘lmaydi, xolos */
        }
    }, 250);
}

/**
 * Alpine ishga tushishidan OLDIN chaqiriladi (Promise qaytaradi): sahifadagi lenta (data-feed) xom HTML'ini
 * eslab qoladi yoki orqaga qaytilgan bo‘lsa — saqlangan holatni joyiga qo‘yadi (Alpine keyin uni odatdagidek jonlantiradi).
 */
export async function captureFeed() {
    const root = document.querySelector('[data-feed]');
    const list = root?.querySelector('[data-feed-list]');
    if (!root || !list) return;

    const navType = performance.getEntriesByType('navigation')[0]?.type;
    const saved = session.get(feedKey());

    if (navType === 'back_forward' && saved && Date.now() - saved.at < FEED_TTL) {
        try {
            const html = await unpack(saved);
            list.innerHTML = html;
            root.dataset.next = saved.next ?? '';
            feed = { key: feedKey(), root, list, html, next: saved.next ?? '', restoredAnchor: session.get(anchorKey()) };
            return;
        } catch {
            /* buzilgan yozuv — yangidan boshlaymiz */
        }
    }

    session.remove(anchorKey());
    feed = { key: feedKey(), root, list, html: list.innerHTML, next: root.dataset.next ?? '', restoredAnchor: null };
    persistFeed();
}

/** Lentaga yangi sahifa qo‘shildi (cheksiz aylantirish) — uning xom HTML'i ham eslab qolinadi. */
export function rememberFeedChunk(root, html, next) {
    if (!feed || feed.root !== root) return;
    feed.html += html;
    feed.next = next ?? '';
    persistFeed();
}

/** Saqlangan holatdan tiklangan lentada — qaysi post ochilgan bo‘lsa, o‘sha joyga qaytaradi. */
export function restoreFeedPosition() {
    const anchor = feed?.restoredAnchor;
    if (!anchor?.id) return;
    try {
        history.scrollRestoration = 'manual';
    } catch {
        /* e'tiborsiz */
    }
    const go = () => {
        const el = document.getElementById(anchor.id);
        if (el) window.scrollTo(0, el.getBoundingClientRect().top + window.scrollY - (anchor.top ?? 0));
    };
    go();
    requestAnimationFrame(go);
    window.addEventListener('load', go, { once: true });
    if (anchor.opened) flashCard(anchor.id);
}

function currentAnchor() {
    if (lastOpened) {
        const el = document.getElementById(lastOpened);
        if (el) return { id: lastOpened, top: el.getBoundingClientRect().top, opened: true };
    }
    // Ekranning yuqorisida turgan birinchi kartochka.
    const offset = 72;
    for (const el of feed?.list.querySelectorAll(':scope > [data-item][id]') ?? []) {
        const rect = el.getBoundingClientRect();
        if (rect.bottom > offset) return { id: el.id, top: rect.top, opened: false };
    }
    return null;
}

function saveAnchor() {
    // Lenta boshqa natijalar bilan almashtirilgan bo‘lsa (jonli qidiruv) — tiklanmaydi.
    if (feed?.root.isConnected && feed.key === feedKey()) session.set(anchorKey(), currentAnchor());
}

function flashCard(id) {
    const card = document.getElementById(id);
    if (!card) return;
    card.classList.remove('flash');
    void card.offsetWidth;
    card.classList.add('flash');
}

/* ---------- Bosishlar ---------- */

function isPlainClick(e) {
    return !e.defaultPrevented && e.button === 0 && !e.metaKey && !e.ctrlKey && !e.shiftKey && !e.altKey;
}

document.addEventListener('click', (e) => {
    if (e.defaultPrevented || e.button !== 0) return;

    // Post matnining istalgan joyi bosilsa — post ochiladi (ichidagi havola va tugmalar o‘zi ishlaydi).
    const area = e.target.closest('[data-post-open]');
    if (area && !e.target.closest('a, button, input, textarea, label, summary, [role="button"]')) {
        if (String(window.getSelection?.() ?? '').trim()) return; // matn belgilanmoqda
        const url = area.dataset.postOpen;
        if (e.metaKey || e.ctrlKey) {
            window.open(url, '_blank', 'noopener');
            return;
        }
        lastOpened = area.closest('article[id]')?.id ?? null;
        navIntent('forward');
        location.href = url;
        return;
    }

    const link = e.target.closest('a[href]');
    if (!link || !isPlainClick(e) || link.target === '_blank' || link.hasAttribute('download')) return;
    let url;
    try {
        url = new URL(link.href, location.href);
    } catch {
        return;
    }
    if (url.origin !== location.origin) return;

    lastOpened = link.closest('article[id]')?.id ?? lastOpened;
    navIntent(link.closest('[data-tab]') || link.hasAttribute('data-tab') ? 'fade' : 'forward');
});

/** O‘tish turini keyingi sahifaga qoldiradi (Navigation API bo‘lmagan brauzerlar uchun). */
function navIntent(type) {
    session.set(NAV_KEY, { type, at: Date.now() });
}

/** "Orqaga": sayt ichidan kelgan bo‘lsa — tarixda orqaga (lenta o‘sha joyidan davom etadi), aks holda zaxira sahifaga. */
window.backOr = (fallback) => {
    let internal = false;
    try {
        internal = !!document.referrer && new URL(document.referrer).origin === location.origin;
    } catch {
        internal = false;
    }
    if (internal && history.length > 1) {
        navIntent('back');
        history.back();
    } else {
        navIntent('back');
        location.href = fallback;
    }
};

/* ---------- Sahifadan chiqish / qaytish ---------- */

window.addEventListener('pagehide', () => {
    saveAnchor();
});

window.addEventListener('pageshow', (e) => {
    if (!e.persisted) return;
    // bfcache: sahifa xotiradan tiklandi — ochilgan post kartochkasini bir lahza belgilaymiz.
    if (lastOpened) flashCard(lastOpened);
    lastOpened = null;
});

/* View Transitions yo‘nalishi (pagereveal) — layouts/head.blade.php dagi kichik skriptda:
 * u birinchi chizishdan oldin ro‘yxatdan o‘tishi kerak, modul skriptlar esa kechroq ishlaydi. */

/* ---------- Mobil: pastga aylantirganda sarlavha yashirinadi, yuqoriga — qaytadi ---------- */

const header = document.querySelector('[data-app-header]');
if (header) {
    let lastY = window.scrollY;
    let travel = 0;
    window.addEventListener(
        'scroll',
        () => {
            const y = window.scrollY;
            const dy = y - lastY;
            lastY = y;
            if (y < 80 || header.contains(document.activeElement)) {
                header.removeAttribute('data-hidden');
                travel = 0;
                return;
            }
            travel = Math.sign(dy) === Math.sign(travel) ? travel + dy : dy;
            if (travel > 28) header.setAttribute('data-hidden', '');
            else if (travel < -28) header.removeAttribute('data-hidden');
        },
        { passive: true },
    );
}
