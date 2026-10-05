@extends('layouts.app')
@section('title', $other ? $other->name.' — xabarlar' : 'Xabarlar')
@section('screen', '1')

@section('sidebar')
    @if ($other)
        <div class="space-y-5 px-1">
            <section class="rounded-2xl border border-line bg-paper p-5 text-center">
                <a href="{{ $other->profileUrl() }}" class="inline-block"><x-avatar :user="$other" size="lg" /></a>
                <p class="mt-3 flex items-center justify-center gap-1 font-medium text-ink">{{ $other->name }}<x-verified :user="$other" /></p>
                <p class="text-[13px] text-muted">{{ '@'.$other->username }}</p>
                @if ($other->bio)<p class="mt-3 text-[13px] leading-relaxed text-ink-soft">{{ \Illuminate\Support\Str::limit($other->bio, 160) }}</p>@endif
                <a href="{{ $other->profileUrl() }}" class="btn btn-secondary btn-sm mt-4">Profilni ko‘rish</a>
            </section>
            <dl class="space-y-2.5 px-1 text-[13px] text-ink-soft">
                <div class="flex items-center justify-between gap-3"><dt>Yuborish</dt><dd><kbd class="kbd">Enter</kbd></dd></div>
                <div class="flex items-center justify-between gap-3"><dt>Yangi qator</dt><dd><kbd class="kbd">Shift</kbd> + <kbd class="kbd">Enter</kbd></dd></div>
                <div class="flex items-center justify-between gap-3"><dt>Oxirgisini tahrirlash</dt><dd><kbd class="kbd">↑</kbd></dd></div>
                <div class="flex items-center justify-between gap-3"><dt>Tez reaksiya</dt><dd>ikki marta bosish</dd></div>
            </dl>
        </div>
    @endif
@endsection

