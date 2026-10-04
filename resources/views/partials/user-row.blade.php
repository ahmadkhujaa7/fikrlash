@php
    $me = auth()->user();
    $following = $following ?? (isset($followingIds) ? isset($followingIds[$person->id]) : ($me ? $me->isFollowing($person) : false));
@endphp
<div class="flex items-center gap-3 px-4 py-3 sm:px-6">
    <a href="{{ $person->profileUrl() }}"><x-avatar :user="$person" /></a>
    <a href="{{ $person->profileUrl() }}" class="min-w-0 flex-1">
        <span class="flex items-center gap-1 font-semibold"><span class="truncate">{{ $person->name }}</span><x-verified :user="$person" /></span>
        <span class="block truncate text-sm text-muted">{{ '@'.$person->username }}</span>
        @if ($person->bio)<span class="mt-0.5 line-clamp-1 block text-sm text-ink-soft">{{ $person->bio }}</span>@endif
    </a>
    @if ($me && ! $me->is($person))
        @include('partials.follow-button', ['target' => $person, 'following' => $following, 'small' => true])
    @endif
</div>
