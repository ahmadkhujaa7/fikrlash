/**
 * Shaxsiy xabarlar: suhbat oynasi, suhbatlar ro‘yxati va menyudagi o‘qilmaganlar belgisi.
 *
 * Real vaqt — qisqa so‘rovlar (poll, ~3 soniya, sahifa ko‘rinib turganda): WebSocket server kerak emas.
 * Har so‘rovda: yangi xabarlar (id > oxirgi), o‘zgarganlari (tahrir, reaksiya, o‘chirish),
 * suhbatdosh qayergacha o‘qigani, "yozmoqda" va onlayn holati.
 */
import { api } from './api';
import { explainGeoError, explainMicError, formatAccuracy, locate, policyBlocks } from './hardware';

const toast = (message, type) => window.toast?.(message, type);
const escapeHtml = (s) => String(s).replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch]);
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const coarse = () => window.matchMedia('(pointer: coarse)').matches;
const pad = (n) => String(n).padStart(2, '0');
const clock = (s) => `${Math.floor(s / 60)}:${pad(Math.floor(s % 60))}`;
const BARS = 36;
const MB = 1024 * 1024;

/*
 * Ovoz yozish formati. Hamma qurilmada ijro bo‘lishi uchun avval MP4/AAC, keyin WebM/Opus.
 * Ba'zi brauzerlar MP4'ni "qo‘llayman" deydi-yu, yozolmaydi — bunday format eslab qolinadi
 * va keyingi safar o‘tkazib yuboriladi (yozish "osilib qolmaydi").
 */
const RECORDER_TYPES = ['audio/mp4;codecs=mp4a.40.2', 'audio/mp4', 'audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus'];
const BAD_TYPES_KEY = 'fk:rec-bad-types';
const badTypes = () => {
    try {
        return JSON.parse(localStorage.getItem(BAD_TYPES_KEY) || '[]');
    } catch {
        return [];
    }
};
const markBadType = (type) => {
    if (!type) return;
    try {
        localStorage.setItem(BAD_TYPES_KEY, JSON.stringify([...new Set([...badTypes(), type])]));
    } catch {
        /* xususiy rejim */
    }
};
function createRecorder(stream) {
    const skip = badTypes();
    for (const type of RECORDER_TYPES.filter((t) => !skip.includes(t) && MediaRecorder.isTypeSupported?.(t))) {
        try {
            return new MediaRecorder(stream, { mimeType: type, audioBitsPerSecond: 48000 });
        } catch {
            markBadType(type);
        }
    }
    return new MediaRecorder(stream); // brauzer o‘zi tanlagan format
}

/** Fayl yuborish (XHR — yuklanish foizini ko‘rsatish uchun). Har doim {status, data} qaytaradi. */
function xhrPost(url, form, onProgress) {
    return new Promise((resolve) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', url);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('X-CSRF-TOKEN', csrf());
        xhr.upload.onprogress = (e) => e.lengthComputable && onProgress?.(e.loaded / e.total);
        xhr.onload = () => {
            let data = {};
            try {
                data = JSON.parse(xhr.responseText);
            } catch {
                /* JSON emas */
            }
            resolve({ status: xhr.status, data });
        };
        xhr.onerror = () => resolve({ status: 0, data: {} });
        xhr.send(form);
    });
}

function uploadError(status, data, fallback) {
    if (status === 0) return 'Internet aloqasini tekshiring.';
    if (status === 413) return 'Fayl juda katta — kichikroq fayl tanlang.';
    if (status === 429) return 'Juda tez yuboryapsiz — birozdan keyin.';
    return data?.message || fallback;
}

/** "2 ta rasm, video" kabi qisqa tavsif. */
function mediaLabel(media = []) {
    const images = media.filter((a) => a.kind === 'image').length;
    const videos = media.length - images;
    const part = (n, one, many) => (n ? (n === 1 ? one : `${n} ta ${many}`) : null);
    return [part(images, 'Rasm', 'rasm'), part(videos, images ? 'video' : 'Video', 'video')].filter(Boolean).join(', ') || 'Media';
}

/** Rasmni yuborishdan oldin kichraytiradi (uzun tomoni ≤ maxSide, JPEG). Kichik rasmlar o‘zgarmaydi. */
async function prepareImage(file, maxSide) {
    let source;
    try {
        source = await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch {
        source = await new Promise((resolve) => {
            const url = URL.createObjectURL(file);
            const img = new Image();
            img.onload = () => resolve(img);
            img.onerror = () => resolve(null);
            img.src = url;
        });
    }
    // Brauzer o‘qiy olmaydigan format (masalan HEIC) — o‘zini yuboramiz, server tekshiradi.
    if (!source) return { blob: file, name: file.name, w: null, h: null };

    const width = source.width || source.naturalWidth;
    const height = source.height || source.naturalHeight;
    const scale = Math.min(1, maxSide / Math.max(width, height));
    const keep = (scale === 1 && file.size <= 1.5 * MB && /^image\/(jpeg|png|webp)$/.test(file.type)) || (file.type === 'image/gif' && file.size <= 8 * MB);
    if (keep) {
        source.close?.();
        return { blob: file, name: file.name, w: width, h: height };
    }
    const w = Math.round(width * scale);
    const h = Math.round(height * scale);
    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff'; // shaffof PNG — oq fonda
    ctx.fillRect(0, 0, w, h);
    ctx.drawImage(source, 0, 0, w, h);
    source.close?.();
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.86));
    return blob ? { blob, name: `${file.name.replace(/\.[^.]+$/, '') || 'rasm'}.jpg`, w, h } : { blob: file, name: file.name, w, h };
}

