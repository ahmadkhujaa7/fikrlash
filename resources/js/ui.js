/**
 * Butun sayt bo‘ylab umumiy oynalar:
 *  - rasm/video ko‘rish (lightbox): suring, kattalashtiring (ikki marta bosish), Esc — yopish;
 *  - tasdiqlash oynasi (brauzerning xunuk confirm() o‘rniga): <form data-confirm="..."> yoki window.confirmAction();
 *  - postni ulashish oynasi: chatdagi va tizimdagi odamlarga yuborish, havolani nusxalash, Telegram.
 */
import { api } from './api';

const toast = (message, type) => window.toast?.(message, type);
const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/** Nusxa olish: Clipboard API bo‘lmasa (HTTP, eski brauzer) — yashirin maydon orqali. */
export async function copyText(text) {
    try {
        await navigator.clipboard.writeText(text);
        return true;
    } catch {
        /* pastdagi usul */
    }
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', '');
    ta.style.cssText = 'position:fixed;top:-1000px;left:0;opacity:0';
    document.body.appendChild(ta);
    ta.select();
    let ok = false;
    try {
        ok = document.execCommand('copy');
    } catch {
        ok = false;
    }
    ta.remove();
    return ok;
}

export function registerUi(Alpine) {
    window.copyText = copyText;

    /* ---------- Rasm/video ko‘rish ---------- */
    Alpine.store('lightbox', {
        open: false,
        items: [],
        index: 0,
        show(items, index = 0) {
            if (!items?.length) return;
            this.items = items;
            this.index = Math.max(0, Math.min(index, items.length - 1));
            this.open = true;
            document.documentElement.classList.add('overlay-lock');
        },
        close() {
            this.open = false;
            document.documentElement.classList.remove('overlay-lock');
        },
        get current() {
            return this.items[this.index] ?? null;
        },
        next() {
            if (this.index < this.items.length - 1) this.index++;
        },
        prev() {
            if (this.index > 0) this.index--;
        },
    });
    window.openLightbox = (items, index) => Alpine.store('lightbox').show(items, index);

    // [data-lightbox="guruh"] — bosilganda o‘sha guruhdagi rasmlar bilan ochiladi; data-full — katta nusxa manzili.
    document.addEventListener('click', (e) => {
        const el = e.target.closest('[data-lightbox]');
        if (!el || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey) return;
        const src = (n) => n.dataset.full || n.getAttribute('href') || n.querySelector('img')?.currentSrc || n.currentSrc || n.src;
        const group = el.dataset.lightbox;
        const scope = el.closest('[data-lightbox-scope]') ?? document;
        const nodes = group ? [...scope.querySelectorAll(`[data-lightbox="${CSS.escape(group)}"]`)] : [el];
        const items = nodes.map((n) => ({ type: 'image', src: src(n), caption: n.dataset.caption || '' })).filter((i) => i.src);
        if (!items.length) return;
        e.preventDefault();
        Alpine.store('lightbox').show(items, Math.max(0, nodes.indexOf(el)));
    }, true); // capture: sahifa o‘tish animatsiyasidan (navigation.js) oldin

    Alpine.data('lightboxView', () => {
        let lastTap = 0;
        let start = null;
        return {
            zoom: 1,
            origin: '50% 50%',
            pan: { x: 0, y: 0 },
            drag: { x: 0, y: 0 },
            get lb() {
                return Alpine.store('lightbox');
            },
            get stageStyle() {
                const moving = start ? '' : `transition: transform ${reduceMotion() ? 0 : 220}ms ease;`;
                if (this.zoom > 1) return `${moving}transform: translate(${this.pan.x}px, ${this.pan.y}px)`;
                const fade = Math.max(0.35, 1 - Math.abs(this.drag.y) / 400);
                return `${moving}transform: translate(${this.drag.x}px, ${this.drag.y}px); opacity:${fade}`;
            },
            get imageStyle() {
                return `transform-origin:${this.origin};transform:scale(${this.zoom});transition:transform ${reduceMotion() ? 0 : 200}ms ease`;
            },
            reset() {
                this.zoom = 1;
                this.pan = { x: 0, y: 0 };
                this.drag = { x: 0, y: 0 };
            },
            close() {
                this.reset();
                this.lb.close();
            },
            go(step) {
                this.reset();
                step > 0 ? this.lb.next() : this.lb.prev();
            },
            onKey(e) {
                if (!this.lb.open) return;
                if (e.key === 'Escape') this.close();
                else if (e.key === 'ArrowRight') this.go(1);
                else if (e.key === 'ArrowLeft') this.go(-1);
            },
            toggleZoom(e) {
                if (this.zoom > 1) {
                    this.reset();
                    return;
                }
                const rect = e.currentTarget.getBoundingClientRect();
                const x = (((e.clientX ?? rect.left + rect.width / 2) - rect.left) / rect.width) * 100;
                const y = (((e.clientY ?? rect.top + rect.height / 2) - rect.top) / rect.height) * 100;
                this.origin = `${x}% ${y}%`;
                this.zoom = 2.5;
            },
            touchStart(e) {
                if (e.touches.length !== 1) {
                    start = null;
                    return;
                }
                const t = e.touches[0];
                start = { x: t.clientX, y: t.clientY, pan: { ...this.pan } };
            },
            touchMove(e) {
                if (!start || e.touches.length !== 1) return;
                const t = e.touches[0];
                const dx = t.clientX - start.x;
                const dy = t.clientY - start.y;
                if (this.zoom > 1) this.pan = { x: start.pan.x + dx, y: start.pan.y + dy };
                else this.drag = Math.abs(dy) > Math.abs(dx) ? { x: 0, y: dy } : { x: dx, y: 0 };
            },
            touchEnd(e) {
                if (!start) return;
                const { x: dx, y: dy } = this.drag;
                const moved = Math.abs(dx) > 8 || Math.abs(dy) > 8 || Math.abs(this.pan.x - start.pan.x) > 8 || Math.abs(this.pan.y - start.pan.y) > 8;
                start = null;
                if (this.zoom === 1) {
                    this.drag = { x: 0, y: 0 };
                    if (Math.abs(dy) > 110) return this.close();
                    if (dx < -60) return this.go(1);
                    if (dx > 60) return this.go(-1);
                }
                // Ikki marta tegish — kattalashtirish.
                if (!moved && e.target.closest('[data-zoomable]')) {
                    const now = Date.now();
                    if (now - lastTap < 300) {
                        const t = e.changedTouches[0];
                        this.toggleZoom({ currentTarget: e.target.closest('[data-zoomable]'), clientX: t.clientX, clientY: t.clientY });
                        lastTap = 0;
                    } else {
                        lastTap = now;
                    }
                }
            },
        };
    });

    /* ---------- Tasdiqlash oynasi ---------- */
    Alpine.store('confirm', {
        open: false,
        title: '',
        text: '',
        ok: 'Tasdiqlash',
        tone: 'danger',
        _resolve: null,
        cancel: true,
        ask({ title, text = '', ok = 'Tasdiqlash', tone = 'danger', cancel = true } = {}) {
            this._resolve?.(false);
            Object.assign(this, { title, text, ok, tone, cancel, open: true });
            return new Promise((resolve) => (this._resolve = resolve));
        },
        answer(value) {
            this.open = false;
            const resolve = this._resolve;
            this._resolve = null;
            resolve?.(value);
        },
    });
    window.confirmAction = (opts = {}) => {
        const o = typeof opts === 'string' ? { title: opts } : opts;
        if (!document.querySelector('[data-confirm-dialog]')) return Promise.resolve(window.confirm([o.title, o.text].filter(Boolean).join('\n\n')));
        return Alpine.store('confirm').ask(o);
    };

    // <form data-confirm="Savol" data-confirm-text="Izoh" data-confirm-ok="O‘chirish" data-confirm-tone="primary">
    document.addEventListener(
        'submit',
        async (e) => {
            const form = e.target;
            if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) return;
            if (form.dataset.confirmed === '1') {
                delete form.dataset.confirmed;
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            const submitter = e.submitter && e.submitter.form === form ? e.submitter : null;
            const ok = await window.confirmAction({
                title: form.dataset.confirm,
                text: form.dataset.confirmText || '',
                ok: form.dataset.confirmOk || 'Tasdiqlash',
                tone: form.dataset.confirmTone || 'danger',
            });
            if (!ok) return;
            form.dataset.confirmed = '1';
            form.requestSubmit(submitter ?? undefined);
        },
        true,
    );

    /* ---------- Ulashish ---------- */
    window.sharePost = (url, text = '', id = null) => {
        // Ilovada mehmon (yoki post emas, profil) — telefonning o‘z "Ulashish" menyusi.
        if (window.fkNative && (!id || !document.body.dataset.auth)) {
            window.fkNative.share({ url, text: text || undefined }).catch(() => {});
            return;
        }
        if (document.querySelector('[data-share-sheet]')) {
            window.dispatchEvent(new CustomEvent('share-post', { detail: { url, text, id } }));
            return;
        }
        if (navigator.share) {
            navigator.share({ url, text }).catch(() => {});
            return;
        }
        copyText(url).then((ok) => toast(ok ? 'Havola nusxalandi.' : 'Nusxa olib bo‘lmadi.', ok ? 'success' : 'error'));
    };

    Alpine.data('shareSheet', () => {
        let searchTimer = null;
        let requestSeq = 0;
        return {
            open: false,
            post: null,
            q: '',
            users: [],
            selected: [],
            body: '',
            loading: false,
            sending: false,
            auth: Boolean(document.body.dataset.auth),
            canNative: Boolean(window.fkNative) || typeof navigator.share === 'function',
            show(post) {
                Object.assign(this, { post, q: '', selected: [], body: '', users: [], open: true });
                document.documentElement.classList.add('overlay-lock');
                if (this.auth && post.id) this.load();
            },
            close() {
                this.open = false;
                document.documentElement.classList.remove('overlay-lock');
            },
            onSearch() {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => this.load(), 250);
            },
            async load() {
                const q = this.q.trim().replace(/^@/, '');
                if (q.length === 1) return;
                const seq = ++requestSeq;
                this.loading = true;
                try {
                    const data = await api('GET', `/messages/recipients${q ? `?${new URLSearchParams({ q })}` : ''}`);
                    if (seq === requestSeq) this.users = data.users ?? [];
                } catch {
                    if (seq === requestSeq) this.users = [];
                } finally {
                    if (seq === requestSeq) this.loading = false;
                }
            },
            isSelected(u) {
                return this.selected.some((s) => s.id === u.id);
            },
            toggle(u) {
                if (!u.can) {
                    toast(u.reason || 'Bu foydalanuvchiga yozib bo‘lmaydi.', 'error');
                    return;
                }
                if (this.isSelected(u)) this.selected = this.selected.filter((s) => s.id !== u.id);
                else if (this.selected.length >= 10) toast('Bir martada ko‘pi bilan 10 kishiga.', 'error');
                else this.selected.push(u);
            },
            async send() {
                if (!this.selected.length || this.sending) return;
                this.sending = true;
                try {
                    const data = await api('POST', '/messages/share', { post_id: this.post.id, user_ids: this.selected.map((u) => u.id), body: this.body.trim() || null });
                    toast(data.message, 'success');
                    (data.failed ?? []).forEach((f) => toast(`${f.name}: ${f.reason}`, 'error'));
                    this.close();
                } catch (err) {
                    toast(err.message, 'error');
                } finally {
                    this.sending = false;
                }
            },
            async copyLink() {
                const ok = await copyText(this.post.url);
                toast(ok ? 'Havola nusxalandi.' : 'Nusxa olib bo‘lmadi.', ok ? 'success' : 'error');
                if (ok) this.close();
            },
            get telegramUrl() {
                return `https://t.me/share/url?${new URLSearchParams({ url: this.post?.url ?? '', text: this.post?.text ?? '' })}`;
            },
            async native() {
                try {
                    if (window.fkNative) await window.fkNative.share({ url: this.post.url, text: this.post.text || undefined });
                    else await navigator.share({ url: this.post.url, text: this.post.text || undefined });
                    this.close();
                } catch {
                    /* bekor qilindi */
                }
            },
        };
    });
}
