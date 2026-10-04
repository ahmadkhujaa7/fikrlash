import { api } from './api';

/**
 * Lentadagi signallar (tavsiya algoritmi uchun):
 *  - ko‘rish: kartochka ekranning kamida 60% qismida 1.5 soniya tursa — "ko‘rsatildi";
 *  - to‘xtash vaqti (dwell): kartochka ko‘rinib turgan umumiy vaqt; 4+ soniya — "o‘qidi".
 * Hammasi to‘planadi va 4 soniyada bir marta bitta so‘rov bilan yuboriladi.
 */
const seen = new Set();
const queue = new Set();
const timers = new Map();
const visibleSince = new Map(); // id -> ko‘rina boshlagan vaqt (ms)
const dwellTotal = new Map(); // id -> to‘plangan soniyalar
const dwellSent = new Set();
let observer = null;

function stopClock(id) {
    const since = visibleSince.get(id);
    if (since === undefined) return;
    visibleSince.delete(id);
    dwellTotal.set(id, (dwellTotal.get(id) ?? 0) + (performance.now() - since) / 1000);
}

function collectDwell() {
    const dwell = {};
    for (const [id, secs] of dwellTotal) {
        if (!dwellSent.has(id) && secs >= 4 && !visibleSince.has(id)) {
            dwell[id] = Math.min(600, Math.round(secs));
            dwellSent.add(id);
        }
    }
    return dwell;
}

function flush() {
    const ids = [...queue].slice(0, 30);
    ids.forEach((id) => queue.delete(id));
    const dwell = document.body.dataset.auth ? collectDwell() : {};
    if (ids.length === 0 && Object.keys(dwell).length === 0) return;
    api('POST', '/api/v1/views', { post_ids: ids, dwell }, { keepalive: true }).catch(() => {});
}

setInterval(flush, 4000);
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        [...visibleSince.keys()].forEach(stopClock);
        flush();
    }
});

export function initViewTracking(root) {
    if (!('IntersectionObserver' in window)) return;
    observer ??= new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                const id = Number(entry.target.dataset.postId);
                if (!id) return;
                // Uzun post ekranga to‘liq sig‘masligi mumkin — ekranning yarmini egallasa ham "ko‘rinmoqda".
                const visible = entry.isIntersecting
                    && (entry.intersectionRatio >= 0.6 || entry.intersectionRect.height >= window.innerHeight * 0.5);
                if (visible && !document.hidden) {
                    if (!visibleSince.has(id)) visibleSince.set(id, performance.now());
                    if (!seen.has(id) && !timers.has(id)) {
                        timers.set(id, setTimeout(() => {
                            seen.add(id);
                            queue.add(id);
                        }, 1500));
                    }
                } else {
                    stopClock(id);
                    if (!seen.has(id)) {
                        clearTimeout(timers.get(id));
                        timers.delete(id);
                    }
                }
            });
        },
        { threshold: [0, 0.3, 0.6, 1] },
    );
    root.querySelectorAll('[data-track-view]').forEach((el) => observer.observe(el));
}

/** Post sahifasida faol o‘qish vaqtini o‘lchaydi (tab ko‘rinmayotganda hisoblanmaydi). */
export function readTimer(url) {
    return {
        seconds: 0,
        sent: 0,
        _tick: null,
        _onHide: null,
        init() {
            this._tick = setInterval(() => !document.hidden && this.seconds++, 1000);
            this._onHide = () => document.hidden && this.send();
            document.addEventListener('visibilitychange', this._onHide);
            window.addEventListener('pagehide', () => this.send());
        },
        send() {
            const delta = this.seconds - this.sent;
            if (delta < 3) return;
            this.sent = this.seconds;
            api('POST', url, { seconds: delta }, { keepalive: true }).catch(() => {});
        },
        // Lenta ustidagi oyna yopilganda (element olib tashlanadi) — o‘qish vaqti yuboriladi.
        destroy() {
            this.send();
            clearInterval(this._tick);
            document.removeEventListener('visibilitychange', this._onHide);
        },
    };
}
