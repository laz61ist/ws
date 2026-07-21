# WABridge P0 — Bağımsız Denetim Raporu

> Denetçi: bağımsız (taze bağlam). Tarih: 2026-07-21. Repo: `/home/user/ws` @ `7838216`.
> Yöntem: her madde ÇALIŞTIRILARAK doğrulandı; kod/test/fixture/config DEĞİŞTİRİLMEDİ.
> Tek yazılan dosya: bu rapor. Doğrulama deterministik ve API'siz (runtime LLM çağrılmadı).

## Özet

**P0 temel akış BİTTİ ve kanıtlandı** — parser + heuristik sınıflama + haftalık digest + web MVP uçtan uca çalışıyor; 29/29 test geçiyor, KVKK silme ve şema uyumu doğrulandı. Tek gerçek boşluk: production runtime LLM yolu (`LlmClassifier`) iskelet halinde ve CANLI DOĞRULANMADI (kodun kendisi de bunu beyan ediyor), fixture'lar hâlâ sentetik. Bunlar P0 kapsamı dışında ve dürüstçe işaretli.

## Kabul kriterleri

| # | Kriter | Durum | Kanıt (komut + gerçek çıktı / dosya:satır) |
|---|--------|-------|--------------------------------------------|
| 1 | Parser tüm fixture'ları hatasız normalize ediyor + büyük fixture test-time üretiliyor | ✅ | `COMPOSER_ALLOW_SUPERUSER=1 composer test` → `HEPSI GEÇTI: 29 test, 66 assertion`. Parse sayıları: android=20, ios=5, large=500 mesaj (hata yok). Regenerasyon: `synthetic_large_500.txt` silindi → `composer test` onu yeniden üretti, md5 birebir aynı (`c2901192...` = `c2901192...`), 29 test yine geçti. |
| 2 | Parse yolu script-only (regex), İÇİNDE LLM yok | ✅ | `grep -rniE "curl\|http\|api\|anthropic\|openai" src/Parser` → **(no matches)**. `src/Parser/ChatParser.php:28-36` iki regex sabiti (`RE_BRACKET`, `RE_DASH`); `:41 parse()` sadece `preg_match`/`explode`/`mb_*` kullanıyor. |
| 3 | Çıkarım gerçek lojistiği yakalıyor, dedikoduyu eliyor | ✅ | Bağımsız smoke (scratchpad, repoya yazılmadı): para toplama→`PARA {ne:öğretmen hediyesi,tutar:100₺,son:2026-01-24}`, etkinlik→`ETKINLIK {tarih:2026-01-23,saat:18:00,ne:veli toplantısı}`. Tuzaklar: "cuma gününü iple çekiyorum"→`GURULTU`, "Tamam hocam getiririz" (haber kipi)→`GURULTU`, "hiç param kalmadı" (tutar yok)→`GURULTU`, "komşu sınıfın öğretmeni değişmiş" (dedikodu)→`GURULTU`. |
| 4 | Digest JSON referans şemaya uyuyor | ✅ | Bağımsız tip kontrolü (ios fixture): `ŞEMA UYUMLU ✓ (5 alan, tipler doğru, iç madde alanları doğru)`. Üst anahtarlar = `{hafta:string, takvim:[{tarih,saat,ne,cocuk}], senden_aksiyon:[string], para_talepleri:[{ne,tutar,son}], elenen_gurultu_sayisi:int}`. Örnek çıktı aşağıda "Kanıt ekleri"nde. |
| 5 | KVKK: ham .txt işlem sonrası siliniyor + DB'ye yazılmıyor | ✅ | `src/Pipeline.php:79-83` `finally { @unlink($path); }`. Geçici dosya smoke: işlem öncesi `EVET` → sonrası `HAYIR (silindi ✓)`. `grep -rniE "insert\|pdo\|mysqli\|sqlite\|->query\|prepare" src` → **(no matches)** = ham sohbeti DB'ye yazan kod yok. Web upload sonrası `storage/uploads/` yalnızca `.gitkeep` (0 bayt). |
| 6 | Secret sadece .env'de, kodda/logda hardcode key yok | ✅ | `grep -rniE "api[_-]?key\|secret\|token\|sk-\|bearer" src public tests` → tüm eşleşmeler değişken adı / config anahtarı (`WABRIDGE_LLM_API_KEY`) / CSRF oturum token'ı / yorum. Gerçek secret DEĞERİ yok, `sk-...` literal yok. `x-api-key: '.$this->apiKey` (Env'den okunuyor). `.env` yok & git'te izlenmiyor; sadece `.env.example` (boş değerli şablon). |
| 7 | PSR-12 + declare(strict_types=1) + prepared statement | ✅ (prepared = N/A) | `grep -rL "declare(strict_types=1)" src/public/tests --include=*.php` → **(hiçbiri eksik değil)**. Tüm PHP dosyaları `php -l` temiz (0 syntax hatası). Prepared statement: **projede DB yok → N/A** (ihlal değil). |
| 8 | Ingestion sadece resmî export .txt; scraping/yetkisiz API/linked-device YOK | ✅ | `grep -rniE "scrap\|whatsapp.*api\|puppeteer\|selenium\|webhook\|wa\.me\|web\.whatsapp" src public` → tek eşleşme `public/index.php:137` bir **açıklama metni** ("Scraping / yetkisiz API kullanılmaz."). Ingestion = web dosya upload (`DigestController::handleUpload`) → `Pipeline::processFile`. |
| 9 | Web MVP ayağa kalkıyor, fixture yükleyince digest render ediyor; rıza yoksa TR hata | ✅ | `php -S 127.0.0.1:8199` → `GET / HTTP 200`. Rıza+CSRF ile POST → render: `Hafta: 2026-01-19/25`, `15 gürültü elendi`, takvim (kıyafet serbest/veli toplantısı), para (`öğretmen hediyesi 100₺`). Rıza YOK → `"Devam etmek için KVKK aydınlatma metnini onaylamanız gerekir..."`. Geçersiz CSRF → `"Oturum doğrulaması başarısız (CSRF)..."`. Sunucu kapatıldı (port HTTP 000). |

## CLAUDE.md kural uyumu

| Kural | Durum | Kanıt |
|-------|-------|-------|
| Ingestion SADECE resmî export .txt | ✅ | Kriter 8. Upload akışı `.txt` uzantı + `is_uploaded_file` + `move_uploaded_file` doğruluyor (`DigestController.php:44-59`). Scraping/linked-device kodu yok. |
| Deterministik parse = script (regex), LLM'e ayrıştırtma yok | ✅ | Kriter 2. `ChatParser` tamamen regex; `src/Parser` altında curl/http/api yok. |
| Kademeli maliyet: bulk ucuz model, yapılandırma üst model | ✅ (tasarım) | `LlmClassifier.php:37,51,73` ucuz model = `classifyLabel` (bulk etiket), üst model = yalnız sinyal mesajının `structure`'ı; gürültüye üst model harcanmıyor (`:38-40`). NOT: canlı çağrı doğrulanmadı (aşağı). |
| KVKK: işle → çıkar → ham veriyi sil; DB'ye yazma; açık rıza | ✅ | Kriter 5. `finally @unlink`, DB'ye ham yazım yok, web formda zorunlu `kvkk_consent` checkbox + sunucu tarafı kontrol (`DigestController.php:28-31`). |
| Secret .env'de; koda/log'a/CLAUDE.md'ye yazma | ✅ | Kriter 6. Hardcode secret yok; `.env` gitignore'da; log'a secret yazan kod görülmedi. |
| PSR-12, strict_types, prepared statement, CSRF, TR hata mesajı | ✅ (prepared N/A) | Kriter 7 + 9. strict_types her dosyada; lint temiz; CSRF `hash_equals` sabit-zaman (`Csrf.php:31`); tüm hata mesajları Türkçe. DB olmadığı için prepared statement N/A. |
| Mevcut çalışan koda dokunma (Consistency First) | ✅ | Denetim salt-okunur; `git status` temiz (repoda değişiklik yok). |
| Halüsinasyon yasak: her iddia fixture çıktısına karşı doğrulanır | ✅ | Bu raporun her satırı çalıştırılmış komut çıktısına dayanıyor; doğrulanamayan tek nokta (canlı LLM) açıkça işaretlendi. |

## Kanıt ekleri (seçme gerçek çıktılar)

**Büyük fixture — 500 mesajdan sinyal çıkarımı (bağımsız smoke):**
```
takvim(etkinlik)=2  senden_aksiyon=2  para=1  => toplam sinyal=5
elenen_gurultu_sayisi=495
KONTROL: sinyal(5) + gurultu(495) = 500  (parse edilen mesaj: 500)
```
Naif keyword tuzakları (tutarsız "para", geçmiş/gelecek "cuma", haber kipi, dedikodu) gürültüde kaldı; tam 5 gerçek lojistik çıkarıldı — beklenen `synthetic_large_500.json` ile birebir.

**Web render — rıza+CSRF ile üretilen digest (HTML `<pre>` bloğundan):**
```json
{
    "hafta": "2026-01-19/25",
    "takvim": [
        {"tarih": "2026-01-22", "saat": "09:00", "ne": "kıyafet serbest", "cocuk": null},
        {"tarih": "2026-01-23", "saat": "18:00", "ne": "veli toplantısı", "cocuk": null}
    ],
    "senden_aksiyon": [
        "Yarına imza formunu getirmeyi unutmayın, veli toplantısı kararı için gerekli.",
        "Gezi ücreti 150₺ Cuma'ya kadar öğretmene getirin lütfen, gezi için gerekli."
    ],
    "para_talepleri": [
        {"ne": "öğretmen hediyesi", "tutar": "100₺", "son": "2026-01-24"}
    ],
    "elenen_gurultu_sayisi": 15
}
```

## Eksikler & riskler

- **Runtime LLM yolu CANLI DOĞRULANMADI.** `LlmClassifier` gerçek Anthropic Messages API çağrısı içeriyor ama API key olmadan koşulamaz; kodun kendi docblock'u da bunu beyan ediyor (`LlmClassifier.php:16-17`). Tüm testler `HeuristicClassifier` üzerinden geçiyor. Production kalitesi (özellikle kolektif-para vs kişisel-ödeme gibi ince ayrımlar) canlı doğrulanana kadar bilinmiyor. → **P0 için kabul edilebilir** (heuristik yol tam çalışıyor), ama P1 öncesi canlı smoke gerekir.
- **Fixture'lar hâlâ sentetik.** `tests/fixtures/*` tümü `synthetic_` önekli, temsili veri (CLAUDE.md ve `tests/fixtures/README.md` bunu açıkça söylüyor). Parser gerçek anonimleştirilmiş export'lara göre tasarlandı ama gerçek dosyalara karşı HENÜZ doğrulanmadı. Gerçek export formatı (ör. farklı yıl/saat/emoji/sistem-satırı varyantları) sürpriz çıkarabilir.
- **Heuristik sınıflandırıcının kabul ettiği sınır.** `HeuristicClassifier` docblock'u (`:22-25`) kolektif-para vs kişisel-ödeme ayrımının %100 olmadığını, o kenar durumların üst modele ait olduğunu belirtiyor — yani "kısmen" bir alan bilinçli olarak LLM'e bırakılmış.
- **`para_talepleri` içi dedup yok.** `senden_aksiyon` `array_unique`'lenirken (`DigestBuilder.php:69`) para/takvim dedup edilmiyor; gerçek bir grupta aynı aidat talebi tekrarlanırsa digest'te mükerrer görünebilir. (P0 fixture'larında tetiklenmiyor; düşük öncelik.)
- **Bağımlılık `composer.lock` gitignore'da** — offline autoload yeniden üretilebilir olduğu için bilinçli; ama üçüncü-parti paket eklenirse reprodüksiyon riski doğar. Şu an dış bağımlılık yok, sorun değil.

## Önerilen sonraki adım

1. **Canlı LLM smoke'u** (P1 kapısı): gerçek `.env` + tek fixture ile `WABRIDGE_CLASSIFIER=llm` yolunu bir kez koştur, ucuz/üst model kademesinin ve JSON yapılandırmanın gerçekten çalıştığını doğrula. Maliyet/hata davranışını ölç.
2. **1-2 gerçek anonimleştirilmiş export ekle** (`synthetic_` öneki olmadan) ve parser'ı bunlara karşı koştur; format varyantı çıkarsa regex'i genişlet. P0 iddiasını "gerçek veriyle doğrulandı"ya taşır.
3. `para_talepleri` / `takvim` için hafif dedup ekle (mükerrer talep birleştirme) — gerçek gürültülü grupta digest kalitesini korur.
4. (Opsiyonel) Web'de büyük dosya/again-upload akışı ve upload hata kodları için birkaç entegrasyon testi.

---
*Bu rapor deterministik doğrulama ile üretildi; hiçbir "çalışıyor" iddiası çalıştırılmadan yazılmadı. Doğrulanamayan tek alan (canlı LLM) açıkça "doğrulanmadı" olarak işaretlendi.*
