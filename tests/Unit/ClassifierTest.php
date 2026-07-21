<?php

declare(strict_types=1);

namespace WABridge\Tests\Unit;

use WABridge\Classify\HeuristicClassifier;
use WABridge\Classify\Label;
use WABridge\Parser\Message;
use WABridge\Tests\TestCase;

final class ClassifierTest extends TestCase
{
    private function classify(string $body, string $at = '2026-01-20 10:00:00'): \WABridge\Classify\Classification
    {
        $msg = new Message(new \DateTimeImmutable($at), 'Test', $body, 1);
        return (new HeuristicClassifier())->classify($msg);
    }

    // --- GÜRÜLTÜ TUZAKLARI (naif keyword false-positive'leri elenmeli) ---

    public function testMoneyWordWithoutAmountIsNoise(): void
    {
        // "param" var ama tutar token'ı yok -> para DEĞİL.
        $c = $this->classify('hiç param kalmadı bu ay ya, maaşa daha var 😩');
        $this->assertSame(Label::Gurultu, $c->label, '"param" tek başına para sayılmamalı');
    }

    public function testGossipIsNoise(): void
    {
        $c = $this->classify('Dedikodu değil ama komşu sınıfın öğretmeni değişmiş 👀');
        $this->assertSame(Label::Gurultu, $c->label);
    }

    public function testIndicativeVerbIsNotTask(): void
    {
        // "getiririz" haber kipi -> emir DEĞİL -> görev sayılmamalı.
        $c = $this->classify('Tamam hocam getiririz');
        $this->assertSame(Label::Gurultu, $c->label, '"getiririz" görev olmamalı');
    }

    public function testPastWeekdayMentionIsNoise(): void
    {
        // "cuma" var ama saat/etkinlik yok -> etkinlik DEĞİL.
        $c = $this->classify('ya bu hafta çok yoğun, cuma gününü iple çekiyorum 😅');
        $this->assertSame(Label::Gurultu, $c->label);
    }

    // --- SİNYALLER (gerçek lojistik yakalanmalı) ---

    public function testImperativePlusObjectIsTask(): void
    {
        $c = $this->classify('Yarına imza formunu getirmeyi unutmayın, veli toplantısı kararı için gerekli.');
        $this->assertSame(Label::Gorev, $c->label);
        $this->assertStringContains('imza', $c->data['aksiyon']);
    }

    public function testEventWithDateAndTime(): void
    {
        $c = $this->classify(
            'Sayın veliler, 22 Ocak Perşembe kıyafet serbest günü olacak. saat 09:00 normal ders başlıyor.',
            '2026-01-19 09:10:00',
        );
        $this->assertSame(Label::Etkinlik, $c->label);
        $this->assertSame('2026-01-22', $c->data['tarih']);
        $this->assertSame('09:00', $c->data['saat']);
        $this->assertSame('kıyafet serbest', $c->data['ne']);
    }

    public function testCollectiveMoneyRequest(): void
    {
        $c = $this->classify(
            'Öğretmen hediyesi almak istiyoruz, 100₺ topluyoruz. Katılmak isteyen 24 Ocak\'a kadar yazsın.',
            '2026-01-20 18:45:00',
        );
        $this->assertSame(Label::Para, $c->label);
        $this->assertSame('100₺', $c->data['tutar']);
        $this->assertSame('2026-01-24', $c->data['son']);
        $this->assertSame('öğretmen hediyesi', $c->data['ne']);
    }

    public function testPersonalPaymentWithImperativeIsAction(): void
    {
        // Tutar + kişisel emir (getirin) -> senden_aksiyon (görev), toplu para değil.
        $c = $this->classify('Gezi ücreti 150₺ Cuma\'ya kadar öğretmene getirin lütfen.');
        $this->assertSame(Label::Gorev, $c->label);
    }

    public function testMediaPlaceholderIsNoise(): void
    {
        $this->assertSame(Label::Gurultu, $this->classify('<Medya dahil edilmedi>')->label);
    }
}
