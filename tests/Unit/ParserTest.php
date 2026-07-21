<?php

declare(strict_types=1);

namespace WABridge\Tests\Unit;

use WABridge\Parser\ChatParser;
use WABridge\Tests\TestCase;

final class ParserTest extends TestCase
{
    private function fixture(string $name): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/fixtures/' . $name . '.txt');
    }

    public function testAndroidFixtureMessageCountAndSystemLineSkipped(): void
    {
        $messages = (new ChatParser())->parse($this->fixture('synthetic_android_tr'));
        // 21 satır - 1 sistem satırı (şifreleme, ":" yok) = 20 mesaj.
        $this->assertCount(20, $messages, 'Android fixture 20 mesaj vermeli');
        $this->assertSame('Ayşe K.', $messages[0]->sender);
        $this->assertStringContains('Günaydın arkadaşlar', $messages[0]->body);
        $this->assertSame('Mehmet T.', $messages[19]->sender);
    }

    public function testAndroidDatetimeParsed(): void
    {
        $messages = (new ChatParser())->parse($this->fixture('synthetic_android_tr'));
        $this->assertSame('2026-01-19 08:32:00', $messages[0]->at->format('Y-m-d H:i:s'));
    }

    public function testIosBracketFormatAndSeconds(): void
    {
        $messages = (new ChatParser())->parse($this->fixture('synthetic_ios_tr'));
        // 7 satır ama 1 sistem + 1 çok-satırlı devam = 5 mesaj.
        $this->assertCount(5, $messages, 'iOS fixture 5 mesaj vermeli');
        $this->assertSame('Ayşe K.', $messages[0]->sender);
        $this->assertSame('2026-01-26 09:05:12', $messages[0]->at->format('Y-m-d H:i:s'));
    }

    public function testMultilineMessageMerged(): void
    {
        $messages = (new ChatParser())->parse($this->fixture('synthetic_ios_tr'));
        // 2. mesaj (gezi) iki fiziksel satıra yayılır.
        $gezi = $messages[1];
        $this->assertStringContains('28 Ocak Çarşamba gezi var.', $gezi->body);
        $this->assertStringContains('Servis saat 08:30', $gezi->body);
        $this->assertTrue(str_contains($gezi->body, "\n"), 'Çok satırlı gövde \\n içermeli');
    }

    public function testUnicodeSpacesNormalized(): void
    {
        // iOS'un araya soktuğu dar (U+202F) ve kırılmaz (U+00A0) boşluklar.
        $line = "12.01.2026,\u{00A0}09:15\u{202F}- Ali Veli: merhaba";
        $messages = (new ChatParser())->parse($line);
        $this->assertCount(1, $messages);
        $this->assertSame('Ali Veli', $messages[0]->sender);
        $this->assertSame('2026-01-12 09:15:00', $messages[0]->at->format('Y-m-d H:i:s'));
    }

    public function testDayFirstVersusMonthFirst(): void
    {
        $parser = new ChatParser();
        // Türkçe gün-önce: 13 = gün, 02 = ay.
        $tr = $parser->parse('13.02.2026, 10:00 - Ali: x');
        $this->assertSame('2026-02-13', $tr[0]->at->format('Y-m-d'));
        // US ay-önce (ilk bileşen >12 olamayacağı için ikinciden anlaşılır).
        $us = $parser->parse('02/13/2026, 10:00 - Ali: x');
        $this->assertSame('2026-02-13', $us[0]->at->format('Y-m-d'));
    }

    public function testTwelveHourClockConverted(): void
    {
        $messages = (new ChatParser())->parse('12.01.2026, 9:15 PM - Ali: akşam');
        $this->assertSame('2026-01-12 21:15:00', $messages[0]->at->format('Y-m-d H:i:s'));
    }
}
