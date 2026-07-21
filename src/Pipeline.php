<?php

declare(strict_types=1);

namespace WABridge;

use WABridge\Classify\ClassifierInterface;
use WABridge\Classify\HeuristicClassifier;
use WABridge\Classify\LlmClassifier;
use WABridge\Digest\DigestBuilder;
use WABridge\Parser\ChatParser;
use WABridge\Support\Env;

/**
 * Uçtan uca akış: ham export .txt -> parse -> sınıflama -> haftalık digest.
 *
 * KVKK: processFile() ham dosyayı işledikten sonra SİLER (finally bloğu, hata
 * olsa bile). Ham içerik hiçbir kalıcı depoya yazılmaz — yalnızca bellekte,
 * digest üretilene kadar yaşar.
 */
final class Pipeline
{
    public function __construct(
        private readonly ClassifierInterface $classifier,
        private readonly ChatParser $parser = new ChatParser(),
        private readonly DigestBuilder $digest = new DigestBuilder(),
    ) {
    }

    /**
     * .env'deki WABRIDGE_CLASSIFIER değerine göre pipeline kurar.
     * Varsayılan "heuristic" (deterministik, API'siz).
     */
    public static function fromEnv(): self
    {
        $mode = Env::get('WABRIDGE_CLASSIFIER', 'heuristic');
        if ($mode === 'llm') {
            $classifier = new LlmClassifier(
                (string) Env::get('WABRIDGE_LLM_API_KEY', ''),
                (string) Env::get('WABRIDGE_LLM_BASE_URL', 'https://api.anthropic.com'),
                (string) Env::get('WABRIDGE_CHEAP_MODEL', 'claude-haiku-4-5-20251001'),
                (string) Env::get('WABRIDGE_UPPER_MODEL', 'claude-opus-4-8'),
            );
        } else {
            $classifier = new HeuristicClassifier();
        }
        return new self($classifier);
    }

    /**
     * Ham metinden digest üretir (dosya silme YOK — önizleme/test yolu).
     *
     * @return array<string,mixed>
     */
    public function processString(string $content): array
    {
        $messages = $this->parser->parse($content);
        $classifications = [];
        foreach ($messages as $m) {
            $classifications[] = $this->classifier->classify($m);
        }
        return $this->digest->build($messages, $classifications);
    }

    /**
     * Ham export dosyasını işler ve İŞLEM SONRASI SİLER (KVKK).
     *
     * @return array<string,mixed>
     */
    public function processFile(string $path, bool $deleteAfter = true): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('Yüklenen dosya okunamadı: dosya bulunamadı veya erişilemiyor.');
        }

        try {
            $content = (string) file_get_contents($path);
            return $this->processString($content);
        } finally {
            if ($deleteAfter && is_file($path)) {
                @unlink($path);
            }
        }
    }
}
