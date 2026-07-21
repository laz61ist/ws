# Test fixtures — DURUM: SENTETİK

Buradaki `synthetic_*.txt` dosyaları **gerçek WhatsApp export'u DEĞİLDİR**.
Format-doğru, elle/programla üretilmiş **temsili** verilerdir. İçlerindeki
isimler, mesajlar ve olaylar uydurmadır; gerçek bir kişiye/gruba ait değildir.

CLAUDE.md, `tests/fixtures/` altında 3–5 **gerçek (anonimleştirilmiş)** export
ister. O gerçek dosyalar bu depoya EKLENMEDİ (elimde gerçek export yok ve KVKK
gereği 3. kişi verisi keyfî işlenmez). Gerçek anonimleştirilmiş export'lar
eklenirken:

- `synthetic_` öneki OLMADAN, aynı dizine konur.
- İçerdikleri kişisel veriler anonimleştirilir (isim → takma ad, telefon silinir).
- Parser bu sentetik dosyalardaki tüm format varyantlarını zaten desteklediği
  için gerçek dosyalar da çalışır.

## Dosyalar

| Dosya | Format | Amaç |
|-------|--------|------|
| `synthetic_android_tr.txt` | Android TR (`GG.AA.YYYY, SS:DD - Ad: mesaj`) | Ana sinyal/gürültü ayrımı: 20 mesaj, 5 gerçek lojistik, gürültü tuzakları |
| `synthetic_ios_tr.txt` | iOS köşeli (`[GG.AA.YYYY, SS:DD:ss] Ad: mesaj`) | Format varyantı + çok satırlı mesaj |
| `synthetic_large_500.txt` | Android TR | ~500 mesajlık yoğun gürültü içine gömülü 5 lojistik ("gözle bakış" kriteri) |

`synthetic_large_500.txt` deterministik olarak üretilir:
`php tests/tools/make_large_fixture.php`

Beklenen digest çıktıları `expected/` altındadır.
