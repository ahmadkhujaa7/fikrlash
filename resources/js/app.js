import Alpine from 'alpinejs';
import intersect from '@alpinejs/intersect';
import focus from '@alpinejs/focus';
import { api, fetchHtml, postForm } from './api';
import { initViewTracking, readTimer } from './views';
import { captureFeed, rememberFeedChunk, restoreFeedPosition } from './navigation';
import { registerBadges, registerInbox, registerThread } from './chat';

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
    _sync: null,
    // Bir xil post sahifada bir necha joyda bo‘lishi mumkin — holat hammasida bir xil bo‘lsin.
    init() {
        this._sync = (e) => {
            if (e.detail.url !== url || e.detail.origin === this) return;
            this.active = e.detail.active;
            if (countKey) this.count = e.detail.count;
        };
        window.addEventListener('post-toggle', this._sync);
    },
    destroy() {
        window.removeEventListener('post-toggle', this._sync);
    },
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
            window.dispatchEvent(new CustomEvent('post-toggle', { detail: { url, active: this.active, count: this.count, origin: this } }));
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
    init() {
        // Orqaga qaytilganda lenta saqlangan holatdan tiklangan bo‘lsa — keyingi sahifa manzili ham o‘shandan.
        if (this.$el.hasAttribute('data-feed')) this.next = this.$el.dataset.next || null;
    },
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
            const items = [...(page?.querySelectorAll(':scope > [data-item]') ?? [])];
            rememberFeedChunk(this.$root, items.map((el) => el.outerHTML).join(''), this.next);
            items.forEach((el) => this.$refs.list.appendChild(el));
            initViewTracking(this.$refs.list);
        } catch {
            this.failed = true;
        } finally {
            this.loading = false;
        }
    },
}));

/* ---------- Post yozish formasi ---------- */
/* ---------- Ro‘yxatdan o‘tish: 1-bosqich (ma'lumotlar) ---------- */
const LATIN = { 'sh': 'sh', 'ch': 'ch', 'o‘': 'o', 'g‘': 'g', 'oʻ': 'o', 'gʻ': 'g', "o'": 'o', "g'": 'g' };

Alpine.data('registerForm', ({ checkUrl, usernameTouched }) => ({
    usernameTouched,
    password: '',
    showPassword: false,
    terms: false,
    submitting: false,
    phoneTaken: false,
    status: { username: null, phone: null },
    messages: { username: '', phone: '' },
    _timers: {},

    init() {
        if (this.$refs.username.value) this.check('username', this.$refs.username.value);
        const phone = document.getElementById('phone');
        if (phone?.value) this.check('phone', phone.value);
    },
    get strength() {
        const p = this.password;
        if (!p) return 0;
        let s = p.length >= 8 ? 1 : 0;
        if (s && /[a-zA-Z]/.test(p) && /\d/.test(p)) s++;
        if (s > 1 && p.length >= 12) s++;
        if (s > 1 && (/[^a-zA-Z0-9]/.test(p) || (/[a-z]/.test(p) && /[A-Z]/.test(p)))) s++;
        return Math.max(1, Math.min(4, s));
    },
    get strengthLabel() {
        if (!this.password) return 'Kamida 8 belgi: harf va raqam bo‘lsin.';
        return ['', 'Juda oddiy — kamida 8 belgi, harf va raqam', 'Yaxshi', 'Kuchli', 'Juda kuchli'][this.strength];
    },
    /** Ismdan username taklif qilish (foydalanuvchi o‘zi yozmaguncha). */
    suggestUsername(name) {
        if (this.usernameTouched) return;
        let s = name.toLowerCase();
        for (const [from, to] of Object.entries(LATIN)) s = s.split(from).join(to);
        s = s.normalize('NFKD').replace(/[^a-z0-9\s_]/g, '').trim().replace(/\s+/g, '_').slice(0, 30);
        this.$refs.username.value = s;
        if (s.length >= 3) this.check('username', s);
    },
    formatPhone(value) {
        let d = value.replace(/\D/g, '');
        if (d.startsWith('998') && d.length > 9) d = d.slice(3);
        d = d.slice(0, 9);
        return [d.slice(0, 2), d.slice(2, 5), d.slice(5, 7), d.slice(7, 9)].filter(Boolean).join(' ');
    },
    check(field, value) {
        clearTimeout(this._timers[field]);
        const raw = field === 'phone' ? value.replace(/\D/g, '') : value;
        if ((field === 'username' && raw.length < 3) || (field === 'phone' && raw.length < 9)) {
            this.status[field] = null;
            this.messages[field] = '';
            this.phoneTaken = false;
            return;
        }
        this.status[field] = 'checking';
        this._timers[field] = setTimeout(async () => {
            try {
                const res = await fetch(`${checkUrl}?${new URLSearchParams({ field, value })}`, { headers: { Accept: 'application/json' } });
                const data = await res.json();
                this.status[field] = data.ok ? 'ok' : 'bad';
                this.messages[field] = data.message ?? '';
                if (field === 'phone') this.phoneTaken = !!data.login;
            } catch {
                this.status[field] = null;
            }
        }, 350);
    },
}));

