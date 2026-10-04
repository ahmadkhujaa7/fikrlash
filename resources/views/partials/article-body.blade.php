{{--
  Maqola tanasi. Har bir matn bo‘lagi ContentFormatter orqali escape qilinadi (rich: **qalin**, *kursiv*,
  havolalar, @eslatma va #teglar). Xom HTML hech qachon chiqmaydi. Rasmlar — width/height bilan (sahifa sakramaydi).
--}}
@php
    use App\Models\Post;
    use App\Support\ContentFormatter;
@endphp
<div class="article-body">
    @foreach ($post->blocks ?? [] as $block)
        @switch($block['type'] ?? null)
            @case('p')
                <p>{!! ContentFormatter::toHtml($block['text'], rich: true) !!}</p>
                @break
            @case('h')
                <h2 id="bolim-{{ $loop->index }}">{{ $block['text'] }}</h2>
                @break
            @case('quote')
                <blockquote><p>{!! ContentFormatter::toHtml($block['text'], rich: true) !!}</p></blockquote>
                @break
            @case('list')
                @php($tag = ! empty($block['ordered']) ? 'ol' : 'ul')
                <{{ $tag }}>
                    @foreach ($block['items'] ?? [] as $item)
                        <li>{!! ContentFormatter::toHtml($item, rich: true) !!}</li>
                    @endforeach
                </{{ $tag }}>
                @break
            @case('image')
                <figure>
                    <a href="{{ Post::mediaUrl($block['path']) }}" target="_blank" rel="noopener">
                        <img src="{{ Post::mediaUrl($block['path']) }}" alt="{{ $block['caption'] ?? '' }}" loading="{{ $loop->index < 3 ? 'eager' : 'lazy' }}" decoding="async"
                             @if (! empty($block['w'])) width="{{ $block['w'] }}" height="{{ $block['h'] }}" @endif>
                    </a>
                    @if (! empty($block['caption']))
                        <figcaption>{{ $block['caption'] }}</figcaption>
                    @endif
                </figure>
                @break
            @case('hr')
                <hr>
                @break
        @endswitch
    @endforeach
</div>
