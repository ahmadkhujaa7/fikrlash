<?php

namespace Tests\Unit;

use App\Support\ContentFormatter;
use Tests\TestCase;

class ContentFormatterTest extends TestCase
{
    public function test_escapes_html_and_script(): void
    {
        $html = ContentFormatter::toHtml('<script>alert(1)</script> <img src=x onerror=alert(1)>');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_links_mentions_hashtags_and_urls_safely(): void
    {
        $html = ContentFormatter::toHtml("Salom @Aziz_1 #Ta'lim https://fikrlash.uz/test?a=1&b=2.");

        $this->assertStringContainsString('href="'.route('profile.show', 'aziz_1').'"', $html);
        $this->assertStringContainsString('href="'.route('tags.show', 'talim').'"', $html);
        $this->assertStringContainsString('href="https://fikrlash.uz/test?a=1&amp;b=2"', $html);
        $this->assertStringContainsString('rel="nofollow ugc noopener noreferrer"', $html);
        $this->assertStringEndsWith('.', strip_tags($html));
    }

    public function test_javascript_urls_are_not_linked(): void
    {
        $html = ContentFormatter::toHtml('javascript:alert(1) "><a href=x>');

        $this->assertStringNotContainsString('href="javascript', $html);
        $this->assertStringNotContainsString('<a href=x>', $html);
    }

    public function test_quotes_inside_url_cannot_break_attribute(): void
    {
        $html = ContentFormatter::toHtml('https://evil.test/"onmouseover="alert(1)');

        $this->assertStringNotContainsString('"onmouseover', $html);
    }

    public function test_emails_are_not_mentions(): void
    {
        $this->assertSame([], ContentFormatter::mentions('yozing: info@fikrlash.uz'));
        $this->assertSame(['ali', 'vali'], ContentFormatter::mentions('@Ali va @vali, yana @ali'));
    }

    public function test_extracts_hashtags_but_not_url_fragments(): void
    {
        $tags = ContentFormatter::hashtags('#AI va #biznes https://site.uz/#anchor');

        $this->assertSame(['ai' => 'AI', 'biznes' => 'biznes'], $tags);
    }

    public function test_bare_domains_become_links(): void
    {
        $html = ContentFormatter::toHtml('Saytimiz fikrlash.uz, batafsil www.kun.uz/news/1 da.');

        $this->assertStringContainsString('<a href="https://fikrlash.uz" class="link"', $html);
        $this->assertStringContainsString('>fikrlash.uz</a>,', $html); // vergul havoladan tashqarida
        $this->assertStringContainsString('<a href="https://www.kun.uz/news/1" class="link"', $html);
        $this->assertStringContainsString('>kun.uz/news/1</a> da.', $html);
    }

    public function test_things_that_only_look_like_domains_stay_text(): void
    {
        foreach (['info@fikrlash.uz', 'Node.js va v2.0', 'fayl.txt', 'a.b'] as $text) {
            $this->assertStringNotContainsString('<a ', ContentFormatter::toHtml($text), $text);
        }
        // Domen ichida HTML/qo‘shtirnoq atributni buzolmaydi.
        $this->assertStringNotContainsString('"onclick', ContentFormatter::toHtml('sayt.uz/"onclick="x'));
    }
}
