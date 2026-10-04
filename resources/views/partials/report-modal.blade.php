@auth
<x-modal title="Nima noto‘g‘ri?">
    <form @submit.prevent="send" class="space-y-2">
        <p class="mb-3 text-sm text-muted">Shikoyatingizni moderatorlar ko‘rib chiqadi. Kim yuborgani muallifga ko‘rsatilmaydi.</p>
        @foreach (\App\Enums\ReportReason::cases() as $reason)
            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line px-4 py-3 text-sm has-[:checked]:border-ink has-[:checked]:bg-sunken">
                <input type="radio" name="reason" value="{{ $reason->value }}" x-model="reason" class="accent-[var(--ink)]">
                {{ $reason->getLabel() }}
            </label>
        @endforeach
        <textarea x-model="description" maxlength="1000" rows="2" class="field mt-2" placeholder="Qo‘shimcha izoh (ixtiyoriy)"></textarea>
        <div class="flex justify-end gap-2 pt-3">
            <button type="button" class="btn btn-secondary" @click="open = false">Bekor qilish</button>
            <button type="submit" class="btn btn-primary" :disabled="sending">Yuborish</button>
        </div>
    </form>
</x-modal>
@endauth
