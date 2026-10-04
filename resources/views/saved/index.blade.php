@extends('layouts.app')
@section('title', 'Saqlanganlar')

@section('content')
    <x-page-header title="Saqlanganlar" text="Keyinroq o‘qish uchun belgilagan fikrlaringiz. Faqat sizga ko‘rinadi." class="border-b border-line" />
    @include('partials.feed', ['emptyTitle' => 'Hali hech narsa saqlanmagan', 'emptyText' => 'Post ostidagi belgi orqali keyinroq o‘qish uchun saqlab qo‘ying.'])
@endsection