/** Video: davomiyligi, o‘lchami va muqova uchun birinchi kadr (brauzer o‘qiy olsa). */
function probeVideo(file) {
    return new Promise((resolve) => {
        const url = URL.createObjectURL(file);
        const video = document.createElement('video');
        let done = false;
        const finish = (out) => {
            if (done) return;
            done = true;
            clearTimeout(timer);
            video.removeAttribute('src');
            video.load();
            URL.revokeObjectURL(url);
            resolve(out);
        };
        const basic = () => ({ duration: Number.isFinite(video.duration) ? video.duration : 0, w: video.videoWidth || null, h: video.videoHeight || null, poster: null });
        const timer = setTimeout(() => finish(basic()), 8000);
        video.muted = true;
        video.playsInline = true;
        video.preload = 'metadata';
        video.onloadedmetadata = () => {
            video.currentTime = Math.min(0.5, (Number.isFinite(video.duration) ? video.duration : 1) / 3);
        };
        video.onseeked = () => {
            const { videoWidth: w, videoHeight: h } = video;
            if (!w || !h) return finish(basic());
            const scale = Math.min(1, 720 / Math.max(w, h));
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(w * scale);
            canvas.height = Math.round(h * scale);
            try {
                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            } catch {
                return finish(basic());
            }
            canvas.toBlob((poster) => finish({ ...basic(), poster }), 'image/jpeg', 0.8);
        };
        video.onerror = () => finish({ duration: 0, w: null, h: null, poster: null });
        video.src = url;
    });
}

/** To‘lqin shaklini kerakli ustunlar soniga keltiradi (bo‘lmasa — id'dan barqaror "tasodifiy" shakl). */
function resample(values, count, seed = 1) {
    if (!values?.length) {
        let x = seed * 9301 + 49297;
        return Array.from({ length: count }, () => {
            x = (x * 9301 + 49297) % 233280;
            return 25 + Math.round((x / 233280) * 55);
        });
    }
    return Array.from({ length: count }, (_, i) => {
        const from = Math.floor((i * values.length) / count);
        const to = Math.max(from + 1, Math.floor(((i + 1) * values.length) / count));
        const slice = values.slice(from, to);
        return Math.max(14, Math.min(100, Math.round(Math.max(...slice))));
    });
}

/* ---------- Menyudagi belgilar (bildirishnomalar + xabarlar) — bitta umumiy so‘rov ---------- */
export function registerBadges(Alpine) {
    const body = document.body;
    Alpine.store('badges', {
        notifications: Number(body.dataset.unreadNotifications || 0),
        messages: Number(body.dataset.unreadMessages || 0),
        async refresh() {
            if (!body.dataset.auth) return;
            const push = window.fkPush?.enabled();
            try {
                const res = await fetch(push ? '/badges?latest=1' : '/badges', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!res.ok) return;
                const { latest, ...counts } = await res.json();
                Object.assign(this, counts);
                if (push && latest) window.fkPush.handle(latest);
            } catch {
                /* jim */
            }
        },
    });
    if (body.dataset.auth) {
        // Brauzer bildirishnomalari yoqilgan bo‘lsa — sahifa yashirin bo‘lsa ham tekshiradi (brauzer o‘zi siyraklashtiradi).
        setInterval(() => (!document.hidden || window.fkPush?.enabled()) && Alpine.store('badges').refresh(), 30000);
        document.addEventListener('visibilitychange', () => !document.hidden && Alpine.store('badges').refresh());
    }
    Alpine.data('unreadBadge', (kind = 'notifications') => ({
        get count() {
            return Alpine.store('badges')[kind] ?? 0;
        },
    }));
}

