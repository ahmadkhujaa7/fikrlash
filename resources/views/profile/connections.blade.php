@extends('layouts.app')
@section('title', ($tab === 'followers' ? 'Obunachilar' : 'Obunalar').' — '.$user->name)

@section('content')
    <div class="flex items-center gap-3 px-2 py-2">
        <a href="{{ $user->profileUrl() }}" class="btn-ghost rounded-full p-2" aria-label="Profilga qaytish"><x-ico name="arrow-left" /></a>
        <div>
            <h1 class="font-semibold leading-tight">{{ $user->name }}</h1>
            <p class="text-sm text-muted">{{ '@'.$user->username }}</p>
        </div>
    </div>
    <x-tabs :items="[
        ['label' => 'Obunachilar', 'href' => route('profile.followers', $user->username), 'active' => $tab === 'followers'],
        ['label' => 'Obunalar', 'href' => route('profile.following', $user->username), 'active' => $tab === 'following'],
    ]" />

    <ul class="stream">
        @forelse ($people as $person)
            <li>@include('partials.user-row', ['person' => $person])</li>
        @empty
            <x-empty-state icon="users" :title="$tab === 'followers' ? 'Hali obunachi yo‘q' : 'Hali hech kimga obuna bo‘linmagan'" />
        @endforelse
    </ul>
    {{ $people->links() }}
@endsection
