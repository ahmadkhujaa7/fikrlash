@extends('layouts.app')
@section('title', 'Qoralamalar')

@section('content')
    <x-page-header title="Qoralamalar" text="Faqat sizga ko‘rinadi. Tayyor bo‘lganda chop eting." class="border-b border-line" />
    @forelse ($posts as $post)
        <a href="{{ route('posts.edit', $post) }}" class="block border-b border-line px-5 py-4 hover:bg-paper/60">
            <p class="prose-post line-clamp-3">{{ $post->content }}</p>
            <p class="mt-2 text-sm text-muted">Oxirgi o‘zgarish: {{ \App\Support\Time::full($post->updated_at) }}</p>
        </a>
    @empty
        <x-empty-state icon="document" title="Qoralama yo‘q" text="Post yozayotganda “Qoralama” tugmasini bosing — u shu yerda saqlanadi.">
            <a href="{{ route('posts.create') }}" class="btn btn-primary">Fikr yozish</a>
        </x-empty-state>
    @endforelse
    {{ $posts->links() }}
@endsection
