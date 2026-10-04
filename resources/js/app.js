import Alpine from 'alpinejs';
import intersect from '@alpinejs/intersect';
import focus from '@alpinejs/focus';
import { api, fetchHtml, postForm } from './api';
import { initViewTracking, readTimer } from './views';

window.Alpine = Alpine;
Alpine.plugin(intersect);
Alpine.plugin(focus);

/* ---------- Toast xabarlar ---------- */
Alpine.store('toasts', {
    items: [],
    push(message, type = 'info') {
        const id = Date.now() + Math.random();
        this.items.push({ id, message, type });
        setTimeout(() => this.dismiss(id), 4000);
    },
    dismiss(id) {
        this.items = this.items.filter((t) => t.id !== id);
    },
});
const toast = (message, type) => Alpine.store('toasts').push(message, type);
window.toast = toast;

/* ---------- Like / Save (optimistik yangilash) ---------- */
Alpine.data('toggle', ({ active, count, url, onKey, offKey, countKey }) => ({
    active,
    count,
    busy: false,
    async flip() {
        if (this.busy) return;
        this.busy = true;
        const prev = { active: this.active, count: this.count };
        this.active = !this.active;
        this.count += this.active ? 1 : -1;
        // Faollashganda ikonka "tepadi" (CSS .pop) — harakat foydalanuvchi bosganiga javob.
        const icon = this.$el.querySelector('svg');
        if (this.active && icon) {
            icon.classList.remove('pop');
            void icon.getBoundingClientRect();
            icon.classList.add('pop');
        }
        try {
            const data = await api(this.active ? 'POST' : 'DELETE', url);
            this.active = data[onKey] ?? this.active;
            if (countKey && data[countKey] !== undefined) this.count = data[countKey];
        } catch (e) {
            Object.assign(this, prev);
            toast(e.message, 'error');
        } finally {
            this.busy = false;
        }
    },
}));

/* ---------- Obuna (follow) ---------- */
Alpine.data('follow', ({ following, url }) => ({
    following,
    busy: false,
    hover: false,
    async flip() {
        if (this.busy) return;
        this.busy = true;
        try {
            const data = await api(this.following ? 'DELETE' : 'POST', url);
            this.following = data.following;
            this.$dispatch('follow-changed', data);
        } catch (e) {
            toast(e.message, 'error');
        } finally {
            this.busy = false;
        }
    },
}));

/* ---------- Infinite scroll: keyingi sahifa HTML'ini qo‘shadi ---------- */
Alpine.data('infinite', (next) => ({
    next,
    loading: false,
    failed: false,
    async load() {
        if (!this.next || this.loading) return;
        this.loading = true;
        this.failed = false;
        try {
            const html = await fetchHtml(this.next);
            const tpl = document.createElement('template');
            tpl.innerHTML = html;
            const page = tpl.content.querySelector('[data-page]');
            this.next = page?.dataset.next || null;
            page?.querySelectorAll(':scope > [data-item]').forEach((el) => this.$refs.list.appendChild(el));
            initViewTracking(this.$refs.list);
        } catch {
            this.failed = true;
        } finally {
            this.loading = false;
        }
    },
}));

/* ---------- Post yozish formasi ---------- */
Alpine.data('composer', ({ max, content = '' }) => ({
    content,
    max,
    preview: null,
    expanded: content.length > 0,
    get left() {
        return this.max - [...this.content].length;
    },
    get tooLong() {
        return this.left < 0;
    },
    grow(el) {
        el.style.height = 'auto';
        el.style.height = `${el.scrollHeight}px`;
    },
    pick(event) {
        const file = event.target.files[0];
        if (!file) return;
        if (file.size > 5 * 1024 * 1024) {
            toast('Rasm 5 MB dan oshmasligi kerak.', 'error');
            event.target.value = '';
            return;
        }
        this.preview = URL.createObjectURL(file);
    },
    clearImage() {
        this.preview = null;
        this.$refs.image.value = '';
    },
}));

