<?php

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HtmlSanitizerTest extends TestCase
{
    #[Test]
    public function it_strips_scripts_and_event_handlers(): void
    {
        $dirty = '<p onclick="alert(1)">مرحبا</p><script>alert(2)</script><a href="javascript:alert(3)">x</a>';
        $clean = HtmlSanitizer::clean($dirty);

        $this->assertStringContainsString('<p>مرحبا</p>', $clean);
        $this->assertStringNotContainsString('script', strtolower($clean));
        $this->assertStringNotContainsString('onclick', strtolower($clean));
        $this->assertStringNotContainsString('javascript:', strtolower($clean));
    }

    #[Test]
    public function it_keeps_safe_formatting_tags(): void
    {
        $html = '<h2>عنوان</h2><p><strong>نص</strong></p><ul><li>عنصر</li></ul>';
        $clean = HtmlSanitizer::clean($html);

        $this->assertStringContainsString('<h2>عنوان</h2>', $clean);
        $this->assertStringContainsString('<strong>نص</strong>', $clean);
        $this->assertStringContainsString('<li>عنصر</li>', $clean);
    }
}
