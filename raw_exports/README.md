# raw_exports/ — GERÇEK export'ları buraya koy

Gerçek veri doğrulaması için WhatsApp'ın resmî **"Sohbeti dışa aktar"** ile
verdiği `.txt` dosyalarını **bu klasöre** koy (ör. `raw_exports/grup1.txt`).

## Önemli (KVKK)
- Bu klasördeki `.txt`'ler `.gitignore`'dadır — **asla commit edilmez**.
  (Yalnızca bu README izlenir.)
- `php scripts/anonymize.php` bunları `tests/fixtures/real/*.txt` altına
  **anonimleştirerek** yazar (isim → Veli1..N/Öğretmen; telefon/e-posta/IBAN/TCKN
  maskeli — `src/Anonymize/Anonymizer.php`, testli: `tests/Unit/AnonymizerTest.php`).
  `tests/fixtures/real/` de `.gitignore`'dadır.
- Kaynak dosyalar SİLİNMEZ; sen istersen elle silersin.
- **Bilinçli sınır (dürüstlük notu)**: script yalnızca gönderen adını (başlıkta)
  ve telefon/e-posta/IBAN/TCKN kalıplarını maskeler. Mesaj METNİ İÇİNDE geçen
  bir kişi adını (ör. "Ayşe bugün gelemeyecek" cümlesindeki isim) TESPİT
  ETMEZ — bu, deterministik regex ile güvenilir çözülemeyen bir ad-tanıma
  (NER) problemidir ve CLAUDE.md'nin "LLM'e ayrıştırtma yasak" ilkesiyle de
  çelişir. `tests/fixtures/real/` yine de `.gitignore`'dadır (savunma
  derinliği); repo dışına paylaşmadan önce anonimleştirilmiş çıktıyı elle
  gözden geçir.

## Sonra ne olur
Dosyaları koyduktan sonra `php scripts/anonymize.php` çalıştır, sonra "gerçek
veri doğrulaması" görevini tekrar ver. Pipeline **yalnızca heuristik** yolla
(LlmClassifier değil) çalışır — para harcanmaz.
