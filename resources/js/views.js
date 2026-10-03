import { api } from './api';

/**
 * Ko‘rishlarni hisoblash: post kartochkasi ekranning kamida 60% qismida 1.5 soniya tursa — "ko‘rildi".
 * Ko‘rishlar to‘planadi va 4 soniyada bir marta bitta so‘rov bilan yuboriladi.
 */
const seen = new Set();
const queue = new Set();
const timers = new Map();
let observer = null;

function flush() {
    if (queue.size === 0) return;
    const ids = [...queue].slice(0, 30);
    ids.forEach((id) => queue.delete(id));
    api('POST', '/api/v1/views', { post_ids: ids }, { keepalive: true }).catch(() => {});
}

setInterval(flush, 4000);
document.addEventListener('visibilitychange', () => document.hidden && flush());

export function initViewTracking(root) {
    if (!('IntersectionObserver' in window)) return;
    observer ??= new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                const id = Number(entry.target.dataset.postId);
                if (!id || seen.has(id)) return;
                if (entry.isIntersecting) {
                    timers.set(id, setTimeout(() => {
                        seen.add(id);
                        queue.add(id);
                        observer.unobserve(entry.target);
                    }, 1500));
                } else {
                    clearTimeout(timers.get(id));
                }
            });
        },
        { threshold: 0.6 },
    );
    root.querySelectorAll('[data-track-view]').forEach((el) => observer.observe(el));
}

/** Post sahifasida faol o‘qish vaqtini o‘lchaydi (tab ko‘rinmayotganda hisoblanmaydi). */
export function readTimer(url) {
    return {
        seconds: 0,
        sent: 0,
        init() {
            const tick = setInterval(() => !document.hidden && this.seconds++, 1000);
            const send = () => {
                const delta = this.seconds - this.sent;
                if (delta < 3) return;
                this.sent = this.seconds;
                api('POST', url, { seconds: delta }, { keepalive: true }).catch(() => {});
            };
            document.addEventListener('visibilitychange', () => document.hidden && send());
            window.addEventListener('pagehide', () => {
                send();
                clearInterval(tick);
            });
        },
    };
}
