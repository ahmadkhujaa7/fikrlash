/**
 * Shaxsiy xabarlar: suhbat oynasi, suhbatlar ro‘yxati va menyudagi o‘qilmaganlar belgisi.
 *
 * Real vaqt — qisqa so‘rovlar (poll, ~3 soniya, sahifa ko‘rinib turganda): WebSocket server kerak emas.
 * Har so‘rovda: yangi xabarlar (id > oxirgi), o‘zgarganlari (tahrir, reaksiya, o‘chirish),
 * suhbatdosh qayergacha o‘qigani, "yozmoqda" va onlayn holati.
 */
import { api } from './api';

const toast = (message, type) => window.toast?.(message, type);
const escapeHtml = (s) => String(s).replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch]);
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const coarse = () => window.matchMedia('(pointer: coarse)').matches;
const pad = (n) => String(n).padStart(2, '0');
const clock = (s) => `${Math.floor(s / 60)}:${pad(Math.floor(s % 60))}`;
const BARS = 36;

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
            try {
                const res = await fetch('/badges', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (res.ok) Object.assign(this, await res.json());
            } catch {
                /* jim */
            }
        },
    });
    if (body.dataset.auth) {
        setInterval(() => !document.hidden && Alpine.store('badges').refresh(), 30000);
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
                return (m.body ?? this.plain(m.html)).replace(/\s+/g, ' ').slice(0, 120);
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
                const text = e.clipboardData?.getData('text/plain') ?? '';
                if (this.draft.length + text.length > this.maxLength) toast(`Xabar ${this.maxLength} belgidan oshmasin.`, 'error');
            },
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
                if (!text) return;

                if (this.editing) {
                    const target = this.editing;
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
                if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
                    toast(window.isSecureContext ? 'Brauzeringiz ovoz yozishni qo‘llab-quvvatlamaydi.' : 'Ovoz yozish uchun sayt HTTPS orqali ochilishi kerak.', 'error');
                    return;
                }
                this.stopAudio();
                this.rec = { state: 'starting', seconds: 0, live: [], levels: [] };
                try {
                    stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true } });
                } catch (err) {
                    this.rec.state = 'idle';
                    toast(err?.name === 'NotAllowedError' ? 'Mikrofonga ruxsat berilmadi. Brauzer sozlamalaridan ruxsat bering.' : 'Mikrofon topilmadi.', 'error');
                    return;
                }

                // Hamma qurilmada ijro bo‘ladigan formatni tanlaymiz: avval MP4/AAC, keyin WebM/Opus.
                const type = ['audio/mp4;codecs=mp4a.40.2', 'audio/mp4', 'audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus'].find((t) => MediaRecorder.isTypeSupported?.(t));
                const chunks = [];
                recorder = new MediaRecorder(stream, type ? { mimeType: type, audioBitsPerSecond: 48000 } : undefined);
                recorder.ondataavailable = (e) => e.data.size && chunks.push(e.data);
                recorder.onstop = () => {
                    const shouldSend = this.rec.state === 'sending';
                    const seconds = Math.max(1, Math.round(this.rec.seconds));
                    const levels = this.rec.levels.slice();
                    this.cleanupRecorder();
                    if (shouldSend && chunks.length) {
                        const blob = new Blob(chunks, { type: recorder?.mimeType || type || 'audio/webm' });
                        this.sendVoice(blob, seconds, levels);
                    }
                    this.rec = { state: 'idle', seconds: 0, live: [], levels: [] };
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

                const started = performance.now();
                clockTimer = setInterval(() => {
                    this.rec.seconds = (performance.now() - started) / 1000;
                    if (this.rec.seconds >= cfg.maxVoice) this.finishRecording();
                }, 200);
                recorder.start(250);
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

                const xhr = new XMLHttpRequest();
                xhr.open('POST', cfg.urls.send);
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.setRequestHeader('X-CSRF-TOKEN', csrf());
                xhr.upload.onprogress = (e) => {
                    const m = this.messages.find((x) => x.key === temp.key);
                    if (m && e.lengthComputable) m.progress = e.loaded / e.total;
                };
                xhr.onload = () => {
                    let data = {};
                    try {
                        data = JSON.parse(xhr.responseText);
                    } catch {
                        /* JSON emas */
                    }
                    if (xhr.status === 201) {
                        this.settle(temp, data.message);
                        return;
                    }
                    const m = this.messages.find((x) => x.key === temp.key);
                    if (m) Object.assign(m, { pending: false, uploading: false, failed: true, retryReply: replyId });
                    toast(data.message || (xhr.status === 429 ? 'Juda tez yuboryapsiz — birozdan keyin.' : 'Ovozli xabar yuborilmadi.'), 'error');
                };
                xhr.onerror = () => {
                    const m = this.messages.find((x) => x.key === temp.key);
                    if (m) Object.assign(m, { pending: false, uploading: false, failed: true, retryReply: replyId });
                    toast('Internet aloqasini tekshiring.', 'error');
                };
                xhr.send(form);
            },
        };
    });
}
