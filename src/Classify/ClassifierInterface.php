<?php

declare(strict_types=1);

namespace WABridge\Classify;

use WABridge\Parser\Message;

/**
 * Sınıflama + yapılandırma sözleşmesi.
 *
 * İki uygulama vardır:
 *  - HeuristicClassifier: deterministik, API'siz. Testler bunu koşar.
 *  - LlmClassifier: production. Kademeli maliyet — bulk sınıflama ucuz modele,
 *    yapılandırma üst modele (CLAUDE.md kuralı).
 *
 * $context, mesajın çözümlenmesine yardımcı çevre bilgisidir (ör. mesajın
 * gönderildiği tarih göreli ifadeleri "yarın/Cuma" çözmede kullanılır).
 */
interface ClassifierInterface
{
    public function classify(Message $message): Classification;
}
