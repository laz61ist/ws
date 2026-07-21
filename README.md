# WABridge (P0)

Kaotik veli WhatsApp grubunun resmî **"Sohbeti dışa aktar" .txt**'sini alıp
yapılandırılmış **haftalık digest**'e çeviren katman. P0 kapsamı: deterministik
parser + sınıflama/çıkarım pipeline + haftalık digest. Web-first, hesap yok.

Proje kuralları için `CLAUDE.md`'ye bakın.

## Ne yapar

Ham export (çoğunluğu gürültü olan yüzlerce mesaj) → sinyal (lojistik):

```json
{
  "hafta": "2026-01-19/25",
  "takvim": [{"tarih":"2026-01-22","saat":"09:00","ne":"kıyafet serbest","cocuk":null}],
  "senden_aksiyon": ["yarına imza formu getir","gezi ücreti 150₺ Cuma'ya kadar"],
  "para_talepleri": [{"ne":"öğretmen hediyesi","tutar":"100₺","son":"2026-01-24"}],
  "elenen_gurultu_sayisi": 470
}
```

## Mimari

| Katman | Dosya | Sorumluluk |
|--------|-------|-----------|
| Parser (deterministik, **LLM yok**) | `src/Parser/ChatParser.php` | Tüm export format varyantlarını (Android/iOS, tarih/saat düzenleri, unicode boşluklar, çok satırlı, sistem satırları) tek kanonik `Message`'a indirger |
| Sınıflama (pluggable) | `src/Classify/HeuristicClassifier.php` | Deterministik, API'siz, çok-sinyalli — testler bunu koşar |
| Sınıflama (production) | `src/Classify/LlmClassifier.php` | Kademeli maliyet: ucuz model = sınıflama, üst model = yapılandırma |
| Digest | `src/Digest/DigestBuilder.php` | Referans şemaya birebir haftalık digest |
| Akış + KVKK | `src/Pipeline.php` | parse → sınıflama → digest; ham .txt işlem sonrası **silinir** |
| Web | `public/index.php`, `src/Web/DigestController.php` | Upload → digest; CSRF + KVKK rızası + Türkçe hata |

Sınıflandırıcı `.env`'deki `WABRIDGE_CLASSIFIER` (`heuristic`|`llm`) ile seçilir.

> **Not:** `LlmClassifier` production iskeletidir; bu depoda canlı API anahtarı
> olmadığından **canlı doğrulanmadı**. Deterministik yol (`heuristic`) uçtan uca
> test edilir.

## Kurulum & çalıştırma

```bash
cp .env.example .env          # gerekli anahtarları doldur (secret'lar .env'de)
composer test                 # fixture'lara karşı parser + çıkarım (offline, LLM yok)
composer serve                # http://localhost:8000
```

`composer` harici bağımlılık gerektirmez (sıfır-bağımlılık autoloader + test runner).

## Test fixtures

`tests/fixtures/` altındaki dosyalar **sentetiktir** (gerçek export değil) —
ayrıntı: `tests/fixtures/README.md`. Gerçek anonimleştirilmiş export'lar
`synthetic_` öneki olmadan aynı dizine eklenir.

Büyük fixture'ı yeniden üretmek: `php tests/tools/make_large_fixture.php`