@section('content')
<div x-data="messageThread(@js($config))" class="chat-screen" :style="viewport" @keydown.escape.window="sheet.open ? closeSheet() : cancelContext()">

    {{-- Ilova paneli: suhbatdosh, holati, amallar --}}
    <div class="app-bar !top-0 !gap-1.5 !pr-1.5">
        <a href="{{ route('messages.index') }}" @click.prevent="backOr($el.href)" class="icon-btn" aria-label="Xabarlarga qaytish"><x-ico name="arrow-left" /></a>
        @if ($other)
            <a href="{{ $other->profileUrl() }}" class="flex min-w-0 flex-1 items-center gap-3 rounded-full py-1 pr-2">
                <span class="relative shrink-0">
                    <x-avatar :user="$other" size="sm" />
                    <span x-show="presence.online" x-cloak class="absolute -bottom-0.5 -right-0.5 size-3 rounded-full bg-firuza ring-[2.5px] ring-paper"></span>
                </span>
                <span class="min-w-0 leading-tight">
                    <span class="flex items-center gap-1 text-[15px] font-medium text-ink"><span class="truncate">{{ $other->name }}</span><x-verified :user="$other" /></span>
                    <span class="block truncate text-[12px]" :class="peerTyping ? 'text-lapis' : (presence.online ? 'text-firuza' : 'text-muted')"
                          x-text="peerTyping ? 'yozmoqda…' : presence.label">{{ '@'.$other->username }}</span>
                </span>
            </a>
            <x-dropdown label="Suhbat amallari">
                <x-slot:trigger class="!p-2.5"><x-ico name="dots" /></x-slot:trigger>
                <x-dropdown-item icon="user" :href="$other->profileUrl()">Profilni ko‘rish</x-dropdown-item>
                <form method="POST" action="{{ route($config['blocked'] ? 'messages.unblock' : 'messages.block', $conversation) }}">
                    @csrf
                    @if ($config['blocked']) @method('DELETE') @endif
                    <x-dropdown-item icon="block" type="submit">{{ $config['blocked'] ? 'Blokdan chiqarish' : 'Bloklash' }}</x-dropdown-item>
                </form>
                <x-dropdown-item icon="flag" @click="$dispatch('report', { type: 'user', id: {{ $other->id }} }); open = false">Shikoyat qilish</x-dropdown-item>
                <form method="POST" action="{{ route('messages.clear', $conversation) }}" class="border-t border-line" x-data
                      @submit="confirm('Suhbat siz uchun tozalansinmi? Suhbatdoshingizda xabarlar qoladi.') || $event.preventDefault()">
                    @csrf
                    <x-dropdown-item icon="trash" type="submit" danger>Suhbatni tozalash</x-dropdown-item>
                </form>
            </x-dropdown>
        @else
            <p class="flex-1 text-[15px] font-medium">Suhbat</p>
        @endif
    </div>

    {{-- Xabarlar --}}
    <div x-ref="scroller" class="chat-scroll relative flex-1 overflow-y-auto overscroll-contain" @scroll.passive="onScroll()">
        <div class="flex min-h-full flex-col justify-end px-3 pb-3 pt-4 sm:px-5">
            <div x-show="loadingOlder" class="flex justify-center py-3"><x-spinner /></div>

            <template x-if="!messages.length">
                <div class="mx-auto my-10 max-w-xs text-center">
                    @if ($other)<x-avatar :user="$other" size="lg" class="mx-auto" />@endif
                    <p class="mt-4 font-serif text-xl text-ink">Suhbatni boshlang</p>
                    <p class="mt-1.5 text-[14px] leading-relaxed text-muted">Matn yozing yoki mikrofon tugmasini bosib ovozli xabar yuboring.</p>
                </div>
            </template>

            <template x-for="item in items" :key="item.key">
                <div>
                    {{-- Kun ajratgichi --}}
                    <template x-if="item.kind === 'day'">
                        <div class="pointer-events-none sticky top-1 z-10 my-3 flex justify-center">
                            <span class="rounded-full bg-paper/90 px-3 py-1 text-[12px] font-medium text-muted shadow-[0_1px_2px_rgb(0_0_0/0.06)] ring-1 ring-line backdrop-blur" x-text="item.label"></span>
                        </div>
                    </template>

                    {{-- Xabar --}}
                    <template x-if="item.kind === 'msg'">
                        <div class="msg-row group/msg flex items-end gap-1.5" :id="'m-' + item.m.id"
                             :class="[item.m.mine ? 'flex-row-reverse' : '', item.first ? 'mt-2.5' : 'mt-[3px]', item.m.reactions.length ? 'mb-6' : '', flashId === item.m.id ? 'msg-flash' : '']">
                            <div class="relative min-w-0 max-w-[84%] sm:max-w-[72%]">
                                <div class="bubble select-text" :class="bubbleClass(item)"
                                     @dblclick.prevent="quickReact(item.m)" @contextmenu.prevent="openSheet(item.m)"
                                     @touchstart.passive="pressStart(item.m)" @touchend="pressEnd()" @touchmove.passive="pressEnd()" @touchcancel="pressEnd()">

                                    {{-- Javob berilgan xabar --}}
                                    <template x-if="item.m.reply">
                                        <button type="button" class="reply-quote" @click="jumpTo(item.m.reply.id)">
                                            <span class="block truncate text-[12px] font-semibold" x-text="item.m.reply.name"></span>
                                            <span class="block truncate text-[13px] opacity-80" x-text="item.m.reply.text"></span>
                                        </button>
                                    </template>

                                    <template x-if="item.m.removed">
                                        <span class="flex items-center gap-1.5 italic opacity-75"><x-ico name="block" size="size-4" /> Xabar o‘chirildi</span>
                                    </template>

                                    <template x-if="!item.m.removed && item.m.type === 'text'">
                                        <span class="msg-text" x-html="item.m.html"></span>
                                    </template>

                                    {{-- Ovozli xabar: o‘ynatish, to‘lqin shakli (bosib aylantirish), vaqt --}}
                                    <template x-if="!item.m.removed && item.m.type === 'voice'">
                                        <span class="flex min-w-[216px] items-center gap-3 py-0.5">
                                            <button type="button" class="voice-btn" @click="togglePlay(item.m)" :aria-label="isPlaying(item.m) ? 'To‘xtatish' : 'Eshitish'" :disabled="!item.m.voice?.url">
                                                <template x-if="item.m.uploading"><x-spinner class="!size-4 !text-current" /></template>
                                                <template x-if="!item.m.uploading && !isPlaying(item.m)"><x-ico name="play" size="size-[18px]" solid class="translate-x-[1px]" /></template>
                                                <template x-if="!item.m.uploading && isPlaying(item.m)"><x-ico name="pause" size="size-[18px]" stroke-width="2.6" /></template>
                                            </button>
                                            <span class="min-w-0 flex-1">
                                                <span class="flex h-8 cursor-pointer items-center gap-[2px]" @click="seek($event, item.m)">
                                                    <template x-for="(h, i) in bars(item.m)" :key="i">
                                                        <span class="w-[3px] shrink-0 rounded-full transition-colors" :style="`height:${h}%`"
                                                              :class="i / 36 < progressOf(item.m) ? (item.m.mine ? 'bg-white' : 'bg-lapis') : (item.m.mine ? 'bg-white/40' : 'bg-ink/20')"></span>
                                                    </template>
                                                </span>
                                                <span class="mt-0.5 block text-[11px] tabular-nums opacity-80" x-text="item.m.uploading ? 'Yuborilmoqda… ' + Math.round((item.m.progress || 0) * 100) + '%' : voiceLabel(item.m)"></span>
                                            </span>
                                        </span>
                                    </template>

                                    {{-- Vaqt, "tahrirlangan", yetkazildi/o‘qildi --}}
                                    <span class="msg-meta" :class="item.m.type === 'voice' && !item.m.removed ? 'msg-meta-block' : ''">
                                        <span x-show="item.m.edited" class="mr-1">tahrirlangan</span>
                                        <span x-text="item.m.time"></span>
                                        <template x-if="item.m.mine && item.m.failed">
                                            <button type="button" class="ml-1 font-semibold text-white underline" @click="retry(item.m)">qayta yuborish</button>
                                        </template>
                                        <template x-if="item.m.mine && !item.m.failed">
                                            <span class="ml-0.5 inline-flex" :title="item.m.pending ? 'Yuborilmoqda' : (isRead(item.m) ? 'O‘qildi' : 'Yetkazildi')">
                                                <template x-if="item.m.pending"><x-ico name="clock" size="size-3.5" /></template>
                                                <template x-if="!item.m.pending">
                                                    <svg class="h-3.5 w-[18px]" viewBox="0 0 18 14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                        <path d="m1.5 7.5 3.5 3.5L12 3.5" /><path x-show="isRead(item.m)" d="m7.5 10.5.5.5 7.5-7.5" />
                                                    </svg>
                                                </template>
                                            </span>
                                        </template>
                                    </span>
                                </div>

                                {{-- Reaksiyalar --}}
                                <div x-show="item.m.reactions.length" class="absolute left-2 top-full -mt-1.5 flex gap-1">
                                    <template x-for="r in item.m.reactions" :key="r.emoji">
                                        <button type="button" class="reaction-chip" :class="r.mine && 'is-mine'" @click="react(item.m, r.emoji)" :aria-label="r.emoji + ' ' + r.count">
                                            <span x-text="r.emoji"></span><span x-show="r.count > 1" class="text-[11px] font-semibold tabular-nums" x-text="r.count"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            {{-- Kompyuterda: sichqoncha bilan kelganda — reaksiya va amallar --}}
                            <div class="mb-1 hidden shrink-0 items-center gap-0.5 opacity-0 transition-opacity group-hover/msg:opacity-100 md:flex" x-show="!item.m.removed && !item.m.pending">
                                <button type="button" class="grid size-8 place-items-center rounded-full text-muted hover:bg-sunken hover:text-ink" @click="openSheet(item.m)" aria-label="Reaksiya va amallar"><x-ico name="smile" size="size-[18px]" /></button>
                                <button type="button" class="grid size-8 place-items-center rounded-full text-muted hover:bg-sunken hover:text-ink" @click="startReply(item.m)" aria-label="Javob berish"><x-ico name="reply" size="size-[18px]" /></button>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Suhbatdosh yozmoqda --}}
            <div x-show="peerTyping" x-cloak class="mt-2 flex">
                <span class="bubble bubble-theirs flex items-center gap-1 !px-4 !py-3" aria-label="yozmoqda">
                    <span class="typing-dot"></span><span class="typing-dot [animation-delay:.15s]"></span><span class="typing-dot [animation-delay:.3s]"></span>
                </span>
            </div>
        </div>
    </div>

    {{-- Pastda emasmiz va yangi xabar keldi --}}
    <div class="pointer-events-none relative h-0">
        <button type="button" x-show="!atBottom" x-cloak x-transition.opacity @click="scrollToBottom(true)"
                class="pointer-events-auto absolute -top-14 right-4 flex h-10 items-center gap-1.5 rounded-full bg-paper px-3 text-[13px] font-medium text-ink shadow-[var(--shadow-pop)] ring-1 ring-line">
            <span x-show="newCount" class="grid h-5 min-w-5 place-items-center rounded-full bg-lapis px-1 text-[11px] text-white" x-text="newCount"></span>
            <x-ico name="arrow-down" size="size-4" />
        </button>
    </div>

    {{-- Yozish paneli --}}
    <div class="chat-composer">
        <template x-if="cannotSend">
            <p class="px-4 py-3 text-center text-[13px] leading-relaxed text-muted" x-text="cannotSend"></p>
        </template>
        <template x-if="!cannotSend">
            <div>
                {{-- Javob / tahrirlash --}}
                <div x-show="replyTo || editing" x-cloak class="mb-2 flex items-center gap-3 rounded-2xl bg-sunken px-3 py-2">
                    <span class="text-lapis"><template x-if="editing"><x-ico name="pencil" size="size-[18px]" /></template><template x-if="!editing"><x-ico name="reply" size="size-[18px]" /></template></span>
                    <span class="min-w-0 flex-1 border-l-2 border-lapis pl-2.5">
                        <span class="block text-[12px] font-semibold text-lapis" x-text="editing ? 'Tahrirlash' : 'Javob: ' + (replyTo?.mine ? 'o‘zingizga' : replyTo?.peerName)"></span>
                        <span class="block truncate text-[13px] text-ink-soft" x-text="contextText"></span>
                    </span>
                    <button type="button" class="grid size-8 place-items-center rounded-full text-muted hover:bg-paper hover:text-ink" @click="cancelContext()" aria-label="Bekor qilish"><x-ico name="x" size="size-4" /></button>
                </div>

                {{-- Ovoz yozilmoqda --}}
                <div x-show="rec.state !== 'idle'" x-cloak class="flex h-12 items-center gap-2">
                    <button type="button" class="grid size-11 shrink-0 place-items-center rounded-full text-anor hover:bg-anor-soft" @click="cancelRecording()" aria-label="Bekor qilish"><x-ico name="trash" /></button>
                    <span class="flex min-w-0 flex-1 items-center gap-3 rounded-full bg-sunken px-4 py-2.5">
                        <span class="rec-dot size-2.5 shrink-0 rounded-full bg-anor"></span>
                        <span class="w-11 shrink-0 text-[14px] font-medium tabular-nums text-ink" x-text="clock(rec.seconds)"></span>
                        <span class="flex h-6 min-w-0 flex-1 items-center justify-end gap-[2px] overflow-hidden">
                            <template x-for="(h, i) in rec.live" :key="i"><span class="w-[3px] shrink-0 rounded-full bg-lapis/70" :style="`height:${h}%`"></span></template>
                        </span>
                    </span>
                    <button type="button" class="send-btn" @click="finishRecording()" :disabled="rec.state !== 'recording'" aria-label="Ovozli xabarni yuborish"><x-ico name="send" size="size-5" /></button>
                </div>

                {{-- Matn --}}
                <div x-show="rec.state === 'idle'" class="flex items-end gap-2">
                    <label class="flex min-h-11 min-w-0 flex-1 items-end rounded-[22px] border border-line bg-sunken/70 px-4 py-[9px] transition-colors focus-within:border-line-strong focus-within:bg-paper">
                        <span class="sr-only">Xabar</span>
                        <textarea x-ref="input" x-model="draft" rows="1" :maxlength="maxLength" placeholder="Xabar yozing…" enterkeyhint="send"
                                  @input="onDraftInput()" @keydown="onComposerKey($event)" @paste="onPaste($event)"
                                  class="block max-h-36 w-full resize-none border-0 bg-transparent p-0 text-[15.5px] leading-[1.45] text-ink placeholder:text-muted/80 focus:outline-none focus:ring-0"></textarea>
                    </label>
                    <button type="button" x-show="draft.trim() || editing" class="send-btn" @click="submit()" :disabled="!draft.trim()" :aria-label="editing ? 'Saqlash' : 'Yuborish'">
                        <template x-if="editing"><x-ico name="check" size="size-5" stroke-width="2.2" /></template>
                        <template x-if="!editing"><x-ico name="send" size="size-5" /></template>
                    </button>
                    <button type="button" x-show="!draft.trim() && !editing" class="grid size-11 shrink-0 place-items-center rounded-full bg-sunken text-ink-soft transition-colors hover:bg-lapis-soft hover:text-lapis"
                            @click="startRecording()" aria-label="Ovozli xabar yozish" title="Ovozli xabar"><x-ico name="mic" /></button>
                </div>
            </div>
        </template>
    </div>

    {{-- Xabar amallari: reaksiya, javob, nusxa, tahrir, o‘chirish (mobil — pastdan chiqadi) --}}
    <div x-show="sheet.open" x-cloak class="fixed inset-0 z-[60]" role="dialog" aria-modal="true" aria-label="Xabar amallari">
        <div class="absolute inset-0 bg-ink/35 backdrop-blur-[2px]" @click="closeSheet()" x-show="sheet.open" x-transition.opacity></div>
        <div class="absolute inset-x-0 bottom-0 mx-auto max-w-md rounded-t-[26px] bg-paper p-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] shadow-[var(--shadow-pop)] md:bottom-auto md:top-1/2 md:-translate-y-1/2 md:rounded-[26px]"
             x-show="sheet.open" x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-6 opacity-0 md:translate-y-[-46%]" x-transition:enter-end="translate-y-0 opacity-100 md:-translate-y-1/2">
            <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-line-strong md:hidden"></div>
            <template x-if="sheet.m && !sheet.m.removed">
                <div class="flex justify-between gap-1 rounded-full bg-sunken p-1.5">
                    <template x-for="e in reactions" :key="e">
                        <button type="button" class="grid size-11 place-items-center rounded-full text-[1.6rem] leading-none transition-transform hover:scale-110 active:scale-95"
                                :class="myReaction(sheet.m) === e && 'bg-paper shadow-sm ring-1 ring-lapis/40'" @click="react(sheet.m, e); closeSheet()" x-text="e" :aria-label="e"></button>
                    </template>
                </div>
            </template>
            <div class="mt-2 overflow-hidden rounded-2xl">
                <template x-if="sheet.m && !sheet.m.removed && !cannotSend">
                    <button type="button" class="sheet-item" @click="startReply(sheet.m); closeSheet()"><x-ico name="reply" /> Javob berish</button>
                </template>
                <template x-if="sheet.m && sheet.m.type === 'text' && !sheet.m.removed">
                    <button type="button" class="sheet-item" @click="copy(sheet.m); closeSheet()"><x-ico name="copy" /> Nusxa olish</button>
                </template>
                <template x-if="sheet.m && sheet.m.mine && sheet.m.type === 'text' && !sheet.m.removed && !cannotSend">
                    <button type="button" class="sheet-item" @click="startEdit(sheet.m); closeSheet()"><x-ico name="pencil" /> Tahrirlash</button>
                </template>
                <template x-if="sheet.m && sheet.m.mine && !sheet.m.removed">
                    <button type="button" class="sheet-item !text-anor" @click="sheet.confirm ? remove(sheet.m) : (sheet.confirm = true)">
                        <x-ico name="trash" /> <span x-text="sheet.confirm ? 'Ha, hamma uchun o‘chirilsin' : 'O‘chirish'"></span>
                    </button>
                </template>
            </div>
            <button type="button" class="btn btn-secondary mt-3 w-full md:hidden" @click="closeSheet()">Yopish</button>
        </div>
    </div>
</div>
@endsection
