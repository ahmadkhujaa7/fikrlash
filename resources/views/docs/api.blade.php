@extends('layouts.app')
@section('title', 'API qo‘llanma')

@php
    $spec = json_decode(file_get_contents(base_path('docs/openapi.json')), true);
    $groups = [];
    foreach ($spec['paths'] as $path => $ops) {
        foreach ($ops as $method => $op) {
            $groups[$op['tags'][0]][] = ['method' => strtoupper($method), 'path' => $path, 'summary' => $op['summary'], 'auth' => isset($op['security'])];
        }
    }
    $colors = ['GET' => 'firuza', 'POST' => 'lapis', 'PATCH' => 'amber', 'PUT' => 'amber', 'DELETE' => 'anor'];
@endphp

@section('content')
    <article class="px-4 pb-16 pt-12 sm:px-6">
        <h1 class="display !text-[2.25rem]">Fikrlash.uz API</h1>
        <p class="mt-3 text-ink-soft">REST API, versiya 1. Asosiy manzil: <code class="rounded bg-sunken px-1.5 py-0.5 text-sm">{{ url('/api/v1') }}</code></p>

        <h2 class="mt-8 text-lg font-semibold">Autentifikatsiya</h2>
        <ol class="mt-2 list-decimal space-y-1 pl-5 text-[15px] text-ink-soft">
            <li><code>POST /auth/login</code> ga <code>login</code> (telefon yoki username) va <code>password</code> yuboring.</li>
            <li>Javobdagi <code>data.token</code> ni har so‘rovda yuboring: <code>Authorization: Bearer &lt;token&gt;</code></li>
            <li>Tokenlar 90 kun amal qiladi; <a href="/admin/api-tokens" class="text-lapis hover:underline">sozlamalarda</a> yoki <code>DELETE /tokens/{id}</code> orqali bekor qilinadi.</li>
        </ol>
        <pre class="mt-4 overflow-x-auto rounded-2xl bg-ink p-4 text-sm text-paper"><code>curl -X POST {{ url('/api/v1/auth/login') }} \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"login":"+998901234567","password":"parol","device_name":"cli"}'

{"success":true,"message":"Xush kelibsiz!","data":{"token":"1|abc…","token_type":"Bearer",…}}</code></pre>

        <h2 class="mt-8 text-lg font-semibold">Javob formati</h2>
        <p class="mt-2 text-[15px] text-ink-soft">Muvaffaqiyat: <code>{"success": true, "message", "data", "meta"}</code>. Xato: <code>{"success": false, "message", "errors"}</code> — HTTP kodlari: 401, 403, 404, 422, 429.
            Ro‘yxatlar cursor bilan sahifalanadi: <code>meta.pagination.next_cursor</code> → <code>?cursor=…</code></p>

        <p class="mt-4"><a href="{{ route('docs.openapi') }}" class="btn btn-secondary btn-sm">OpenAPI (JSON) yuklab olish</a></p>

        @foreach ($groups as $tag => $endpoints)
            <h2 class="mt-12 font-serif text-2xl font-medium">{{ $tag }}</h2>
            <ul class="mt-3 divide-y divide-line rounded-2xl border border-line">
                @foreach ($endpoints as $e)
                    <li class="flex flex-wrap items-baseline gap-x-3 gap-y-1 px-4 py-3">
                        <x-badge :tone="$colors[$e['method']] ?? 'neutral'" class="w-16 justify-center font-mono">{{ $e['method'] }}</x-badge>
                        <code class="text-sm font-medium">{{ $e['path'] }}</code>
                        @if ($e['auth'])<span title="Token talab qilinadi"><x-ico name="lock" size="size-4" class="text-muted" /></span>@endif
                        <span class="basis-full text-sm text-muted sm:basis-auto">{{ $e['summary'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endforeach
    </article>
@endsection
