---
name: verify-extraction
description: WABridge çıkarım çıktısını (digest) referans şemaya ve tests/fixtures/ beklentilerine karşı doğrula. Parser/sınıflama/digest koduna dokunulduğunda, "çıkarım çalışıyor" iddiası yapılmadan önce, veya yeni fixture eklendiğinde MUTLAKA kullan. Deterministik doğrulama akışı — LLM harcamaz.
---

# verify-extraction

WABridge digest çıktısının doğruluğunu **kanıta dayalı** denetler. Amaç: "çalışıyor"
demeden önce test çıktısını göstermek (CLAUDE.md EVIDENCE kuralı).

## Ne zaman

- `src/Parser`, `src/Classify`, `src/Digest` veya `src/Pipeline.php` değişince.
- Yeni fixture eklenince.
- "Sınıflama/çıkarım çalışıyor" denmeden ÖNCE.

## Adımlar

1. **Testi koştur, çıktıyı GÖSTER.**
   `COMPOSER_ALLOW_SUPERUSER=1 composer test`
   Tüm testler geçmeliyse ham çıktıyı yapıştır. Bir test düştüyse çıktıyı
   yapıştır ve **"geçti" DEME** — düşen testi raporla.

2. **Digest'i referans şemaya karşı denetle.** Çıktı şu 5 alanı ve tiplerini
   birebir taşımalı: `hafta` (string), `takvim` (`[{tarih,saat,ne,cocuk}]`),
   `senden_aksiyon` (`[string]`), `para_talepleri` (`[{ne,tutar,son}]`),
   `elenen_gurultu_sayisi` (int). Ekstra/eksik alan = başarısız.

3. **Fixture beklentisiyle karşılaştır.** Her `tests/fixtures/synthetic_*.txt`
   için `tests/fixtures/expected/<ad>.json` ile TAM eşleşmeli (DigestTest bunu yapar).

4. **Sinyal/gürültü gözle bakış.** `synthetic_large_500.txt`: ~500 mesajdan
   tam **5** gerçek lojistik (2 takvim + 2 aksiyon + 1 para), **495** gürültü
   elenmiş olmalı. Naif keyword tuzakları (tutarsız "para", geçmiş "cuma",
   haber kipi "getiririz", dedikodu) gürültüde kalmalı.

5. **KVKK kontrolü.** `KvkkTest` geçmeli: `processFile()` ham .txt'yi işleyip
   **siler**. `storage/uploads/` içinde `.gitkeep` dışında dosya kalmamalı.

## Kırmızı çizgiler

- Doğrulanmamış hiçbir şeyi "geçti" diye raporlama. Test çıktısı yoksa
  **"doğrulanmadı"** de.
- Beklenen JSON'u gerçek çıktıya uydurmak için düzenleme — bu doğrulamayı
  anlamsızlaştırır. Beklenen dosyalar SPEC'tir; kod ona uymalı, tersi değil.
- Bu akış ürünün runtime LLM'ini çağırmaz; deterministik ve ücretsizdir.