/* ---------- Suhbatlar ro‘yxati: o‘zi yangilanadi ---------- */
export function registerInbox(Alpine) {
    Alpine.data('inbox', (url) => ({
        timer: null,
        init() {
            const tick = async () => {
                if (!document.hidden) {
                    try {
                        const res = await fetch(url, { headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                        if (res.ok) {
                            const html = await res.text();
                            const current = this.$el.querySelector('[data-inbox-list]');
                            if (current && current.outerHTML !== html.trim()) current.outerHTML = html;
                        }
                    } catch {
                        /* tarmoq yo‘q — keyingi safar */
                    }
                }
                this.timer = setTimeout(tick, 8000);
            };
            this.timer = setTimeout(tick, 8000);
        },
        destroy() {
            clearTimeout(this.timer);
        },
    }));

    Alpine.data('newChat', (searchUrl, base) => ({
        open: false,
        q: '',
        results: [],
        loading: false,
        base,
        show() {
            this.open = true;
            this.$nextTick(() => this.$refs.q.focus());
        },
        async search() {
            const q = this.q.trim().replace(/^@/, '');
            if (q.length < 2) {
                this.results = [];
                return;
            }
            this.loading = true;
            try {
                const res = await fetch(`${searchUrl}?${new URLSearchParams({ q })}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                this.results = res.ok ? (await res.json()).users : [];
            } catch {
                this.results = [];
            } finally {
                this.loading = false;
            }
        },
    }));
}

/* ---------- Suhbat oynasi ---------- */
export function registerThread(Alpine) {
    Alpine.data('messageThread', (cfg) => {
        // DOM elementlari va taymerlar — reaktiv emas (Alpine proxy'siga tushmasin).
        let audio = null;
        let pollTimer = null;
        let pressTimer = null;
        let recorder = null;
        let stream = null;
        let audioCtx = null;
        let levelTimer = null;
        let clockTimer = null;
        let readTimer = null;
        let tmpSeq = 0;
        let lastTypingSent = 0;
        const objectUrls = new Set();
        const blobUrl = (blob) => {
            const url = URL.createObjectURL(blob);
            objectUrls.add(url);
            return url;
        };

        const withKey = (m) => ({ ...m, key: `m${m.id}` });

        return {
            messages: cfg.messages.map(withKey),
            hasMore: cfg.hasMore,
            peerRead: cfg.peerRead,
            presence: cfg.presence,
            cannotSend: cfg.cannotSend,
            reactions: cfg.reactions,
            maxLength: cfg.maxLength,
            since: cfg.now,
            peerTyping: false,
            draft: '',
            replyTo: null,
            editing: null,
            atBottom: true,
            newCount: 0,
            loadingOlder: false,
            flashId: null,
            viewport: '',
            sheet: { open: false, m: null, confirm: false },
            _readSent: 0,
            player: { id: null, progress: 0, time: 0, playing: false },
            rec: { state: 'idle', seconds: 0, live: [], levels: [] },
            attachOpen: false,
            pending: [],
            locating: false,
            dragging: false,

            get pendingBusy() {
                return this.pending.some((f) => f.busy);
            },

            init() {
                document.documentElement.classList.add('chat-lock');
                this.fitViewport();
                window.visualViewport?.addEventListener('resize', () => this.fitViewport());
                window.visualViewport?.addEventListener('scroll', () => this.fitViewport());
                window.addEventListener('resize', () => this.fitViewport());

                this.$nextTick(() => {
                    this.scrollToBottom(false);
                    if (!coarse()) this.$refs.input?.focus({ preventScroll: true });
                });
                this.schedulePoll(cfg.pollMs);
                document.addEventListener('visibilitychange', () => {
                    if (!document.hidden) this.poll();
                });
                window.addEventListener('pagehide', () => this.stopEverything());
            },
            destroy() {
                this.stopEverything();
                objectUrls.forEach((url) => URL.revokeObjectURL(url));
                document.documentElement.classList.remove('chat-lock');
            },
            stopEverything() {
                clearTimeout(pollTimer);
                audio?.pause();
                this.cancelRecording();
            },

            /** Mobil: suhbat aynan ko‘rinadigan qismni egallaydi (klaviatura ochilganda ham kiritish maydoni ko‘rinadi). */
            fitViewport() {
                const vv = window.visualViewport;
                if (!vv || window.innerWidth >= 768) {
                    this.viewport = '';
                    return;
                }
                const wasBottom = this.atBottom;
                this.viewport = `height:${Math.round(vv.height)}px;transform:translateY(${Math.round(vv.offsetTop)}px)`;
                if (wasBottom) this.$nextTick(() => this.scrollToBottom(false));
            },

            /* --- Ro‘yxat: kun ajratgichlari va guruhlash --- */
            get items() {
                const out = [];
                let prevDay = null;
                let prev = null;
                const near = (a, b) => Math.abs(Date.parse(b.at) - Date.parse(a.at)) < 5 * 60 * 1000;
                this.messages.forEach((m, i) => {
                    if (m.day !== prevDay) {
                        out.push({ kind: 'day', key: `d${m.day}`, label: m.day_label });
                        prevDay = m.day;
                        prev = null;
                    }
                    const next = this.messages[i + 1];
                    const first = !(prev && prev.mine === m.mine && near(prev, m));
                    const last = !(next && next.mine === m.mine && next.day === m.day && near(m, next));
                    out.push({ kind: 'msg', key: m.key, m, first, last });
                    prev = m;
                });
                return out;
            },
            bubbleClass(item) {
                const m = item.m;
                const side = m.mine ? 'bubble-mine' : 'bubble-theirs';
                const corners = m.mine
                    ? `${item.first ? '' : 'rounded-tr-[6px]'} ${item.last ? '' : 'rounded-br-[6px]'}`
                    : `${item.first ? '' : 'rounded-tl-[6px]'} ${item.last ? '' : 'rounded-bl-[6px]'}`;
                return `${side} ${corners} ${m.pending ? 'opacity-80' : ''} ${m.failed ? '!bg-none !bg-anor' : ''}`;
            },
            isRead(m) {
                return typeof m.id === 'number' && m.id <= this.peerRead;
            },
            get lastId() {
                const ids = this.messages.map((m) => m.id).filter((id) => typeof id === 'number');
                return ids.length ? Math.max(...ids) : 0;
            },
            get contextText() {
                const m = this.editing || this.replyTo;
                if (!m) return '';
                if (m.type === 'voice') return `Ovozli xabar · ${clock(m.voice?.duration || 0)}`;
                const text = (m.body ?? this.plain(m.html)).replace(/\s+/g, ' ').trim().slice(0, 120);
                if (m.type === 'media') return text ? `${mediaLabel(m.media)} · ${text}` : mediaLabel(m.media);
                if (m.type === 'location') return 'Joylashuv';
                if (m.type === 'post') return text || 'Ulashilgan post';
                return text;
            },
            plain(html) {
                const div = document.createElement('div');
                div.innerHTML = (html || '').replace(/<br\s*\/?>/g, '\n');
                return div.textContent || '';
            },
            clock,

            /* --- Aylantirish --- */
            scrollToBottom(smooth = true) {
                const el = this.$refs.scroller;
                if (!el) return;
                el.scrollTo({ top: el.scrollHeight, behavior: smooth && !window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'smooth' : 'auto' });
                this.atBottom = true;
                this.newCount = 0;
                this.markRead();
            },
            onScroll() {
                const el = this.$refs.scroller;
                this.atBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 120;
                if (this.atBottom && this.newCount) {
                    this.newCount = 0;
                    this.markRead();
                }
                if (el.scrollTop < 160 && this.hasMore && !this.loadingOlder) this.loadOlder();
            },
            async loadOlder() {
                const first = this.messages.find((m) => typeof m.id === 'number');
                if (!first) return;
                this.loadingOlder = true;
                const el = this.$refs.scroller;
                const before = el.scrollHeight;
                try {
                    const data = await api('GET', `${cfg.urls.history}?before=${first.id}`);
                    this.messages = [...data.messages.map(withKey), ...this.messages];
                    this.hasMore = data.hasMore;
                    this.$nextTick(() => {
                        el.scrollTop += el.scrollHeight - before;
                    });
                } catch {
                    /* keyingi aylantirishda qayta urinadi */
                } finally {
                    this.loadingOlder = false;
                }
            },
            jumpTo(id) {
                const el = document.getElementById(`m-${id}`);
                if (!el) {
                    toast('Bu xabar ancha yuqorida — yuqoriga aylantiring.');
                    return;
                }
                el.scrollIntoView({ block: 'center', behavior: 'smooth' });
                this.flashId = id;
                setTimeout(() => (this.flashId = null), 1300);
            },

            /* --- Yangilanishlar (poll) --- */
            schedulePoll(ms) {
                clearTimeout(pollTimer);
                pollTimer = setTimeout(() => this.poll(), ms);
            },
            async poll() {
                clearTimeout(pollTimer);
                if (document.hidden) {
                    this.schedulePoll(cfg.pollMs * 3);
                    return;
                }
                try {
                    const data = await api('GET', `${cfg.urls.poll}?${new URLSearchParams({ after: this.lastId, since: this.since })}`);
                    this.merge(data);
                } catch {
                    /* tarmoq yo‘q — keyingi safar */
                }
                this.schedulePoll(cfg.pollMs);
            },
            merge(data) {
                this.since = data.now;
                this.peerRead = Math.max(this.peerRead, data.peerRead);
                this.peerTyping = data.typing;
                this.presence = data.presence;

                for (const m of data.updated) this.replace(m);

                const fresh = data.messages.filter((m) => !this.messages.some((x) => x.id === m.id));
                if (!fresh.length) return;
                const wasBottom = this.atBottom;
                this.messages.push(...fresh.map(withKey));
                const incoming = fresh.filter((m) => !m.mine).length;
                if (incoming) this.peerTyping = false;
                if (wasBottom) {
                    this.$nextTick(() => this.scrollToBottom(true));
                } else {
                    this.newCount += incoming;
                }
            },
            replace(m) {
                const i = this.messages.findIndex((x) => x.id === m.id);
                if (i !== -1) this.messages[i] = { ...m, key: this.messages[i].key };
                if (this.player.id === m.id && (m.removed || !m.voice)) this.stopAudio();
            },
            markRead() {
                if (document.hidden) return;
                clearTimeout(readTimer);
                readTimer = setTimeout(async () => {
                    const lastIncoming = Math.max(0, ...this.messages.filter((m) => !m.mine && typeof m.id === 'number').map((m) => m.id));
                    if (!lastIncoming || lastIncoming <= (this._readSent || 0)) return;
                    this._readSent = lastIncoming;
                    try {
                        const data = await api('POST', cfg.urls.read, { id: lastIncoming });
                        if (window.Alpine?.store('badges')) window.Alpine.store('badges').messages = data.unread;
                    } catch {
                        this._readSent = 0;
                    }
                }, 300);
            },

            /* --- Yozish --- */
            onDraftInput() {
                const el = this.$refs.input;
                el.style.height = 'auto';
                el.style.height = `${Math.min(el.scrollHeight, 144)}px`;
                if (this.draft.trim() && !this.editing && Date.now() - lastTypingSent > 3000) {
                    lastTypingSent = Date.now();
                    api('POST', cfg.urls.typing).catch(() => {});
                }
            },
            onComposerKey(e) {
                if (e.key === 'Enter' && !e.shiftKey && !e.isComposing && !coarse()) {
                    e.preventDefault();
                    this.submit();
                } else if (e.key === 'ArrowUp' && !this.draft && !this.editing) {
                    const last = [...this.messages].reverse().find((m) => m.mine && m.type === 'text' && !m.removed && typeof m.id === 'number');
                    if (last) {
                        e.preventDefault();
                        this.startEdit(last);
                    }
                }
            },
            onPaste(e) {
                // Nusxalangan rasm (masalan, ekran surati) — biriktiriladi.
                const files = [...(e.clipboardData?.files ?? [])].filter((f) => /^(image|video)\//.test(f.type));
                if (files.length && !this.editing) {
                    e.preventDefault();
                    this.addFiles(files);
                    return;
                }
                const text = e.clipboardData?.getData('text/plain') ?? '';
                if (this.draft.length + text.length > this.maxLength) toast(`Xabar ${this.maxLength} belgidan oshmasin.`, 'error');
            },

            /* --- Rasm va video biriktirish --- */
            pickFiles(e) {
                const files = [...(e.target.files ?? [])];
                e.target.value = '';
                this.attachOpen = false;
                this.addFiles(files);
            },
            onDrop(e) {
                this.dragging = false;
                if (this.cannotSend || this.editing) return;
                this.addFiles([...(e.dataTransfer?.files ?? [])]);
            },
            addFiles(files) {
                const room = cfg.media.maxFiles - this.pending.length;
                if (!files.length) return;
                if (room <= 0) {
                    toast(`Bir xabarda ko‘pi bilan ${cfg.media.maxFiles} ta fayl.`, 'error');
                    return;
                }
                if (files.length > room) toast(`Bir xabarda ko‘pi bilan ${cfg.media.maxFiles} ta fayl — ortiqchasi olinmadi.`, 'error');

                for (const file of files.slice(0, room)) {
                    const kind = file.type.startsWith('video/') ? 'video' : file.type.startsWith('image/') || /\.(heic|heif)$/i.test(file.name) ? 'image' : null;
                    if (!kind) {
                        toast(`"${file.name}" — faqat rasm yoki video yuborish mumkin.`, 'error');
                        continue;
                    }
                    const limit = kind === 'video' ? cfg.media.videoMaxBytes : cfg.media.imageMaxBytes;
                    if (file.size > limit) {
                        toast(`"${file.name}" juda katta — ko‘pi bilan ${Math.floor(limit / MB)} MB.`, 'error');
                        continue;
                    }
                    const item = { key: `f${++tmpSeq}`, kind, name: file.name, blob: file, thumb: '', poster: null, duration: 0, w: null, h: null, busy: true };
                    this.pending.push(item);
                    this.prepare(item.key, file, kind);
                }
                this.$nextTick(() => this.$refs.input?.focus({ preventScroll: true }));
            },
            async prepare(key, file, kind) {
                const patch = {};
                if (kind === 'image') {
                    const out = await prepareImage(file, cfg.media.imageMaxWidth);
                    Object.assign(patch, out, { thumb: blobUrl(out.blob) });
                } else {
                    const out = await probeVideo(file);
                    if (out.duration && out.duration > cfg.media.videoMaxSeconds) {
                        toast(`Video ${Math.round(cfg.media.videoMaxSeconds / 60)} daqiqadan uzun bo‘lmasin.`, 'error');
                        this.pending = this.pending.filter((f) => f.key !== key);
                        return;
                    }
                    Object.assign(patch, { duration: Math.round(out.duration || 0), w: out.w, h: out.h, poster: out.poster, thumb: out.poster ? blobUrl(out.poster) : '' });
                }
                const item = this.pending.find((f) => f.key === key);
                if (item) Object.assign(item, patch, { busy: false });
            },
            removePending(i) {
                const [item] = this.pending.splice(i, 1);
                if (item?.thumb) {
                    URL.revokeObjectURL(item.thumb);
                    objectUrls.delete(item.thumb);
                }
            },
            sendMedia(text) {
                const files = this.pending.splice(0);
                const total = files.reduce((sum, f) => sum + (f.blob?.size || 0) + (f.poster?.size || 0), 0);
                if (total > cfg.media.postMaxBytes) {
                    this.pending = files;
                    toast(`Fayllar jami ${Math.floor(cfg.media.postMaxBytes / MB)} MB dan oshmasin — bir nechta xabarga bo‘lib yuboring.`, 'error');
                    return;
                }
                const temp = this.pushTemp({
                    type: 'media',
                    html: text ? escapeHtml(text).replace(/\n/g, '<br>') : null,
                    body: text || null,
                    media: files.map((f) => ({ key: f.key, kind: f.kind, thumb: f.thumb, url: null, poster: null, w: f.w, h: f.h, duration: f.duration })),
                    uploading: true,
                    progress: 0,
                    files,
                });
                const replyId = this.replyTo?.id;
                this.replyTo = null;
                this.resetInput();
                this.uploadMedia(temp, files, text, replyId);
            },
            async uploadMedia(temp, files, text, replyId) {
                const form = new FormData();
                if (text) form.append('body', text);
                if (replyId) form.append('reply_to_id', String(replyId));
                files.forEach((f, i) => {
                    form.append(`files[${i}]`, f.blob, f.name);
                    if (f.poster) form.append(`posters[${i}]`, f.poster, 'poster.jpg');
                    if (f.duration) form.append(`durations[${i}]`, String(f.duration));
                    if (f.w && f.h) form.append(`dims[${i}]`, `${f.w}x${f.h}`);
                });
                const { status, data } = await xhrPost(cfg.urls.send, form, (p) => {
                    const m = this.messages.find((x) => x.key === temp.key);
                    if (m) m.progress = p;
                });
                if (status === 201) {
                    // Yuklangan rasmlar qayta yuklanmasin — mahalliy nusxasi ko‘rinib turadi.
                    data.message.media = (data.message.media ?? []).map((a, i) => ({ ...a, thumb: files[i]?.thumb || null }));
                    this.settle(temp, data.message);
                    return;
                }
                const m = this.messages.find((x) => x.key === temp.key);
                if (m) Object.assign(m, { pending: false, uploading: false, failed: true, retryBody: text, retryReply: replyId });
                toast(uploadError(status, data, 'Rasm/video yuborilmadi.'), 'error');
            },

            /* --- Joylashuv --- */
            async shareLocation() {
                this.attachOpen = false;
                if (!window.isSecureContext || !navigator.geolocation || policyBlocks('geolocation')) {
                    explainGeoError(null);
                    return;
                }
                const ok = await window.confirmAction({
                    title: 'Joylashuv yuborilsinmi?',
                    text: `${cfg.peerName ?? 'Suhbatdoshingiz'} hozirgi joylashuvingizni xaritada ko‘radi.`,
                    ok: 'Yuborish',
                    tone: 'primary',
                });
                if (!ok) return;

                this.locating = true;
                let pos;
                try {
                    pos = await locate(); // avval GPS, bo‘lmasa Wi‑Fi/tarmoq bo‘yicha
                } catch (err) {
                    explainGeoError(err);
                    return;
                } finally {
                    this.locating = false;
                }

                const round = (n) => Math.round(n * 1e6) / 1e6;
                const acc = Math.round(pos.coords.accuracy || 0);
                // Kompyuterda GPS yo‘q — joylashuv taxminiy bo‘lishi mumkin; foydalanuvchi bilsin.
                if (acc > 3000) {
                    const go = await window.confirmAction({
                        title: 'Joylashuv taxminiy',
                        text: `Qurilma joylashuvingizni faqat taxminan aniqladi (${formatAccuracy(acc)}). Telefonda GPS, kompyuterda Wi‑Fi yoqilsa aniqroq bo‘ladi. Shunday yuborilsinmi?`,
                        ok: 'Yuborish',
                        tone: 'primary',
                    });
                    if (!go) return;
                }
                this.sendLocation({ lat: round(pos.coords.latitude), lng: round(pos.coords.longitude), acc });
            },
            formatAccuracy,
            async sendLocation(location, existing = null) {
                const temp = existing ?? this.pushTemp({ type: 'location', location });
                const replyId = existing ? existing.retryReply : this.replyTo?.id;
                if (!existing) this.replyTo = null;
                try {
                    const data = await api('POST', cfg.urls.send, { ...location, reply_to_id: replyId ?? null });
                    this.settle(temp, data.message);
                } catch (err) {
                    const m = this.messages.find((x) => x.key === temp.key);
                    if (m) Object.assign(m, { pending: false, failed: true, retryReply: replyId });
                    toast(err.message, 'error');
                }
            },
            /** OpenStreetMap parchalari: nuqta markazda turadigan 260×150 kesim. */
            mapTiles(loc) {
                const z = 15;
                const W = 260;
                const H = 150;
                const T = 256;
                const n = 2 ** z;
                const lat = (Math.max(-85, Math.min(85, loc.lat)) * Math.PI) / 180;
                const x = ((loc.lng + 180) / 360) * n * T;
                const y = ((1 - Math.log(Math.tan(lat) + 1 / Math.cos(lat)) / Math.PI) / 2) * n * T;
                const left = x - W / 2;
                const top = y - H / 2;
                const tiles = [];
                for (let tx = Math.floor(left / T); tx <= Math.floor((left + W) / T); tx++) {
                    for (let ty = Math.floor(top / T); ty <= Math.floor((top + H) / T); ty++) {
                        tiles.push({ key: `${tx}_${ty}`, src: `https://tile.openstreetmap.org/${z}/${((tx % n) + n) % n}/${ty}.png`, left: Math.round(tx * T - left), top: Math.round(ty * T - top) });
                    }
                }
                return tiles;
            },
            mapLink(loc) {
                return `https://www.google.com/maps?q=${loc.lat},${loc.lng}`;
            },
            yandexLink(loc) {
                return `https://yandex.uz/maps/?pt=${loc.lng},${loc.lat}&z=16&l=map`;
            },

            /** Albomni to‘liq ekranda ko‘rish (rasm — kattalashtirish, video — ijro). */
            openMedia(m, index) {
                const caption = this.plain(m.html).trim();
                const items = (m.media ?? []).map((a) => ({ type: a.kind, src: a.url || a.thumb, poster: a.poster || a.thumb, caption }));
                window.openLightbox?.(items, index);
            },
            mediaLabel,
            resetInput() {
                this.draft = '';
                this.$nextTick(() => {
                    const el = this.$refs.input;
                    if (el) el.style.height = 'auto';
                });
            },
            startReply(m) {
                this.editing = null;
                this.replyTo = { ...m, peerName: m.mine ? 'Siz' : cfg.peerName };
                this.$nextTick(() => this.$refs.input?.focus());
            },
            startEdit(m) {
                this.replyTo = null;
                this.editing = m;
                this.draft = m.body ?? this.plain(m.html);
                this.$nextTick(() => {
                    this.onDraftInput();
                    const el = this.$refs.input;
                    el?.focus();
                    el?.setSelectionRange(el.value.length, el.value.length);
                });
            },
            cancelContext() {
                if (this.editing) this.resetInput();
                this.editing = null;
                this.replyTo = null;
            },
            async submit() {
                const text = this.draft.trim();

                if (this.editing) {
                    const target = this.editing;
                    if (!text && target.type === 'text') return;
                    this.editing = null;
                    this.resetInput();
                    if (text === (target.body ?? '').trim()) return;
                    try {
                        const data = await api('PATCH', cfg.urls.message.replace('__ID__', target.id), { body: text });
                        this.replace(data.message);
                    } catch (err) {
                        toast(err.message, 'error');
                    }
                    return;
                }

                if (this.pending.length) {
                    if (!this.pendingBusy) this.sendMedia(text);
                    return;
                }
                if (!text) return;

                const temp = this.pushTemp({ type: 'text', html: escapeHtml(text).replace(/\n/g, '<br>'), body: text });
                const replyId = this.replyTo?.id;
                this.replyTo = null;
                this.resetInput();
                this.sendText(temp, text, replyId);
            },
            pushTemp(fields) {
                const now = new Date();
                const last = this.messages[this.messages.length - 1];
                const temp = {
                    id: `t${++tmpSeq}`,
                    key: `t${tmpSeq}`,
                    mine: true,
                    removed: false,
                    edited: false,
                    reactions: [],
                    reply: this.replyTo ? { id: this.replyTo.id, name: this.replyTo.mine ? 'Siz' : cfg.peerName, text: this.contextText } : null,
                    at: now.toISOString(),
                    time: `${pad(now.getHours())}:${pad(now.getMinutes())}`,
                    day: last?.day_label === 'Bugun' ? last.day : now.toISOString().slice(0, 10),
                    day_label: 'Bugun',
                    pending: true,
                    ...fields,
                };
                this.messages.push(temp);
                this.$nextTick(() => this.scrollToBottom(true));
                return this.messages[this.messages.length - 1];
            },
            settle(temp, message) {
                const i = this.messages.findIndex((m) => m.key === temp.key);
                const dup = this.messages.findIndex((m) => m.id === message.id);
                if (dup !== -1 && dup !== i) this.messages.splice(dup, 1);
                const j = this.messages.findIndex((m) => m.key === temp.key);
                if (j !== -1) this.messages[j] = { ...message, key: temp.key };
            },
            async sendText(temp, text, replyId) {
                try {
                    const data = await api('POST', cfg.urls.send, { body: text, reply_to_id: replyId ?? null });
                    this.settle(temp, data.message);
                } catch (err) {
                    const m = this.messages.find((x) => x.key === temp.key);
                    if (m) Object.assign(m, { pending: false, failed: true, retryBody: text, retryReply: replyId });
                    toast(err.message, 'error');
                }
            },
            retry(m) {
                Object.assign(m, { pending: true, failed: false });
                if (m.type === 'voice') this.uploadVoice(m, m.blob, m.voice.duration, m.levels, m.retryReply);
                else if (m.type === 'media') {
                    Object.assign(m, { uploading: true, progress: 0 });
                    this.uploadMedia(m, m.files, m.retryBody, m.retryReply);
                } else if (m.type === 'location') this.sendLocation(m.location, m);
                else this.sendText(m, m.retryBody, m.retryReply);
            },

            /* --- Amallar (bosib turish / o‘ng tugma / ikki marta bosish) --- */
            pressStart(m) {
                clearTimeout(pressTimer);
                pressTimer = setTimeout(() => {
                    navigator.vibrate?.(12);
                    this.openSheet(m);
                }, 450);
            },
            pressEnd() {
                clearTimeout(pressTimer);
            },
            openSheet(m) {
                if (m.pending || typeof m.id !== 'number') return;
                this.$refs.input?.blur();
                this.sheet = { open: true, m, confirm: false };
            },
            closeSheet() {
                this.sheet.open = false;
            },
            myReaction(m) {
                return m?.reactions?.find((r) => r.mine)?.emoji ?? null;
            },
            quickReact(m) {
                if (m.removed || typeof m.id !== 'number') return;
                window.getSelection?.()?.removeAllRanges();
                this.react(m, this.reactions[0]);
            },
            async react(m, emoji) {
                if (typeof m.id !== 'number') return;
                // Darhol ko‘rsatamiz, server javobi bilan aniqlashtiramiz.
                const before = JSON.parse(JSON.stringify(m.reactions));
                const mine = before.find((r) => r.mine);
                let next = before.map((r) => (r.mine ? { ...r, count: r.count - 1, mine: false } : r)).filter((r) => r.count > 0);
                if (mine?.emoji !== emoji) {
                    const ex = next.find((r) => r.emoji === emoji);
                    if (ex) Object.assign(ex, { count: ex.count + 1, mine: true });
                    else next = [...next, { emoji, count: 1, mine: true }];
                }
                this.replace({ ...m, reactions: next });
                try {
                    const data = await api('POST', `${cfg.urls.message.replace('__ID__', m.id)}/react`, { emoji });
                    this.replace(data.message);
                } catch (err) {
                    this.replace({ ...m, reactions: before });
                    toast(err.message, 'error');
                }
            },
            async copy(m) {
                try {
                    await navigator.clipboard.writeText(this.plain(m.html));
                    toast('Nusxa olindi.', 'success');
                } catch {
                    toast('Nusxa olib bo‘lmadi.', 'error');
                }
            },
            async remove(m) {
                this.closeSheet();
                try {
                    const data = await api('DELETE', cfg.urls.message.replace('__ID__', m.id));
                    this.replace(data.message);
                } catch (err) {
                    toast(err.message, 'error');
                }
            },

            /* --- Ovozli xabarni eshitish --- */
            bars(m) {
                return resample(m.voice?.waveform ?? m.levels, BARS, typeof m.id === 'number' ? m.id : 7);
            },
            isPlaying(m) {
                return this.player.id === m.id && this.player.playing;
            },
            progressOf(m) {
                return this.player.id === m.id ? this.player.progress : 0;
            },
            voiceLabel(m) {
                const total = m.voice?.duration ?? 0;
                return this.player.id === m.id ? `${clock(this.player.time)} / ${clock(total)}` : clock(total);
            },
            togglePlay(m) {
                if (!m.voice?.url) return;
                if (this.player.id === m.id && audio) {
                    if (audio.paused) audio.play().catch(() => toast('Ovozni ijro etib bo‘lmadi.', 'error'));
                    else audio.pause();
                    return;
                }
                this.stopAudio();
                audio = new Audio(m.voice.url);
                audio.preload = 'auto';
                const duration = m.voice.duration || 1;
                this.player = { id: m.id, progress: 0, time: 0, playing: false };
                audio.addEventListener('timeupdate', () => {
                    this.player.time = audio.currentTime;
                    this.player.progress = Math.min(1, audio.currentTime / (Number.isFinite(audio.duration) ? audio.duration : duration));
                });
                audio.addEventListener('play', () => (this.player.playing = true));
                audio.addEventListener('pause', () => (this.player.playing = false));
                audio.addEventListener('ended', () => {
                    this.player = { id: null, progress: 0, time: 0, playing: false };
                });
                audio.addEventListener('error', () => {
                    toast('Bu ovozli xabarni qurilmangiz ijro eta olmadi.', 'error');
                    this.stopAudio();
                });
                audio.play().catch(() => {});
            },
            seek(e, m) {
                if (this.player.id !== m.id || !audio) {
                    this.togglePlay(m);
                    return;
                }
                const rect = e.currentTarget.getBoundingClientRect();
                const ratio = Math.min(1, Math.max(0, (e.clientX - rect.left) / rect.width));
                const total = Number.isFinite(audio.duration) ? audio.duration : m.voice.duration;
                audio.currentTime = ratio * total;
            },
            stopAudio() {
                if (audio) {
                    audio.pause();
                    audio.src = '';
                }
                audio = null;
                this.player = { id: null, progress: 0, time: 0, playing: false };
            },

            /* --- Ovoz yozish --- */
            async startRecording() {
                if (this.rec.state !== 'idle') return;
                if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia || !window.MediaRecorder || policyBlocks('microphone')) {
                    explainMicError(null);
                    return;
                }
                this.stopAudio();
                this.rec = { state: 'starting', seconds: 0, live: [], levels: [] };
                try {
                    stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true } });
                } catch (err) {
                    this.cleanupRecorder();
                    this.rec = { state: 'idle', seconds: 0, live: [], levels: [] };
                    explainMicError(err);
                    return;
                }

                const chunks = [];
                let type = '';
                const fail = (message, err) => {
                    markBadType(type);
                    this.cleanupRecorder();
                    this.rec = { state: 'idle', seconds: 0, live: [], levels: [] };
                    if (err) explainMicError(err);
                    else toast(message, 'error');
                };
                try {
                    recorder = createRecorder(stream);
                    type = recorder.mimeType || '';
                } catch (err) {
                    fail('', err);
                    return;
                }
                recorder.ondataavailable = (e) => e.data.size && chunks.push(e.data);
                recorder.onerror = () => {
                    // Format ishlamadi — keyingi safar boshqasi tanlanadi.
                    this.rec.state = 'cancelled';
                    try {
                        recorder.stop();
                    } catch {
                        /* allaqachon to‘xtagan */
                    }
                    fail('Ovoz yozishda xato bo‘ldi — yana bir marta urinib ko‘ring.');
                };
                recorder.onstop = () => {
                    const shouldSend = this.rec.state === 'sending';
                    const seconds = Math.max(1, Math.round(this.rec.seconds));
                    const levels = this.rec.levels.slice();
                    this.cleanupRecorder();
                    this.rec = { state: 'idle', seconds: 0, live: [], levels: [] };
                    if (!shouldSend) return;
                    if (!chunks.length) {
                        markBadType(type);
                        toast('Ovoz yozilmadi — yana bir marta urinib ko‘ring.', 'error');
                        return;
                    }
                    const blob = new Blob(chunks, { type: recorder?.mimeType || type || chunks[0]?.type || 'audio/webm' });
                    this.sendVoice(blob, seconds, levels);
                };

                // Ovoz balandligi: jonli ustunlar va keyin to‘lqin shakli uchun.
                try {
                    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                    const analyser = audioCtx.createAnalyser();
                    analyser.fftSize = 512;
                    audioCtx.createMediaStreamSource(stream).connect(analyser);
                    const buf = new Uint8Array(analyser.fftSize);
                    levelTimer = setInterval(() => {
                        analyser.getByteTimeDomainData(buf);
                        let sum = 0;
                        for (const v of buf) sum += ((v - 128) / 128) ** 2;
                        const level = Math.min(100, Math.round(Math.sqrt(sum / buf.length) * 320));
                        this.rec.levels.push(level);
                        this.rec.live = [...this.rec.live.slice(-31), Math.max(12, level)];
                    }, 100);
                } catch {
                    /* to‘lqin shaklisiz ham ishlaydi */
                }

                try {
                    recorder.start(250);
                } catch (err) {
                    fail('', err);
                    return;
                }
                const started = performance.now();
                clockTimer = setInterval(() => {
                    this.rec.seconds = (performance.now() - started) / 1000;
                    if (this.rec.seconds >= cfg.maxVoice) this.finishRecording();
                }, 200);
                this.rec.state = 'recording';
                navigator.vibrate?.(10);
            },
            finishRecording() {
                if (this.rec.state !== 'recording' || !recorder) return;
                if (this.rec.seconds < 0.7) {
                    toast('Juda qisqa — gapirib turing.', 'error');
                    this.cancelRecording();
                    return;
                }
                this.rec.state = 'sending';
                recorder.stop();
            },
            cancelRecording() {
                if (recorder && recorder.state !== 'inactive') {
                    this.rec.state = 'cancelled';
                    recorder.stop();
                } else {
                    this.cleanupRecorder();
                    this.rec = { state: 'idle', seconds: 0, live: [], levels: [] };
                }
            },
            cleanupRecorder() {
                clearInterval(levelTimer);
                clearInterval(clockTimer);
                stream?.getTracks().forEach((t) => t.stop());
                stream = null;
                audioCtx?.close?.().catch(() => {});
                audioCtx = null;
            },
            sendVoice(blob, seconds, levels) {
                const waveform = resample(levels, 48);
                const temp = this.pushTemp({ type: 'voice', voice: { url: null, duration: seconds, waveform }, uploading: true, progress: 0, blob, levels: waveform });
                const replyId = this.replyTo?.id;
                this.replyTo = null;
                this.uploadVoice(temp, blob, seconds, waveform, replyId);
            },
            uploadVoice(temp, blob, seconds, waveform, replyId) {
                const ext = blob.type.includes('mp4') ? 'm4a' : blob.type.includes('ogg') ? 'ogg' : 'webm';
                const form = new FormData();
                form.append('voice', blob, `voice.${ext}`);
                form.append('duration', String(seconds));
                form.append('waveform', JSON.stringify(waveform));
                if (replyId) form.append('reply_to_id', String(replyId));

                xhrPost(cfg.urls.send, form, (p) => {
                    const m = this.messages.find((x) => x.key === temp.key);
                    if (m) m.progress = p;
                }).then(({ status, data }) => {
                    if (status === 201) {
                        this.settle(temp, data.message);
                        return;
                    }
                    const m = this.messages.find((x) => x.key === temp.key);
                    if (m) Object.assign(m, { pending: false, uploading: false, failed: true, retryReply: replyId });
                    toast(uploadError(status, data, 'Ovozli xabar yuborilmadi.'), 'error');
                });
            },
        };
    });
}
