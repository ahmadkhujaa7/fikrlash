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
/* ---------- Post yozish ---------- */
const WORD = "[\\p{L}\\p{N}_‘’ʻʼ']";
// Kursor oldidagi "#so‘z" yoki "@user" — takliflar shu bo‘lak uchun chiqadi.
const TOKEN_RE = new RegExp(`(^|[\\s(])([#@])(${WORD}{0,50})$`, 'u');
const TAG_RE = new RegExp(`(^|[^\\p{L}\\p{N}_&/#])#(${WORD}{2,50})`, 'gu');
const TAG_NAME_RE = new RegExp(`^${WORD}{1,50}$`, 'u');

const draftStore = {
    get(key) {
        try {
            return JSON.parse(localStorage.getItem(key) ?? 'null');
        } catch {
            return null;
        }
    },
    set(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
        } catch {
            /* xususiy rejim yoki joy tugagan — qoralama shunchaki saqlanmaydi */
        }
    },
    remove(key) {
        try {
            localStorage.removeItem(key);
        } catch {
            /* e'tiborsiz */
        }
    },
};

const escapeHtml = (s) => s.replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch]);

/** Oldindan ko‘rish uchun: serverdagi ContentFormatter bilan bir xil ko‘rinish (avval escape, keyin havolalar). */
function formatPreview(text) {
    let html = escapeHtml(text.trim().replace(/\n{3,}/g, '\n\n'));
    html = html.replace(/https?:\/\/[^\s<>"]+/g, (url) => `<a class="link">${url.replace(/^https?:\/\/(www\.)?/, '').slice(0, 48)}</a>`);
    html = html.replace(/(^|[^\p{L}\p{N}_@/])@([A-Za-z0-9_]{3,30})/gu, '$1<a class="mention">@$2</a>');
    html = html.replace(new RegExp(`(^|[^\\p{L}\\p{N}_&/#;])#(${WORD}{2,50})`, 'gu'), '$1<a class="hashtag">#$2</a>');
    return html.replace(/\n/g, '<br>') || '<span class="text-muted">Matn yozilganda shu yerda ko‘rinadi.</span>';
}

Alpine.data('composer', (opts) => ({
    content: opts.content ?? '',
    initial: opts.content ?? '',
    max: opts.max,
    full: !!opts.full,
    tags: opts.tags ?? [],
    maxTags: opts.maxTags ?? 5,
    preview: null,
    imageInfo: '',
    expanded: !!opts.full || (opts.content ?? '').length > 0,
    showPreview: false,
    dragging: false,
    restored: false,
    submitting: false,
    savedLabel: '',
    ac: { open: false, items: [], index: 0, type: null, start: 0 },
    tagQuery: '',
    tagSuggest: { open: false, items: [], index: 0 },
    _timers: {},
    _abort: null,

    init() {
        if (opts.draftKey) {
            const saved = draftStore.get(opts.draftKey);
            if (saved?.content?.trim() && !this.content.trim()) {
                this.content = saved.content;
                this.tags = Array.isArray(saved.tags) ? saved.tags : this.tags;
                this.restored = true;
                this.expanded = true;
                this.$nextTick(() => this.grow(this.$refs.text));
            }
            this.$watch('content', () => this.scheduleSave());
            this.$watch('tags', () => this.scheduleSave());
        }
        if (this.full) {
            // Yozilgan matn tasodifan yo‘qolmasin.
            window.addEventListener('beforeunload', (e) => {
                if (!this.submitting && this.content.trim() !== this.initial.trim() && !opts.draftKey) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
        }
    },

    get left() {
        return this.max - [...this.content].length;
    },
    get tooLong() {
        return this.left < 0;
    },
    get progress() {
        return Math.min(1, [...this.content].length / this.max);
    },
    get words() {
        return (this.content.trim().match(/\S+/g) || []).length;
    },
    get readMinutes() {
        return Math.max(1, Math.round(this.words / 180));
    },
    get textTags() {
        const found = [];
        for (const m of this.content.matchAll(TAG_RE)) {
            const tag = m[2];
            if (!found.some((t) => t.toLowerCase() === tag.toLowerCase())) found.push(tag);
        }
        return found.slice(0, 10);
    },
    get previewHtml() {
        return formatPreview(this.content);
    },

    grow(el) {
        if (!el) return;
        el.style.height = 'auto';
        el.style.height = `${el.scrollHeight}px`;
    },
    togglePreview() {
        this.showPreview = !this.showPreview;
        if (!this.showPreview) this.$nextTick(() => this.$refs.text.focus());
    },
    onSubmit(e) {
        if (!this.content.trim() || this.tooLong || this.submitting) {
            e.preventDefault();
            return;
        }
        this.submitting = true;
        if (opts.draftKey) draftStore.remove(opts.draftKey);
    },

    /* --- Rasm: tanlash, sudrab tashlash, Ctrl+V --- */
    pick(event) {
        const file = event.target.files[0];
        if (file) this.useFile(file, false);
    },
    useFile(file, assign = true) {
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            toast('Faqat JPG, PNG yoki WEBP rasm qo‘shish mumkin.', 'error');
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            toast('Rasm 5 MB dan oshmasligi kerak.', 'error');
            if (this.$refs.image) this.$refs.image.value = '';
            return;
        }
        if (assign) {
            const dt = new DataTransfer();
            dt.items.add(file);
            this.$refs.image.files = dt.files;
        }
        this.expanded = true;
        this.preview = URL.createObjectURL(file);
        this.imageInfo = `${(file.size / 1024 / 1024).toFixed(1)} MB`;
    },
    drop(event) {
        this.dragging = false;
        const file = [...(event.dataTransfer?.files ?? [])].find((f) => f.type.startsWith('image/'));
        if (file) this.useFile(file);
    },
    paste(event) {
        const item = [...(event.clipboardData?.items ?? [])].find((i) => i.kind === 'file' && i.type.startsWith('image/'));
        if (item) {
            event.preventDefault();
            this.useFile(item.getAsFile());
        }
    },
    clearImage() {
        this.preview = null;
        this.imageInfo = '';
        this.$refs.image.value = '';
    },

    /* --- Qoralama (brauzerda) --- */
    scheduleSave() {
        clearTimeout(this._timers.save);
        this._timers.save = setTimeout(() => {
            if (this.submitting) return;
            if (this.content.trim()) {
                draftStore.set(opts.draftKey, { content: this.content, tags: this.tags, at: Date.now() });
                this.savedLabel = 'Qoralama saqlandi';
            } else {
                draftStore.remove(opts.draftKey);
                this.savedLabel = '';
            }
        }, 700);
    },
    discardDraft() {
        draftStore.remove(opts.draftKey);
        this.content = '';
        this.tags = [];
        this.restored = false;
        this.savedLabel = '';
        this.$nextTick(() => {
            this.grow(this.$refs.text);
            this.$refs.text.focus();
        });
    },

    /* --- # va @ takliflari matn ichida --- */
    onInput(event) {
        this.grow(event.target);
        this.restored = false;
        this.detectToken();
    },
    detectToken() {
        const el = this.$refs.text;
        if (!el || el.selectionStart !== el.selectionEnd) return this.closeAc();
        const match = el.value.slice(0, el.selectionStart).match(TOKEN_RE);
        if (!match) return this.closeAc();

        const type = match[2] === '#' ? 'tag' : 'user';
        const query = match[3];
        this.ac.type = type;
        this.ac.start = el.selectionStart - query.length - 1;
        clearTimeout(this._timers.ac);
        this._timers.ac = setTimeout(() => this.loadAc(type, query), 150);
    },
    async loadAc(type, query) {
        this._abort?.abort();
        const controller = (this._abort = new AbortController());
        try {
            const url = `${type === 'tag' ? opts.tagsUrl : opts.usersUrl}?${new URLSearchParams({ q: query })}`;
            const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: controller.signal });
            if (!res.ok) return;
            const data = await res.json();
            let items;
            if (type === 'user') {
                items = data.users.map((u) => ({ key: `u-${u.username}`, type: 'user', insert: `@${u.username}`, name: u.name, username: u.username, avatar: u.avatar_url, initials: u.initials, tone: u.tone, verified: u.verified }));
            } else {
                items = data.tags.map((t) => ({ key: `t-${t.slug}`, type: 'tag', insert: `#${t.name}`, name: t.name, count: t.posts_count }));
                if (data.can_create && query.length >= 2) items.push({ key: 'new', type: 'tag', insert: `#${query}`, name: query, isNew: true });
            }
            this.ac.items = items;
            this.ac.index = 0;
            this.ac.open = items.length > 0 && document.activeElement === this.$refs.text;
        } catch {
            /* bekor qilingan so‘rov */
        }
    },
    onKeydown(event) {
        if (!this.ac.open || event.ctrlKey || event.metaKey) return;
        const n = this.ac.items.length;
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            this.ac.index = (this.ac.index + 1) % n;
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            this.ac.index = (this.ac.index - 1 + n) % n;
        } else if (event.key === 'Enter' || event.key === 'Tab') {
            event.preventDefault();
            this.choose(this.ac.items[this.ac.index]);
        } else if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            this.closeAc();
        }
    },
    choose(item) {
        if (!item) return;
        const el = this.$refs.text;
        const before = this.content.slice(0, this.ac.start);
        const after = this.content.slice(el.selectionStart).replace(new RegExp(`^${WORD}*`, 'u'), '');
        const insert = `${item.insert} `;
        this.content = before + insert + after;
        this.closeAc();
        this.$nextTick(() => {
            const pos = before.length + insert.length;
            el.focus();
            el.setSelectionRange(pos, pos);
            this.grow(el);
        });
    },
    closeAc() {
        this.ac.open = false;
        this.ac.items = [];
    },
    insertSymbol(symbol) {
        this.expanded = true;
        this.showPreview = false;
        this.$nextTick(() => {
            const el = this.$refs.text;
            const start = el.selectionStart ?? this.content.length;
            const end = el.selectionEnd ?? start;
            const pad = start > 0 && !/\s/.test(this.content[start - 1]) ? ' ' : '';
            this.content = this.content.slice(0, start) + pad + symbol + this.content.slice(end);
            this.$nextTick(() => {
                const pos = start + pad.length + 1;
                el.focus();
                el.setSelectionRange(pos, pos);
                this.detectToken();
            });
        });
    },

    /* --- Teg chiplari --- */
    async loadTagSuggestions() {
        const query = this.tagQuery.replace(/^#+/, '').trim();
        try {
            const res = await fetch(`${opts.tagsUrl}?${new URLSearchParams({ q: query })}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!res.ok) return;
            const data = await res.json();
            const taken = this.tags.map((t) => t.toLowerCase());
            const items = data.tags.filter((t) => !taken.includes(t.name.toLowerCase())).map((t) => ({ key: t.slug, name: t.name, count: t.posts_count }));
            if (data.can_create && query && TAG_NAME_RE.test(query.replace(/\s+/g, '_'))) {
                items.unshift({ key: 'new', name: query.replace(/\s+/g, '_'), isNew: true });
            }
            this.tagSuggest = { open: items.length > 0 && document.activeElement === this.$refs.tagInput, items, index: 0 };
        } catch {
            /* e'tiborsiz */
        }
    },
    addTag(raw) {
        const name = String(raw ?? this.tagQuery).replace(/^#+/, '').trim().replace(/\s+/g, '_');
        if (!name) return;
        if (!TAG_NAME_RE.test(name)) {
            toast('Teg faqat harf, raqam va _ belgisidan iborat bo‘lishi mumkin.', 'error');
            return;
        }
        if (this.tags.length >= this.maxTags) {
            toast(`Ko‘pi bilan ${this.maxTags} ta teg qo‘shish mumkin.`, 'error');
            return;
        }
        if (!this.tags.some((t) => t.toLowerCase() === name.toLowerCase())) this.tags.push(name);
        this.tagQuery = '';
        this.tagSuggest.open = false;
        this.$nextTick(() => this.$refs.tagInput?.focus());
    },
    removeTag(index) {
        this.tags.splice(index, 1);
    },
    tagKeydown(event) {
        const s = this.tagSuggest;
        if (event.key === 'ArrowDown' && s.open) {
            event.preventDefault();
            s.index = (s.index + 1) % s.items.length;
        } else if (event.key === 'ArrowUp' && s.open) {
            event.preventDefault();
            s.index = (s.index - 1 + s.items.length) % s.items.length;
        } else if (event.key === 'Enter' || event.key === ',' || (event.key === ' ' && this.tagQuery.trim())) {
            event.preventDefault();
            this.addTag(s.open && s.items[s.index] && event.key === 'Enter' ? s.items[s.index].name : this.tagQuery);
        } else if (event.key === 'Backspace' && !this.tagQuery && this.tags.length) {
            this.tags.pop();
        } else if (event.key === 'Escape') {
            s.open = false;
        }
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