/* ---------- Ro‘yxatdan o‘tish: 2-bosqich (SMS kod, 6 katak) ---------- */
Alpine.data('otpInput', () => ({
    digits: ['', '', '', '', '', ''],
    submitting: false,
    get code() {
        return this.digits.join('');
    },
    init() {
        this.$nextTick(() => this.focus(0));
    },
    focus(i) {
        this.$root.querySelector(`[data-otp="${Math.max(0, Math.min(5, i))}"]`)?.focus();
    },
    fill(from, text) {
        const chars = text.replace(/\D/g, '').slice(0, 6 - from).split('');
        chars.forEach((ch, k) => (this.digits[from + k] = ch));
        this.focus(from + chars.length);
        this.autoSubmit();
    },
    onInput(i, event) {
        const value = event.target.value.replace(/\D/g, '');
        // SMS'dan avtomatik to‘ldirish yoki tez yozishda bir katakka bir nechta raqam tushadi — taqsimlaymiz.
        if (value.length > 1) {
            this.digits[i] = '';
            this.fill(i, value);
            return;
        }
        this.digits[i] = value;
        if (value) this.focus(i + 1);
        this.autoSubmit();
    },
    onBackspace(i, event) {
        if (!this.digits[i] && i > 0) {
            event.preventDefault();
            this.digits[i - 1] = '';
            this.focus(i - 1);
        }
    },
    paste(event) {
        this.fill(0, event.clipboardData?.getData('text') ?? '');
    },
    autoSubmit() {
        if (this.code.length === 6 && !this.submitting) {
            this.$nextTick(() => this.$root.requestSubmit());
        }
    },
}));

/* ---------- Post yozish ---------- */
const WORD = "[\\p{L}\\p{N}_‘’ʻʼ']";
// Kursor oldidagi "#so‘z" yoki "@user" — takliflar shu bo‘lak uchun chiqadi.
const TOKEN_RE = new RegExp(`(^|[\\s(])([#@])(${WORD}{0,50})$`, 'u');
const TAG_RE = new RegExp(`(^|[^\\p{L}\\p{N}_&/#])#(${WORD}{2,50})`, 'gu');
const TAG_NAME_RE = new RegExp(`^${WORD}{1,50}$`, 'u');

/** Mobil klaviatura balandligi (px): asboblar paneli klaviatura ustida tursin (iOS va Android). */
function watchKeyboard(callback) {
    const vv = window.visualViewport;
    if (!vv) return;
    const update = () => callback(Math.max(0, Math.round(window.innerHeight - vv.height - vv.offsetTop)));
    vv.addEventListener('resize', update);
    vv.addEventListener('scroll', update);
}

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

/**
 * Teg chiplari (qisqa fikr va maqola uchun umumiy): mavjud tegni tanlash yoki yangisini yaratish.
 * Faqat oddiy xossa va metodlar — obyekt ichiga "...tagChips(opts)" bilan qo‘shiladi.
 */
const tagChips = (opts) => ({
    tags: opts.tags ?? [],
    maxTags: opts.maxTags ?? 5,
    tagQuery: '',
    tagSuggest: { open: false, items: [], index: 0 },

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
});

Alpine.data('composer', (opts) => ({
    content: opts.content ?? '',
    initial: opts.content ?? '',
    max: opts.max,
    full: !!opts.full,
    ...tagChips(opts),
    preview: null,
    imageInfo: '',
    expanded: !!opts.full || (opts.content ?? '').length > 0,
    showPreview: false,
    dragging: false,
    restored: false,
    submitting: false,
    savedLabel: '',
    kb: 0,
    ac: { open: false, items: [], index: 0, type: null, start: 0 },
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
            watchKeyboard((kb) => (this.kb = kb));
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
}));

/* ---------- Maqola muharriri ---------- */
/*
 * Maqola = sarlavha + bloklar. Har bir matn bloki — o‘zi kattalashadigan textarea
 * (contenteditable emas: mobil klaviaturalar, avtomatik tuzatish va nusxa-qo‘yishda ishonchli).
 *   Enter — yangi paragraf, Shift+Enter — qator, Backspace boshida — oldingisi bilan qo‘shish;
 *   "## " — sarlavha, "> " — iqtibos, "- " / "1. " — ro‘yxat, "---" — ajratgich;
 *   Ctrl+B / Ctrl+I — qalin / kursiv; rasm — tugma, sudrab tashlash yoki Ctrl+V.
 */