/* ---------- Izohlar ---------- */
Alpine.data('comments', ({ url }) => ({
    url,
    loaded: false,
    replyTo: null,
    replyName: '',
    content: '',
    sending: false,
    async init() {
        const html = await fetchHtml(this.url).catch(() => null);
        if (html !== null) this.$refs.thread.innerHTML = html;
        this.loaded = true;
        if (location.hash.startsWith('#comment-')) {
            this.$nextTick(() => document.querySelector(location.hash)?.scrollIntoView({ block: 'center' }));
        }
    },
    reply(id, username) {
        this.replyTo = id;
        this.replyName = username;
        this.content = this.content || `@${username} `;
        this.$refs.input.focus();
    },
    cancelReply() {
        this.replyTo = null;
        this.replyName = '';
    },
    async submit(form) {
        if (this.sending || !this.content.trim()) return;
        this.sending = true;
        try {
            const data = await postForm(form);
            const tpl = document.createElement('template');
            tpl.innerHTML = data.html.trim();
            const node = tpl.content.firstElementChild;
            const target = data.parent_id
                ? document.querySelector(`#comment-${data.parent_id} [data-replies]`)
                : this.$refs.thread.querySelector('[data-page]') ?? this.$refs.thread;
            this.$refs.thread.querySelector('[data-empty]')?.remove();
            target?.appendChild(node);
            node.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            this.$dispatch('comments-count', data.comments_count);
            this.content = '';
            this.cancelReply();
        } catch (e) {
            toast(e.message, 'error');
        } finally {
            this.sending = false;
        }
    },
    async remove(id, url) {
        if (!confirm('Izohni o‘chirasizmi?')) return;
        try {
            await api('DELETE', url);
            document.getElementById(`comment-${id}`)?.remove();
            toast('Izoh o‘chirildi.');
        } catch (e) {
            toast(e.message, 'error');
        }
    },
}));

/* ---------- Shikoyat (report) oynasi ---------- */
Alpine.data('reporter', () => ({
    open: false,
    type: null,
    id: null,
    reason: '',
    description: '',
    sending: false,
    show({ type, id }) {
        Object.assign(this, { open: true, type, id, reason: '', description: '' });
    },
    async send() {
        if (!this.reason) return toast('Sababni tanlang.', 'error');
        this.sending = true;
        try {
            await api('POST', '/api/v1/reports', { type: this.type, id: this.id, reason: this.reason, description: this.description || null });
            this.open = false;
            toast('Rahmat! Xabaringiz moderatorlarga yuborildi.', 'success');
        } catch (e) {
            toast(e.message, 'error');
        } finally {
            this.sending = false;
        }
    },
}));

/* ---------- Ulashish ---------- */
window.sharePost = async (url, text) => {
    if (navigator.share) {
        try {
            await navigator.share({ url, text });
        } catch {
            /* foydalanuvchi bekor qildi */
        }
        return;
    }
    await navigator.clipboard.writeText(url);
    toast('Havola nusxalandi.', 'success');
};

