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
}
