# raw_exports/ — GERÇEK export'ları buraya koy

Gerçek veri doğrulaması için WhatsApp'ın resmî **"Sohbeti dışa aktar"** ile
verdiği `.txt` dosyalarını **bu klasöre** koy (ör. `raw_exports/grup1.txt`).

## Önemli (KVKK)
- Bu klasördeki `.txt`'ler `.gitignore`'dadır — **asla commit edilmez**.
  (Yalnızca bu README izlenir.)
- `scripts/anonymize.php` bunları `tests/fixtures/real/*.txt` altına
  **anonimleştirerek** yazar (isim → Veli1..N/Öğretmen; telefon/e-posta/IBAN/TCKN
  maskeli). `tests/fixtures/real/` de `.gitignore`'dadır.
- Kaynak dosyalar SİLİNMEZ; sen istersen elle silersin.

## Sonra ne olur
Dosyaları koyduktan sonra "gerçek veri doğrulaması" görevini tekrar ver.
Pipeline **yalnızca heuristik** yolla (LlmClassifier değil) çalışır — para harcanmaz.
