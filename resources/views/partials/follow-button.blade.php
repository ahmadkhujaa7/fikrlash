@php $small = $small ?? false; @endphp
<button type="button"
        x-data="follow({ following: {{ $following ? 'true' : 'false' }}, url: '{{ route('api.v1.users.follow', $target->username) }}' })"
        @click="flip" @mouseenter="hover = true" @mouseleave="hover = false" :disabled="busy"
        :class="following ? 'btn-secondary' : 'btn-primary'"
        class="btn {{ $small ? 'btn-sm' : '' }}"
        :aria-pressed="following">
    <span x-text="following ? (hover ? 'Obunani bekor qilish' : 'Obunadasiz') : 'Obuna bo‘lish'">{{ $following ? 'Obunadasiz' : 'Obuna bo‘lish' }}</span>
</button>
