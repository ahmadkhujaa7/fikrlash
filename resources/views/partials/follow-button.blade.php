@php
    $small = $small ?? false;
    // quiet: yon paneldagi ro‘yxatlarda — obuna bo‘lmaganda ham chiziqli (sokin) ko‘rinish
    $idle = ($quiet ?? false) ? 'btn-secondary' : 'btn-primary';
@endphp
<button type="button"
        x-data="follow({ following: {{ $following ? 'true' : 'false' }}, url: '{{ route('api.v1.users.follow', $target->username) }}' })"
        @click="flip" @mouseenter="hover = true" @mouseleave="hover = false" :disabled="busy"
        @if ($idle === 'btn-primary') :class="{ 'btn-secondary': following, 'btn-primary': !following }" @endif
        class="btn {{ $small ? 'btn-sm' : '' }} {{ $following ? 'btn-secondary' : $idle }}"
        aria-pressed="{{ $following ? 'true' : 'false' }}" :aria-pressed="following">
    <span x-text="following ? (hover ? 'Bekor qilish' : 'Obunadasiz') : 'Obuna bo‘lish'">{{ $following ? 'Obunadasiz' : 'Obuna bo‘lish' }}</span>
</button>