/* ---------- Kategoriyaga obuna ---------- */
/* ---------- Real vaqtdagi qidiruv (qidiruv sahifasi) ---------- */
Alpine.data('liveSearch', ({ url, q, type }) => ({
    q,
    type,
    loading: false,
    controller: null,
    lastKey: `${q.trim()}|${type}`,
    init() {
        // Orqaga/oldinga tugmalari — URL'dagi so‘rov qayta tiklanadi.
        window.addEventListener('popstate', () => {
            const params = new URLSearchParams(location.search);
            this.q = params.get('q') ?? '';
            this.type = params.get('type') ?? 'all';
            this.run(true, false);
        });
    },
    async run(force = false, push = true) {
        const q = this.q.trim();
        const key = `${q}|${this.type}`;
        if (!force && key === this.lastKey) return;
        this.lastKey = key;

        // "#teg" yuborilsa (Enter) — teg sahifasiga o‘tamiz.
        if (force && push && /^#[\p{L}\p{N}_]+$/u.test(q)) {
            window.location.href = `/search?q=${encodeURIComponent(q)}`;
            return;
        }

        this.controller?.abort();
        const controller = (this.controller = new AbortController());
        this.loading = true;
        const params = new URLSearchParams(q ? { q, type: this.type } : {});

        try {
            const res = await fetch(`${url}?${params}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                credentials: 'same-origin',
                signal: controller.signal,
            });
            if (!res.ok) throw new Error(res.status === 429 ? 'Juda tez qidiryapsiz — bir oz kuting.' : 'Qidiruvda xato yuz berdi.');
            this.$refs.results.innerHTML = await res.text();
            initViewTracking(this.$refs.results);
            const target = q ? `/search?${params}` : '/search';
            if (push && target !== location.pathname + location.search) history.replaceState(null, '', target);
            document.title = (q ? `“${q}” — qidiruv` : 'Qidiruv') + ' — Fikrlash.uz';
        } catch (e) {
            if (e.name !== 'AbortError') toast(e.message, 'error');
        } finally {
            if (this.controller === controller) this.loading = false;
        }
    },
    setType(type) {
        this.type = type;
        this.run(true);
    },
    clear() {
        this.q = '';
        this.run(true);
        this.$refs.input.focus();
    },
}));

/* ---------- Sarlavhadagi tezkor qidiruv (takliflar ro‘yxati) ---------- */
Alpine.data('quickSearch', (url) => ({
    q: '',
    html: '',
    open: false,
    loading: false,
    controller: null,
    async suggest() {
        const q = this.q.trim();
        if (q.length < 2) {
            this.open = false;
            this.html = '';
            return;
        }
        this.controller?.abort();
        const controller = (this.controller = new AbortController());
        this.loading = true;
        try {
            const res = await fetch(`${url}?${new URLSearchParams({ q })}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                credentials: 'same-origin',
                signal: controller.signal,
            });
            if (!res.ok) return;
            this.html = await res.text();
            this.open = this.$root.contains(document.activeElement);
        } catch (e) {
            // Bekor qilingan so‘rov yoki tarmoq xatosi — takliflar shunchaki chiqmaydi.
        } finally {
            if (this.controller === controller) this.loading = false;
        }
    },
    move(step) {
        const items = [...this.$root.querySelectorAll('[data-suggest]')];
        if (!items.length) return;
        this.open = true;
        const index = items.indexOf(document.activeElement);
        const next = index + step;
        if (next < 0) {
            this.$refs.q.focus();
            return;
        }
        items[Math.min(next, items.length - 1)].focus();
    },
    close() {
        if (!this.open) return;
        // Avval maydonga fokus (u @focus'da ro‘yxatni ochadi), keyin yopamiz.
        if (this.$root.contains(document.activeElement)) this.$refs.q.focus();
        this.open = false;
    },
}));

/* ---------- "Qiziq emas" — post yashiriladi, algoritm shunga o‘xshash postlarni kamroq ko‘rsatadi ---------- */
Alpine.data('dismissable', (url) => ({
    dismissed: false,
    async dismiss() {
        try {
            await api('POST', url);
            this.dismissed = true;
        } catch (e) {
            toast(e.message, 'error');
        }
    },
}));

/* ---------- O‘qilmagan bildirishnomalar (har 60 soniyada) ---------- */
Alpine.data('unreadBadge', (initial) => ({
    count: initial,
    init() {
        if (!document.body.dataset.auth) return;
        setInterval(async () => {
            if (document.hidden) return;
            try {
                this.count = (await api('GET', '/api/v1/notifications/unread-count')).unread;
            } catch {
                /* jim */
            }
        }, 60000);
    },
}));

Alpine.data('readTimer', readTimer);

document.addEventListener('DOMContentLoaded', () => {
    initViewTracking(document);
    const flash = document.body.dataset.toast;
    if (flash) toast(flash, 'success');
});

Alpine.start();