const uid = () => Math.random().toString(36).slice(2, 10);
const TEXT_BLOCKS = ['p', 'h', 'quote'];
const MD_SHORTCUT = /^(#{1,3}|>|[-*•]|1[.)])\s/;

/** Preview uchun: serverdagi ContentFormatter::toHtml($text, rich: true) bilan bir xil natija. */
function richText(text) {
    let html = escapeHtml(text.trim());
    html = html.replace(/https?:\/\/[^\s<>"]+/g, (url) => `<a class="link">${url.replace(/^https?:\/\/(www\.)?/, '').slice(0, 48)}</a>`);
    html = html.replace(/(^|[^\p{L}\p{N}_@/])@([A-Za-z0-9_]{3,30})/gu, '$1<a class="mention">@$2</a>');
    html = html.replace(new RegExp(`(^|[^\\p{L}\\p{N}_&/#;])#(${WORD}{2,50})`, 'gu'), '$1<a class="hashtag">#$2</a>');
    html = html.replace(/\*\*(?=\S)(.+?)(?<=\S)\*\*/gu, '<strong>$1</strong>');
    html = html.replace(/(?<![*\p{L}\p{N}])\*(?=[^\s*])([^*\n]*?[^\s*])\*(?![*\p{L}\p{N}])/gu, '<em>$1</em>');
    return html.replace(/\n/g, '<br>');
}

function hydrateBlocks(blocks) {
    return (Array.isArray(blocks) ? blocks : []).map((b) => {
        if (b.type === 'list') return { id: uid(), type: 'list', ordered: !!b.ordered, items: (b.items?.length ? b.items : ['']).map((text) => ({ id: uid(), text: String(text) })) };
        if (b.type === 'image') return { id: uid(), type: 'image', path: b.path, url: b.url, caption: b.caption ?? '', w: b.w ?? null, h: b.h ?? null, uploading: false, progress: 1 };
        if (b.type === 'hr') return { id: uid(), type: 'hr' };
        return { id: uid(), type: TEXT_BLOCKS.includes(b.type) ? b.type : 'p', text: String(b.text ?? '') };
    });
}

Alpine.data('articleEditor', (opts) => {
    // Forma elementi: o‘chirilgan blok ichidan chaqirilgan metodlarda $root aniqlanmaydi — shuning uchun oldindan saqlanadi.
    let root = null;

    return {
        ...tagChips(opts),
        title: opts.title ?? '',
        blocks: hydrateBlocks(opts.blocks),
        focused: 0,
        focusedItem: null,
        sel: { start: 0, end: 0 },
        showPreview: false,
        dragging: false,
        restored: false,
        submitting: false,
        asDraft: false,
        savedLabel: '',
        kb: 0,
        flash: null,
        _initial: '',
        _timers: {},

        init() {
            root = this.$root;
            if (!this.blocks.length) this.blocks = [this.newBlock('p')];

            if (opts.draftKey) {
                const saved = draftStore.get(opts.draftKey);
                if (saved && !this.hasContent && (saved.title?.trim() || saved.blocks?.length)) {
                    this.title = saved.title ?? '';
                    this.blocks = hydrateBlocks(saved.blocks);
                    if (!this.blocks.length) this.blocks = [this.newBlock('p')];
                    this.tags = Array.isArray(saved.tags) ? saved.tags : this.tags;
                    this.restored = true;
                }
                this.$watch('title', () => this.scheduleSave());
                this.$watch('blocks', () => this.scheduleSave());
                this.$watch('tags', () => this.scheduleSave());
            }

            this._initial = this.serialized + this.title;
            window.addEventListener('beforeunload', (e) => {
                if (this.uploading || (!opts.draftKey && !this.submitting && this.serialized + this.title !== this._initial)) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });

            watchKeyboard((kb) => (this.kb = kb));

            this.$nextTick(() => {
                root.querySelectorAll('textarea[data-grow]').forEach((el) => this.grow(el));
                if (!this.title.trim()) this.$refs.title?.focus();
            });
        },

        /* --- Holat --- */
        newBlock(type, extra = {}) {
            if (type === 'list') return { id: uid(), type, ordered: false, items: [{ id: uid(), text: '' }], ...extra };
            if (type === 'hr') return { id: uid(), type };
            return { id: uid(), type, text: '', ...extra };
        },
        isText(b) {
            return b && TEXT_BLOCKS.includes(b.type);
        },
        findBlock(id) {
            return this.blocks.find((b) => b.id === id);
        },
        placeholder(b, i) {
            if (b.type === 'h') return 'Kichik sarlavha';
            if (b.type === 'quote') return 'Iqtibos yoki muhim fikr';
            if (this.blocks.length === 1) return 'Yozishni boshlang… Rasm, kichik sarlavha va ro‘yxat — pastdagi paneldan.';
            return i === this.focused ? 'Davom eting…' : '';
        },
        get clean() {
            const out = [];
            for (const b of this.blocks) {
                if (this.isText(b) && b.text.trim()) out.push({ type: b.type, text: b.text.trim() });
                else if (b.type === 'list') {
                    const items = b.items.map((i) => i.text.trim()).filter(Boolean);
                    if (items.length) out.push({ type: 'list', ordered: b.ordered, items });
                } else if (b.type === 'image' && b.path) out.push({ type: 'image', path: b.path, url: b.url, caption: b.caption.trim(), w: b.w, h: b.h });
                else if (b.type === 'hr') out.push({ type: 'hr' });
            }
            return out;
        },
        get serialized() {
            return JSON.stringify(this.clean.map(({ url, ...b }) => b));
        },
        get bodyText() {
            return this.clean
                .map((b) => (b.type === 'list' ? b.items.join('\n') : b.type === 'image' ? b.caption : (b.text ?? '')))
                .filter(Boolean)
                .join('\n\n');
        },
        get hasContent() {
            return this.clean.some((b) => b.type !== 'hr');
        },
        get chars() {
            return [...`${this.title}\n\n${this.bodyText}`].length;
        },
        get words() {
            return (`${this.title} ${this.bodyText}`.trim().match(/\S+/g) || []).length;
        },
        get readMinutes() {
            return Math.max(1, Math.round(this.words / 200));
        },
        get uploading() {
            return this.blocks.some((b) => b.type === 'image' && b.uploading);
        },
        get tooLong() {
            return this.chars > opts.max;
        },
        get canSubmit() {
            return this.title.trim().length >= 3 && this.hasContent && !this.uploading && !this.tooLong && !this.submitting;
        },
        get textTags() {
            const found = [];
            for (const m of this.bodyText.matchAll(TAG_RE)) {
                if (!found.some((t) => t.toLowerCase() === m[2].toLowerCase())) found.push(m[2]);
            }
            return found.slice(0, 10);
        },
        get previewHtml() {
            let html = '';
            for (const b of this.clean) {
                if (b.type === 'p') html += `<p>${richText(b.text)}</p>`;
                else if (b.type === 'h') html += `<h2>${escapeHtml(b.text)}</h2>`;
                else if (b.type === 'quote') html += `<blockquote><p>${richText(b.text)}</p></blockquote>`;
                else if (b.type === 'list') {
                    const tag = b.ordered ? 'ol' : 'ul';
                    html += `<${tag}>${b.items.map((i) => `<li>${richText(i)}</li>`).join('')}</${tag}>`;
                } else if (b.type === 'image') {
                    html += `<figure><img src="${escapeHtml(b.url ?? '')}" alt="${escapeHtml(b.caption)}">${b.caption ? `<figcaption>${escapeHtml(b.caption)}</figcaption>` : ''}</figure>`;
                } else if (b.type === 'hr') html += '<hr>';
            }
            return html || '<p class="text-muted">Matn yozilganda shu yerda ko‘rinadi.</p>';
        },
        get blockedReason() {
            if (this.uploading) return 'Rasm yuklanmoqda…';
            if (this.title.trim().length < 3) return 'Sarlavha yozing';
            if (!this.hasContent) return 'Matn yozing';
            if (this.tooLong) return `Maqola ${opts.max} belgidan oshmasin`;
            return '';
        },

        /* --- Fokus va kursor --- */
        el(i, j = null) {
            return root.querySelector(j === null || j === undefined ? `[data-idx="${i}"]:not([data-item])` : `[data-idx="${i}"][data-item="${j}"]`);
        },
        focusAt(i, pos = 'end', j = null) {
            this.$nextTick(() => {
                const el = this.el(i, j);
                if (!el) return;
                el.focus({ preventScroll: false });
                const p = pos === 'start' ? 0 : pos === 'end' ? el.value.length : Math.min(pos, el.value.length);
                el.setSelectionRange(p, p);
                this.grow(el);
                this.focused = i;
                this.focusedItem = j;
                this.sel = { start: p, end: p };
            });
        },
        focusNearest(i, pos, dir) {
            for (let k = i; k >= 0 && k < this.blocks.length; k += dir) {
                const b = this.blocks[k];
                if (this.isText(b)) return this.focusAt(k, pos);
                if (b.type === 'list') return this.focusAt(k, pos, pos === 'start' ? 0 : b.items.length - 1);
            }
            if (dir < 0) this.$refs.title?.focus();
        },
        track(event, i, j = null) {
            this.focused = i;
            this.focusedItem = j;
            this.sel = { start: event.target.selectionStart ?? 0, end: event.target.selectionEnd ?? 0 };
        },
        grow(el) {
            if (!el) return;
            el.style.height = 'auto';
            el.style.height = `${el.scrollHeight}px`;
        },
        titleKey(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                this.focusNearest(0, 'start', 1);
            }
        },

        /* --- Matn bloklari: klaviatura --- */
        hotkeys(event) {
            if (!(event.ctrlKey || event.metaKey)) return false;
            const key = event.key.toLowerCase();
            if (key === 'b' || key === 'i') {
                event.preventDefault();
                this.track(event, this.focused, this.focusedItem);
                this.wrap(key === 'b' ? '**' : '*');
                return true;
            }
            if (key === 'enter') {
                event.preventDefault();
                this.publish();
                return true;
            }
            return false;
        },
        onKey(event, i) {
            if (this.hotkeys(event)) return;
            const b = this.blocks[i];
            const el = event.target;

            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
                event.preventDefault();
                if (b.type === 'p' && b.text.trim() === '---') {
                    this.blocks.splice(i, 1, this.newBlock('hr'), this.newBlock('p'));
                    return this.focusAt(i + 1, 'start');
                }
                if (b.type === 'quote' && !b.text.trim()) {
                    b.type = 'p';
                    return;
                }
                const before = b.text.slice(0, el.selectionStart);
                const after = b.text.slice(el.selectionEnd);
                b.text = before.replace(/\s+$/, '');
                this.blocks.splice(i + 1, 0, this.newBlock('p', { text: after.replace(/^\s+/, '') }));
                return this.focusAt(i + 1, 'start');
            }

            if (event.key === 'Backspace' && el.selectionStart === 0 && el.selectionEnd === 0) {
                if (b.type !== 'p') {
                    event.preventDefault();
                    b.type = 'p';
                    return this.$nextTick(() => this.grow(this.el(i)));
                }
                if (i === 0) {
                    if (!b.text && this.blocks.length > 1) {
                        event.preventDefault();
                        this.blocks.splice(0, 1);
                        this.focusNearest(0, 'start', 1);
                    } else if (!b.text) {
                        event.preventDefault();
                        this.$refs.title?.focus();
                    }
                    return;
                }
                event.preventDefault();
                const prev = this.blocks[i - 1];
                if (this.isText(prev)) {
                    const pos = prev.text.length;
                    prev.text += b.text;
                    this.blocks.splice(i, 1);
                    return this.focusAt(i - 1, pos);
                }
                if (prev.type === 'list') {
                    const last = prev.items[prev.items.length - 1];
                    const pos = last.text.length;
                    last.text += b.text;
                    this.blocks.splice(i, 1);
                    return this.focusAt(i - 1, pos, prev.items.length - 1);
                }
                if (!b.text) {
                    this.blocks.splice(i, 1);
                    return this.focusNearest(i - 1, 'end', -1);
                }
                return this.highlight(prev.id);
            }

            if (event.key === 'ArrowUp' && el.selectionStart === 0 && el.selectionEnd === 0 && i > 0) {
                event.preventDefault();
                return this.focusNearest(i - 1, 'end', -1);
            }
            if (event.key === 'ArrowDown' && el.selectionEnd === el.value.length && i < this.blocks.length - 1) {
                event.preventDefault();
                return this.focusNearest(i + 1, 'start', 1);
            }
        },
        onInput(event, i) {
            const b = this.blocks[i];
            this.grow(event.target);
            this.restored = false;
            this.track(event, i);
            if (b.type !== 'p') return;

            // Markdown qisqartmalari: "## ", "> ", "- ", "1. "
            const m = b.text.match(MD_SHORTCUT);
            if (!m) return;
            const rest = b.text.slice(m[0].length);
            const el = event.target;
            const caret = Math.max(0, el.selectionStart - m[0].length);
            if (m[1].startsWith('#') || m[1] === '>') {
                // Shu textarea'ning o‘zi qoladi — kursor darhol (keyingi tugma bosilishidan oldin) joyiga qo‘yiladi.
                b.type = m[1] === '>' ? 'quote' : 'h';
                b.text = rest;
                el.value = rest;
                el.setSelectionRange(caret, caret);
                this.sel = { start: caret, end: caret };
                return;
            }
            this.blocks.splice(i, 1, { id: uid(), type: 'list', ordered: /\d/.test(m[1]), items: [{ id: uid(), text: rest }] });
            this.focusAt(i, caret, 0);
        },
        onPaste(event, i, j = null) {
            const data = event.clipboardData;
            const files = [...(data?.files ?? [])].filter((f) => f.type.startsWith('image/'));
            if (files.length) {
                event.preventDefault();
                files.slice(0, 10).forEach((f, n) => this.upload(f, i + n));
                return;
            }
            // Bir necha paragrafli matn — har biri alohida blok bo‘lib qo‘yiladi.
            const text = (data?.getData('text/plain') ?? '').replace(/\r\n?/g, '\n');
            if (j !== null || !/\n\s*\n/.test(text)) return;
            event.preventDefault();
            const b = this.blocks[i];
            const el = event.target;
            const before = b.text.slice(0, el.selectionStart);
            const after = b.text.slice(el.selectionEnd);
            const parts = text.split(/\n\s*\n/).map((t) => t.trim()).filter(Boolean);
            if (!parts.length) return;
            b.text = before + parts[0];
            const rest = parts.slice(1).map((t) => this.newBlock('p', { text: t }));
            const lastIndex = i + rest.length;
            if (rest.length) {
                rest[rest.length - 1].text += after;
                this.blocks.splice(i + 1, 0, ...rest);
                this.focusAt(lastIndex, rest[rest.length - 1].text.length - after.length);
            } else {
                b.text += after;
                this.focusAt(i, b.text.length - after.length);
            }
            this.$nextTick(() => root.querySelectorAll('textarea[data-grow]').forEach((t) => this.grow(t)));
        },

        /* --- Ro‘yxat bandlari --- */
        onItemKey(event, i, j) {
            if (this.hotkeys(event)) return;
            const b = this.blocks[i];
            const item = b.items[j];
            const el = event.target;

            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
                event.preventDefault();
                if (!item.text.trim()) {
                    // Bo‘sh band + Enter — ro‘yxatdan chiqish.
                    b.items.splice(j, 1);
                    const tail = b.items.splice(j);
                    const insert = [this.newBlock('p')];
                    if (tail.length) insert.push({ id: uid(), type: 'list', ordered: b.ordered, items: tail });
                    if (!b.items.length) {
                        this.blocks.splice(i, 1, ...insert);
                        return this.focusAt(i, 'start');
                    }
                    this.blocks.splice(i + 1, 0, ...insert);
                    return this.focusAt(i + 1, 'start');
                }
                const before = item.text.slice(0, el.selectionStart);
                const after = item.text.slice(el.selectionEnd);
                item.text = before;
                b.items.splice(j + 1, 0, { id: uid(), text: after });
                return this.focusAt(i, 'start', j + 1);
            }

            if (event.key === 'Backspace' && el.selectionStart === 0 && el.selectionEnd === 0) {
                event.preventDefault();
                if (j > 0) {
                    const prev = b.items[j - 1];
                    const pos = prev.text.length;
                    prev.text += item.text;
                    b.items.splice(j, 1);
                    return this.focusAt(i, pos, j - 1);
                }
                // Birinchi band — oddiy paragrafga aylanadi.
                b.items.splice(0, 1);
                const p = this.newBlock('p', { text: item.text });
                if (b.items.length) this.blocks.splice(i, 0, p);
                else this.blocks.splice(i, 1, p);
                return this.focusAt(i, 'start');
            }

            if (event.key === 'ArrowUp' && el.selectionStart === 0) {
                event.preventDefault();
                return j > 0 ? this.focusAt(i, 'end', j - 1) : this.focusNearest(i - 1, 'end', -1);
            }
            if (event.key === 'ArrowDown' && el.selectionEnd === el.value.length) {
                event.preventDefault();
                return j < b.items.length - 1 ? this.focusAt(i, 'start', j + 1) : this.focusNearest(i + 1, 'start', 1);
            }
        },

        /* --- Asboblar paneli --- */
        current() {
            return this.blocks[Math.min(this.focused, this.blocks.length - 1)];
        },
        isActive(type, ordered = null) {
            const b = this.current();
            if (!b || b.type !== type) return false;
            return ordered === null || b.ordered === ordered;
        },
        setType(type) {
            const i = Math.min(this.focused, this.blocks.length - 1);
            const b = this.blocks[i];
            if (this.isText(b)) {
                b.type = b.type === type ? 'p' : type;
                return this.focusAt(i, this.sel.start);
            }
            if (b?.type === 'list') {
                const parts = b.items.map((it) => this.newBlock(type, { text: it.text }));
                this.blocks.splice(i, 1, ...parts);
                return this.focusAt(i + parts.length - 1, 'end');
            }
            const at = this.insertAfter(i, this.newBlock(type));
            this.focusAt(at, 'start');
        },
        setList(ordered) {
            const i = Math.min(this.focused, this.blocks.length - 1);
            const b = this.blocks[i];
            if (b?.type === 'list') {
                if (b.ordered === ordered) {
                    const parts = b.items.map((it) => this.newBlock('p', { text: it.text }));
                    this.blocks.splice(i, 1, ...parts);
                    return this.focusAt(i, 'end');
                }
                b.ordered = ordered;
                return this.focusAt(i, 'end', this.focusedItem ?? b.items.length - 1);
            }
            if (this.isText(b)) {
                const lines = b.text.split('\n');
                this.blocks.splice(i, 1, { id: uid(), type: 'list', ordered, items: lines.map((text) => ({ id: uid(), text })) });
                return this.focusAt(i, 'end', lines.length - 1);
            }
            const at = this.insertAfter(i, { id: uid(), type: 'list', ordered, items: [{ id: uid(), text: '' }] });
            this.focusAt(at, 'start', 0);
        },
        insertHr() {
            const at = this.insertAfter(Math.min(this.focused, this.blocks.length - 1), this.newBlock('hr'));
            this.focusNearest(at + 1, 'start', 1);
        },
        /** Joriy blokdan keyin qo‘yadi; joriy blok bo‘sh paragraf bo‘lsa — o‘rniga. Qo‘yilgan indeksni qaytaradi. */
        insertAfter(i, block) {
            const cur = this.blocks[i];
            let at;
            if (cur && cur.type === 'p' && !cur.text.trim()) {
                this.blocks.splice(i, 1, block);
                at = i;
            } else {
                this.blocks.splice(i + 1, 0, block);
                at = i + 1;
            }
            // Oxirida rasm yoki ajratgich qolsa — yozishni davom ettirish uchun bo‘sh paragraf.
            const last = this.blocks[this.blocks.length - 1];
            if (!this.isText(last) && last.type !== 'list') this.blocks.push(this.newBlock('p'));
            this.focused = at;
            return at;
        },
        moveBlock(i, dir) {
            const j = i + dir;
            if (j < 0 || j >= this.blocks.length) return;
            const [b] = this.blocks.splice(i, 1);
            this.blocks.splice(j, 0, b);
            this.highlight(b.id);
        },
        removeBlock(i) {
            this.blocks.splice(i, 1);
            if (!this.blocks.length) this.blocks.push(this.newBlock('p'));
            this.focusNearest(Math.max(0, i - 1), 'end', i > 0 ? -1 : 1);
        },
        highlight(id) {
            this.flash = id;
            clearTimeout(this._timers.flash);
            this._timers.flash = setTimeout(() => (this.flash = null), 900);
        },
        /** Qalin / kursiv: belgilangan matn (yoki kursor turgan so‘z) ** yoki * bilan o‘raladi; qayta bosilsa — olib tashlanadi. */
        wrap(mark) {
            const i = this.focused;
            const j = this.focusedItem;
            const b = this.blocks[i];
            const target = j === null || j === undefined ? b : b?.items?.[j];
            if (!target || typeof target.text !== 'string') return;
            const t = target.text;
            let { start, end } = this.sel;
            const n = mark.length;

            if (t.slice(start - n, start) === mark && t.slice(end, end + n) === mark) {
                target.text = t.slice(0, start - n) + t.slice(start, end) + t.slice(end + n);
                return this.selectRange(i, j, start - n, end - n);
            }
            if (start === end) {
                const left = t.slice(0, start).match(/[\p{L}\p{N}_‘’ʻʼ']*$/u)[0].length;
                const right = t.slice(end).match(/^[\p{L}\p{N}_‘’ʻʼ']*/u)[0].length;
                start -= left;
                end += right;
            }
            target.text = t.slice(0, start) + mark + t.slice(start, end) + mark + t.slice(end);
            this.selectRange(i, j, start + n, end + n);
        },
        selectRange(i, j, start, end) {
            this.$nextTick(() => {
                const el = this.el(i, j);
                if (!el) return;
                el.focus();
                el.setSelectionRange(start, end);
                this.sel = { start, end };
            });
        },

        /* --- Rasmlar --- */
        pickImages(event) {
            [...event.target.files].slice(0, 10).forEach((f, n) => this.upload(f, Math.min(this.focused, this.blocks.length - 1) + n));
            event.target.value = '';
        },
        drop(event) {
            this.dragging = false;
            const files = [...(event.dataTransfer?.files ?? [])].filter((f) => f.type.startsWith('image/'));
            files.slice(0, 10).forEach((f, n) => this.upload(f, Math.min(this.focused, this.blocks.length - 1) + n));
        },
        upload(file, after) {
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                return toast('Faqat JPG, PNG yoki WEBP rasm qo‘shish mumkin.', 'error');
            }
            if (file.size > 5 * 1024 * 1024) {
                return toast('Rasm 5 MB dan oshmasligi kerak.', 'error');
            }
            const id = uid();
            const at = this.insertAfter(after, { id, type: 'image', path: null, url: URL.createObjectURL(file), caption: '', w: null, h: null, uploading: true, progress: 0 });
            // Rasmdan keyin yozishni davom ettirish mumkin bo‘lsin.
            this.focusNearest(at + 1, 'start', 1);

            const fail = (message) => {
                const index = this.blocks.findIndex((b) => b.id === id);
                if (index !== -1) this.blocks.splice(index, 1);
                toast(message, 'error');
            };
            const xhr = new XMLHttpRequest();
            xhr.open('POST', opts.uploadUrl);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]')?.content ?? '');
            xhr.upload.onprogress = (e) => {
                const b = this.findBlock(id);
                if (b && e.lengthComputable) b.progress = e.loaded / e.total;
            };
            xhr.onload = () => {
                let data = {};
                try {
                    data = JSON.parse(xhr.responseText);
                } catch {
                    /* JSON emas */
                }
                if (xhr.status === 201) {
                    const b = this.findBlock(id);
                    if (b) Object.assign(b, { path: data.path, url: data.url, w: data.width, h: data.height, uploading: false, progress: 1 });
                } else if (xhr.status === 429) {
                    fail('Juda ko‘p rasm yuklandi. Birozdan keyin urinib ko‘ring.');
                } else if (xhr.status === 419) {
                    fail('Sessiya eskirgan — sahifani yangilang (matn saqlanib qoladi).');
                } else {
                    fail(data.errors?.image?.[0] ?? data.message ?? 'Rasmni yuklab bo‘lmadi.');
                }
            };
            xhr.onerror = () => fail('Internet aloqasini tekshiring — rasm yuklanmadi.');
            const form = new FormData();
            form.append('image', file);
            xhr.send(form);
        },

        /* --- Qoralama, ko‘rish, yuborish --- */
        scheduleSave() {
            clearTimeout(this._timers.save);
            this._timers.save = setTimeout(() => {
                if (this.submitting) return;
                if (this.title.trim() || this.hasContent) {
                    draftStore.set(opts.draftKey, { title: this.title, blocks: this.clean, tags: this.tags, at: Date.now() });
                    this.savedLabel = 'Qoralama saqlandi';
                } else {
                    draftStore.remove(opts.draftKey);
                    this.savedLabel = '';
                }
            }, 800);
        },
        discardDraft() {
            draftStore.remove(opts.draftKey);
            this.title = '';
            this.blocks = [this.newBlock('p')];
            this.tags = [];
            this.restored = false;
            this.savedLabel = '';
            this.$nextTick(() => this.$refs.title?.focus());
        },
        togglePreview() {
            this.showPreview = !this.showPreview;
            window.scrollTo({ top: 0 });
        },
        publish() {
            this.asDraft = false;
            this.$nextTick(() => root.requestSubmit());
        },
        saveDraft() {
            this.asDraft = true;
            this.$nextTick(() => root.requestSubmit());
        },
        onSubmit(event) {
            // Qoralama uchun sarlavha yetarli; chop etish uchun — sarlavha va matn.
            const ok = this.asDraft ? this.title.trim().length >= 3 && this.hasContent && !this.uploading : this.canSubmit;
            if (!ok || this.submitting) {
                event.preventDefault();
                if (this.blockedReason) toast(this.blockedReason, 'error');
                return;
            }
            this.submitting = true;
            if (opts.draftKey) draftStore.remove(opts.draftKey);
        },
    };
});

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
            window.dispatchEvent(new CustomEvent('post-comments', { detail: { url: this.url, count: data.comments_count } }));
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

/* ---------- Navigatsiya yordamchilari (asosiysi — navigation.js) ---------- */
/** Lentada turib "Lenta"/logo bosilsa — sahifa qayta yuklanmaydi, yuqoriga silliq qaytadi. */
document.addEventListener('click', (e) => {
    const link = e.target.closest('a[data-home-link]');
    if (!link || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey) return;
    if (location.pathname !== '/') return;
    e.preventDefault();
    window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
});

/** Izoh maydoniga o‘tish (izoh tugmasi bosilganda). Mehmon uchun — izohlar bo‘limiga. */
window.focusComment = (postId) => {
    const input = document.getElementById(`comment-input-${postId}`);
    if (input) {
        input.scrollIntoView({ block: 'center', behavior: 'smooth' });
        input.focus({ preventScroll: true });
    } else {
        document.getElementById('comments')?.scrollIntoView({ behavior: 'smooth' });
    }
};

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
/* ---------- Belgilar va shaxsiy xabarlar (chat.js) ---------- */
registerBadges(Alpine);
registerInbox(Alpine);
registerThread(Alpine);

Alpine.data('readTimer', readTimer);

document.addEventListener('DOMContentLoaded', () => {
    initViewTracking(document);
    const flash = document.body.dataset.toast;
    if (flash) toast(flash, 'success');
});

// Orqaga qaytilganda lenta holati Alpine'dan oldin tiklanadi, so‘ng o‘sha joyga aylantiriladi.
captureFeed()
    .catch(() => {})
    .finally(() => {
        Alpine.start();
        restoreFeedPosition();
    });
