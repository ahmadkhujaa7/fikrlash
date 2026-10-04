@extends('layouts.app')
@section('title', $category->name)
@section('description', $category->description ?: $category->name.' mavzusidagi fikrlar — Fikrlash.uz')

@section('content')
    <x-page-header :title="$category->name" :text="$category->description" class="border-b border-line" />
    @include('partials.feed', ['emptyTitle' => 'Bu mavzuda hali fikr yo‘q', 'emptyText' => 'Birinchi bo‘lib yozing!'])
@endsection
