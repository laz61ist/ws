# Proje: WABridge — WhatsApp grup organizatörü (P0)

## Amaç
Kullanıcının resmî "Sohbeti dışa aktar" .txt'sini alıp kaotik veli WhatsApp
grubunu yapılandırılmış çıktıya çeviren katman. P0 kapsamı: parser + çıkarım
pipeline + haftalık digest. Web-first. v1a'dan itibaren magic-link hesap +
digest geçmişi var (bkz. docs/PRODUCT_v1_DESIGN.md).

## Stack
- Backend + parser: PHP 8.3 (MVC). Ağır metin işi gerekirse Python yardımcı script.
- Çıkarım: LLM API (runtime). Cheap model = sınıflama, üst model = yapılandırma.
- UI: responsive web (P0). Android (Kotlin/Compose) P1'de share-target.

## KESİN KURALLAR (ihlal = red)
- INGESTION SADECE resmî Export Chat .txt üzerinden. WhatsApp scraping /
  yetkisiz API / linked-device otomasyonu YASAK (ToS ihlali → hesap banı).
- Deterministik parse = SCRIPT (regex). LLM'e .txt ayrıştırtma — para + halüsinasyon.
  LLM SADECE fuzzy iş: mesaj sınıflama {etkinlik|görev|oylama|para|gürültü} +
  etkinlik yapılandırma.
- Runtime maliyet: bulk sınıflama ucuz modele, yapılandırma üst modele. Her mesaja
  üst model = israf.
- KVKK/GDPR: ham sohbette 3. kişilerin kişisel verisi var → işle → çıkar →
  HAM VERİYİ SİL. Açık rıza. Ham .txt kalıcı DB'ye yazılmaz.
- v1a: ÜRETİLMİŞ digest JSON'u kalıcı saklanır (kullanıcı geçmişi için) ama bu
  isim/serbest-metin içerebilir — KVKK'dan tam muaf DEĞİL, rıza metni bunu
  yansıtır, hesap silme = digest'leri de siler. Magic-link token'ları DB'ye
  ASLA plaintext yazılmaz (yalnızca sha256 hash).
- WhatsApp Cloud API ile chat İÇERİĞİ ASLA işlenmez (Meta'nın Ekim 2025 "AI
  birincil işlev" yasağı + 3.kişi veri belirsizliği — bkz. docs/PRODUCT_v1_DESIGN.md
  Soru 1). WhatsApp en fazla P1+'ta AI içermeyen hatırlatma template'i için kullanılır.
- Secret .env'de. Koda, CLAUDE.md'ye, log'a ASLA yazma.
- PSR-12, declare(strict_types=1), prepared statement, CSRF, Türkçe hata mesajı.
- Mevcut çalışan koda dokunma; yeni ekleme mevcut kalıba uyar (Consistency First).
- HALÜSİNASYON YASAK: uydurma veri/sonuç yok. Her "çalışıyor" iddiasını
  tests/fixtures/ çıktısına karşı doğrula. Doğrulanmamışsa "doğrulanmadı" de.

## Test fixtures
tests/fixtures/ altında 3-5 GERÇEK (anonimleştirilmiş) export .txt.
Her değişiklik bunlara karşı çalıştırılır. WhatsApp .txt satır formatı:
`GG.AA.YYYY, SS:DD - Gönderen: mesaj` (tarih/format varyantlarını handle et).

> P0 durumu: repoda şu an GERÇEK export yerine `synthetic_*` önekli
> SENTETİK (temsili) fixture'lar var (bkz. tests/fixtures/README.md).
> Gerçek anonimleştirilmiş export'lar eklenince format-doğru çalışması için
> parser bunlara göre tasarlandı; gerçek dosyalar da aynı dizine `synthetic_`
> öneki OLMADAN eklenir.

## Komutlar
- Test: `composer test` (fixture'lara karşı parser + çıkarım)
- Çalıştır: `php -S localhost:8000 -t public`
