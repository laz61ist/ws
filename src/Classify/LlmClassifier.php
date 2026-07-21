<?php

declare(strict_types=1);

namespace WABridge\Classify;

use WABridge\Parser\Message;

/**
 * Production sınıflandırıcı — kademeli maliyet (CLAUDE.md kuralı):
 *   1) UCUZ model: bulk mesaj sınıflama {etkinlik|görev|oylama|para|gürültü}
 *   2) ÜST model: yalnızca sinyal etiketli mesajların YAPILANDIRMASI
 * Gürültüye üst model harcanmaz.
 *
 * DURUM: iskelet gerçek Anthropic Messages API çağrısı yapar ancak API key
 * olmadan koşulamaz; bu repoda CANLI DOĞRULANMADI. Testler HeuristicClassifier
 * üzerinden koşar. Secret .env'den okunur, KODA yazılmaz.
 */
final class LlmClassifier implements ClassifierInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly string $cheapModel,
        private readonly string $upperModel,
    ) {
        if (trim($this->apiKey) === '') {
            throw new \RuntimeException(
                'LLM sınıflandırıcı için WABRIDGE_LLM_API_KEY tanımlı değil. '
                . '.env dosyasını doldurun veya WABRIDGE_CLASSIFIER=heuristic kullanın.'
            );
        }
    }

    public function classify(Message $message): Classification
    {
        $label = $this->classifyLabel($message);      // ucuz model
        if ($label === Label::Gurultu) {
            return Classification::noise();
        }
        $data = $this->structure($message, $label);    // üst model
        return new Classification($label, $data);
    }

    private function classifyLabel(Message $message): Label
    {
        $system = 'Sen bir Türkçe veli grubu mesajı sınıflandırıcısısın. '
            . 'Verilen mesajı SADECE şu etiketlerden biriyle yanıtla (tek kelime, küçük harf): '
            . 'etkinlik, gorev, oylama, para, gurultu. '
            . 'Lojistik olmayan sohbet/dedikodu/selam = gurultu.';
        $answer = $this->call($this->cheapModel, $system, $message->body, 16);
        $answer = strtolower(trim($answer));
        foreach (Label::cases() as $case) {
            if (str_contains($answer, $case->value)) {
                return $case;
            }
        }
        return Label::Gurultu;
    }

    /**
     * @return array<string,mixed>
     */
    private function structure(Message $message, Label $label): array
    {
        $ctx = 'Mesaj zamanı: ' . $message->at->format('Y-m-d H:i') . ' (göreli tarihleri buna göre çöz). ';
        $schema = match ($label) {
            Label::Etkinlik => '{"tarih":"YYYY-MM-DD|null","saat":"HH:MM|null","ne":"kısa ad","cocuk":"ad|null"}',
            Label::Para => '{"ne":"kısa konu","tutar":"örn 100₺","son":"YYYY-MM-DD|null"}',
            default => '{"aksiyon":"tek cümle yapılacak iş"}',
        };
        $system = 'Aşağıdaki mesajı yapılandır. SADECE geçerli JSON döndür, şema: ' . $schema . '. ' . $ctx;
        $raw = $this->call($this->upperModel, $system, $message->body, 256);
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : ['aksiyon' => trim($message->body)];
    }

    private function call(string $model, string $system, string $user, int $maxTokens): string
    {
        $payload = json_encode([
            'model' => $model,
            'max_tokens' => $maxTokens,
            'system' => $system,
            'messages' => [['role' => 'user', 'content' => $user]],
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init(rtrim($this->baseUrl, '/') . '/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'content-type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('LLM çağrısı başarısız: ' . $err);
        }
        $data = json_decode((string) $response, true);
        return (string) ($data['content'][0]['text'] ?? '');
    }
}
