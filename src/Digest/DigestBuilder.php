<?php

declare(strict_types=1);

namespace WABridge\Digest;

use WABridge\Classify\Classification;
use WABridge\Classify\Label;
use WABridge\Parser\Message;

/**
 * Sınıflandırılmış mesajlardan referans şemaya birebir uyan haftalık digest
 * üretir:
 *   {
 *     "hafta": "YYYY-MM-DD/DD",
 *     "takvim": [{"tarih","saat","ne","cocuk"}],
 *     "senden_aksiyon": ["..."],
 *     "para_talepleri": [{"ne","tutar","son"}],
 *     "elenen_gurultu_sayisi": int
 *   }
 */
final class DigestBuilder
{
    /**
     * @param list<Message>        $messages
     * @param list<Classification> $classifications aynı sıra ve uzunlukta
     * @return array{hafta:string,takvim:list<array<string,mixed>>,senden_aksiyon:list<string>,para_talepleri:list<array<string,mixed>>,elenen_gurultu_sayisi:int}
     */
    public function build(array $messages, array $classifications): array
    {
        $takvim = [];
        $aksiyon = [];
        $para = [];
        $gurultu = 0;

        foreach ($classifications as $c) {
            switch ($c->label) {
                case Label::Etkinlik:
                    $takvim[] = [
                        'tarih' => $c->data['tarih'] ?? null,
                        'saat' => $c->data['saat'] ?? null,
                        'ne' => $c->data['ne'] ?? '',
                        'cocuk' => $c->data['cocuk'] ?? null,
                    ];
                    break;
                case Label::Gorev:
                case Label::Oylama:
                    $a = trim((string) ($c->data['aksiyon'] ?? ''));
                    if ($a !== '') {
                        $aksiyon[] = $a;
                    }
                    break;
                case Label::Para:
                    $para[] = [
                        'ne' => $c->data['ne'] ?? '',
                        'tutar' => $c->data['tutar'] ?? '',
                        'son' => $c->data['son'] ?? null,
                    ];
                    break;
                case Label::Gurultu:
                    $gurultu++;
                    break;
            }
        }

        return [
            'hafta' => $this->weekLabel($messages),
            'takvim' => array_values($takvim),
            'senden_aksiyon' => array_values(array_unique($aksiyon)),
            'para_talepleri' => array_values($para),
            'elenen_gurultu_sayisi' => $gurultu,
        ];
    }

    /**
     * Mesajların en yoğun düştüğü ISO haftanın Pazartesi–Pazar etiketi.
     * Aynı ay ise "2026-01-19/25", değilse "2026-01-30/02-05".
     *
     * @param list<Message> $messages
     */
    private function weekLabel(array $messages): string
    {
        if ($messages === []) {
            return '';
        }

        /** @var array<string,int> $counts */
        $counts = [];
        foreach ($messages as $m) {
            $key = $m->isoWeekKey();
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        arsort($counts);
        $dominant = array_key_first($counts);

        // "o-\WW" -> o haftanın Pazartesi'si.
        [$year, $week] = explode('-W', (string) $dominant);
        $monday = (new \DateTimeImmutable())->setISODate((int) $year, (int) $week, 1);
        $sunday = $monday->modify('+6 day');

        $start = $monday->format('Y-m-d');
        if ($monday->format('m') === $sunday->format('m')) {
            return $start . '/' . $sunday->format('d');
        }
        return $start . '/' . $sunday->format('m-d');
    }
}
