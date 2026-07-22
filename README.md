# WABridge (v1a)

Kaotik veli WhatsApp grubunun resmî **"Sohbeti dışa aktar" .txt**'sini alıp
yapılandırılmış **haftalık digest**'e çeviren katman. P0 çekirdeği: deterministik
parser + sınıflama/çıkarım pipeline + haftalık digest. v1a: magic-link hesap +
digest geçmişi + ücretsiz/ücretli sınır. Web-first.

Ürün tasarım kararları (ingestion kanalı, ödeme, faz ayrımı) için
`docs/PRODUCT_v1_DESIGN.md`. Proje kuralları için `CLAUDE.md`'ye bakın.

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
| Web (upload) | `public/index.php`, `src/Web/DigestController.php` | Upload → digest; CSRF + KVKK rızası + ücretsiz/ücretli hak + Türkçe hata |
| Kalıcılık (v1a) | `src/Storage/Database.php`, `Migrator.php`, `storage/migrations/*.sql` | SQLite — yalnızca ÜRETİLMİŞ digest + hesap/grup meta verisi, ham export asla |
| Auth (v1a) | `src/Auth/MagicLinkService.php`, `AuthSession.php`, `public/login.php`, `magic.php` | Parolasız, hash'lenmiş tek-kullanımlık link |
| Geçmiş (v1a) | `src/Digest/DigestRepository.php`, `src/Web/HistoryController.php`, `public/gecmis.php` | Digest geçmişi + hesap silme (KVKK unutulma hakkı) |
| Billing iskeleti (v1b) | `src/Billing/{Entitlement,IyzicoClient,SubscriptionService,WebhookController}.php` | Ücretsiz/ücretli sınır (**gerçek, test edilmiş**); iyzico canlı çağrısı **DOĞRULANMADI** (bkz. aşağı) |

Sınıflandırıcı `.env`'deki `WABRIDGE_CLASSIFIER` (`heuristic`|`llm`) ile seçilir.

> **Not (LLM):** `LlmClassifier` production iskeletidir; bu depoda canlı API
> anahtarı olmadığından **canlı doğrulanmadı**. Deterministik yol (`heuristic`)
> uçtan uca test edilir.
>
> **Not (iyzico):** Sandbox hesabı e-posta ile açılabiliyor (vergi levhası
> gerekmiyor, doğrulandı) ama bu depoda gerçek sandbox key'i yok. iyzico'nun
> imza şeması (IYZWSv2) resmi docs'tan bu oturumda teyit edilemediği için
> `IyzicoClient`/webhook imza doğrulaması bilinçli olarak **reddeder/istisna
> fırlatır** (fail-closed) — sahte "çalışıyor" iddiası yok. `Entitlement`
> (ücretsiz/ücretli sınır) iyzico'ya bağımlı değildir ve tam test edilmiştir.

## Kurulum & çalıştırma

```bash
cp .env.example .env          # gerekli anahtarları doldur (secret'lar .env'de)
composer test                 # fixture'lara karşı parser + çıkarım (offline, LLM yok)
composer serve                # http://localhost:8000 — SQLite otomatik migrate olur
```

`composer` harici bağımlılık gerektirmez (sıfır-bağımlılık autoloader + test runner).
İlk istekte `src/Support/App::database()` `storage/migrations/*.sql`'i otomatik,
idempotent uygular — ayrı bir migrate komutu gerekmez.

## Test fixtures

`tests/fixtures/` altındaki dosyalar **sentetiktir** (gerçek export değil) —
ayrıntı: `tests/fixtures/README.md`. Gerçek anonimleştirilmiş export'lar
`synthetic_` öneki olmadan aynı dizine eklenir.

Büyük fixture'ı yeniden üretmek: `php tests/tools/make_large_fixture.php`
