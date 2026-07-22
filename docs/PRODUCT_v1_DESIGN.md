# WABridge — Product v1 Design

> Onay durumu: ONAYLANDI. Bu doküman implementasyon planı — v1a/v1b kodu bu
> dokümanla AYNI turda yazılmadı; kullanıcı talimatı gereği ayrı bir turda
> yapılacak (bkz. "Kapsam netliği" bölümü).

## Context (neden bu değişiklik)

P0 denetimi (`docs/P0_STATUS.md`) motorun (parser + heuristik sınıflama + digest)
sağlam olduğunu kanıtladı — 29/29 test, KVKK silme, şema uyumu bağımsız
doğrulandı. Ama ürün olarak KULLANILABILIR ve GELİR-GETİREBİLİR değil: hesap
yok, ödeme yok, kalıcılık yok (digest her HTTP response'ta üretilip anında
kayboluyor), ve tek ingestion yolu (manuel .txt export→upload) haftalık
tekrarlanan bir sürtünme — gerçek bir veli bunu birkaç haftadan sonra bırakır.

Bu doküman motoru (`src/Parser`, `src/Classify`, `src/Digest`, `src/Pipeline` —
**tek satır değişmeyecek**) bir ürüne saran katmanı tasarlıyor: hangi ingestion
kanalı, para nasıl alınır, gerçek veri olmadan nasıl pilot test edilir, ve
mevcut iskelet ↔ pilot-hazır+gelir-ready v1 arası tam fark.

Araştırma yöntemi: 3 bağımsız Explore ajanı (WhatsApp Cloud API gerçekleri,
TR ödeme sağlayıcıları, kod tabanı envanteri — hepsi WebSearch/kaynak/grep ile
kanıtlı, halüsinasyon yok) + 1 Plan ajanı (implementasyon tasarımı) + kendi
doğrulamam (kod okuma). Doğrulanamayan her nokta aşağıda **DOĞRULANMADI**
olarak işaretli.

---

## Soru 1 — Gerçek kullanıcı akışı ve ingestion kanalı

### Üç seçenek karşılaştırması

**(a) WhatsApp Cloud API forward-to-bot** — araştırıldı, kaynaklı:

- *Maliyet*: 1 Temmuz 2025'ten beri per-message fiyatlandırma. Kullanıcı-başlatımlı,
  24 saat pencere içi yanıt = "Service" mesajı = **ücretsiz** (Kasım 2024'ten
  beri). WABridge akışı ("kullanıcı iletir, bot hemen digest döner") tam bu
  pencereye oturuyor → Meta ücreti muhtemelen **$0/ay**; ücret ancak proaktif
  hatırlatma eklenirse devreye girer (~$3-30/ay aralığı, TR rakamları ikincil
  kaynak, DOĞRULANMADI). **Maliyet bu kanalı engellemiyor.**
- *Onay süreci*: Meta Business Manager → WABA kaydı → Business Verification
  (2-3 belge; süre kaynaklara göre 3-14 iş günü arası tutarsız, DOĞRULANMADI net
  SLA). "Yeşil tik" gerekmez.
- *24 saat kuralı*: Bu akış için elverişli (kullanıcı-başlatımlı → ücretsiz).
- **🔴 KRİTİK RİSK 1 — 3. kişi verisi belirsizliği**: Gruptaki, bota hiç mesaj
  atmayan DİĞER velilerin verisinin işlenmesine dair WhatsApp'ta isimlendirilmiş
  açık bir madde yok (ne yasak ne izin). GDPR/KVKK açısından bağımsız bir hukuki
  risk katmanı — CLAUDE.md'nin KVKK hassasiyetiyle doğrudan geriliyor.
- **🔴 KRİTİK RİSK 2 — Meta'nın "AI sağlayıcı" yasağı (daha ağır)**: 18 Ekim
  2025'te WhatsApp Business Solution Terms güncellendi; AI'ın WhatsApp Business
  Solution'ı **birincil işlev** (incidental değil) olarak kullanması yasaklandı
  — mevcut sağlayıcılar için **15 Ocak 2026'dan itibaren yürürlükte (yani ŞU
  AN)**. WABridge'in TÜM iş modeli "AI ile grup sohbeti analiz edip digest
  üretmek" — yapılandırılmış sipariş-takip botlarının aksine tam bu kategoriye
  giriyor. Meta bu ayrımı kendi takdirine bırakmış, WABridge için kamuya açık
  bir istisna DOĞRULANMADI. Politika AB'de rekabet soruşturması altında (Aralık
  2025), İtalya askıya alınmasını istemiş — nihai hali belirsiz ama ŞU AN
  yürürlükte.
  - Kaynaklar: [WhatsApp Business Solution Terms](https://www.whatsapp.com/legal/business-solution-terms) · [TechCrunch, 18 Ekim 2025](https://techcrunch.com/2025/10/18/whatssapp-changes-its-terms-to-bar-general-purpose-chatbots-from-its-platform/) · [TechCrunch, AB soruşturması](https://techcrunch.com/2025/12/04/eu-investigating-meta-over-policy-change-that-bans-rival-ai-chatbots-from-whatsapp/)

**(b) export-.txt-upload (mevcut)** — neden UX ölü: kod envanterinden kanıtlı
(`public/index.php`, `src/Web/DigestController.php`). Haftalık MANUEL, çok-adımlı
(export al → indir → yükle), oturum/hesap/geçmiş SIFIR → digest sayfa kapanınca
kayboluyor → haftaya sıfırdan. Hatırlatma mekanizması yok. Gerçek bir velinin
birkaç haftada bırakacağı öngörülüyor.

**(c) organizatör-tarafı B2B2C** — değerlendirildi, P0/v1 için **elendi**: hedef
kitle (bireysel veli) ile uyumsuz, ayrı bir satış döngüsü (organizatörü ikna)
gerektirir, kapsamı genişletir. P1+ notu olarak Gap listesine bırakıldı — mevcut
şema (`groups.owner_user_id`) buna zaten 1:N olarak açık, sadece UI/satış eksik.

### KARAR: (a) değil — hibrit; chat analizi ASLA WhatsApp Cloud API'den geçmez

**Seçilen akış: web upload (mevcut motor, değişmez) + hesap/magic-link +
(P1+'ta, isteğe bağlı) SADECE hatırlatma amaçlı WhatsApp bildirimi — chat
içeriği bu kanaldan asla geçmez.**

Gerekçe: (a)'nın maliyet engeli yok ama hukuki/politika riski (özellikle Meta'nın
kendi platformunda "AI birincil işlev" yasağı, ürünün TEMEL VARSAYIMINI hedef
alıyor ve şu an yürürlükte) P0/v1 için orantısız büyük. Asıl UX sorunu ("haftalık
manuel adım unutuluyor") hesap+geçmiş ile kısmen, P1+'ta hafif bir "incidental"
(AI değil, chat içeriği taşımayan) WhatsApp hatırlatma template'iyle tamamen
çözülebilir — iki riski de mimari olarak by-design dışlayan bir ayrım.

### Onboarding akışı (ilk 60 saniye)

1. `public/login.php` — tek input: e-posta + "Bağlantı gönder". CSRF mevcut
   `Support\Csrf` ile.
2. POST → "E-postanı kontrol et". Arka planda `MagicLinkService::requestLink()`:
   `users` tablosunda yoksa oluştur, tek-kullanımlık **hash'lenmiş** token
   üretip `magic_links`'e yazar (TTL ~20 dk), `Mailer` ile gönderir.
3. E-postadaki linke tık → `public/magic.php?token=...` → token doğrulanır
   (hash eşleşir, süresi geçmemiş, kullanılmamış) → session'a `user_id` yazılır
   → kullanıcının hiç grubu yoksa otomatik "Grubum" adlı bir grup oluşturulur
   (grup adı sorma adımı v1a'da yok, sürtünme azaltmak için).
4. Redirect → `public/index.php` — **mevcut upload formu birebir aynı kalır**
   (dosya + KVKK checkbox + CSRF), tek fark: artık login duvarının ardında, ve
   KVKK metni genişler ("...digest hesabında saklanır, dilediğin an silebilirsin").
5. Upload sonrası: digest render'ı bugünkü haliyle aynı + artık `/gecmis.php`'den
   tekrar görülebilir.

2 form submit + 1 link tık + 1 dosya seç — "ilk 60 saniye" hedefiyle uyumlu
(gerçek süre e-posta teslimine bağlı; dev'de `LogMailer` bunu sıfırlar).

---

## Soru 2 — Para kazanma yüzeyi

Araştırıldı, kaynaklı (Temmuz 2026 itibarıyla):

- **Stripe TR'de ÇALIŞMIYOR** (net, doğrulandı): resmi desteklenen-ülke
  listesinde ([stripe.com/global](https://stripe.com/global)) Türkiye yok. Yaygın
  yanlış kanının aksine gerçek durum budur. Dolaylı yol (Stripe Atlas — ABD
  şirketi) var ama muhasebe/vergi/TL-transfer karmaşıklığı ekliyor; P0/v1 için
  **orantısız**, P1+ notu.
- **iyzico — gerçekçi yol ama şahıs şirketi ÖN KOŞUL**: standart üye işyeri
  başvurusu vergi levhası + imza sirküleri istiyor; vergi mükellefi olmayan
  bireysel başvurular reddediliyor (forum/şikayet kaynaklarıyla doğrulandı).
  **MRR tahsilatı için en az bir şahıs şirketi kurulması gerekiyor — bu, kodun
  atlatamayacağı, kurucunun kod-dışı yapması gereken bir ön koşul.** Resmi
  "Abonelik" ürünü recurring billing'i tam destekliyor (periyot, webhook);
  ilk 3 ay ücretsiz, sonra aylık 199 TL ek ürün ücreti. Komisyon oranı kaynaklar
  arası tutarsız (~%2-4 aralığı iddia ediliyor) — kesin oran DOĞRULANMADI, panelden
  teyit gerekir.
- **Havuz ödeme (N veliden toplama) için native ürün YOK** hiçbir TR sağlayıcıda
  bulunamadı. v1'de: tek ödeme linki (iyzico Link) + manuel takip yeterli;
  otomatik komisyon motoru P1+.

### Ücretsiz ↔ ücretli sınırı

**Grup başına ilk 4 digest ücretsiz, 5.'den itibaren aktif abonelik şart.**
Süre değil sayı bazlı: organizatör her hafta düzenli yüklemeyebilir, takvim
bazlı bir sınır (ör. "ilk 30 gün") pilotun değerini göstermeden tükenebilir.
4 sayısı ~1 aylık gerçek kullanım kanıtı sağlar. Uygulama noktası:
`src/Billing/Entitlement.php::canProcessDigest(int $groupId): bool`, upload
pipeline'a verilmeden hemen önce, mevcut CSRF/KVKK `fail()` deseniyle.

### iyzico entegrasyon iskeleti (P0 motoruna dokunmadan, ayrı modül)

- `src/Billing/IyzicoClient.php` — `LlmClassifier::call()` ile AYNI desen
  (çıplak `curl_init`, `.env`'den key/secret — `WABRIDGE_IYZICO_API_KEY`,
  `WABRIDGE_IYZICO_SECRET_KEY`, `WABRIDGE_IYZICO_BASE_URL`).
- `src/Billing/SubscriptionService.php` — checkout linki, durum sorgulama, iptal.
- `src/Billing/WebhookController.php` — imza doğrulama + `payment_events`'e
  idempotent yazma (`event_id UNIQUE` ile çift-işlem engeli) + `subscriptions`
  güncelleme.
- `public/billing_webhook.php` — mevcut `public/index.php` kalıbına benzer ince giriş.

**Dürüstlük notu (LlmClassifier ile aynı ilke)**: şahıs şirketi kurulana kadar
bu modül **iskelet olarak yazılır ama canlı doğrulanamaz** — README/docs'ta
açıkça "doğrulanmadı" işaretlenir, uydurma "çalışıyor" iddiası yapılmaz.

---

## Soru 3 — Test/pilot planı

**Kurucu verisine dayanmayan gerçek veri akışı**: küçük rızalı gerçek grup
(3-5 grup) → `raw_exports/` (zaten `.gitignore`'da, KVKK korumalı, doğrulandı)
→ `scripts/anonymize.php` (henüz yazılmadı — bu planın kapsamı DIŞINDA, ayrı bir
ön-iş, `docs/P0_REAL_DATA_VALIDATION.md` sürecinde zaten tanımlı) → deterministik
isim takma-adlaştırma + PII maskeleme → `tests/fixtures/real/` (yine
`.gitignore`'da). Rıza: KVKK aydınlatma metni + açık onay, ham dosya işlem
sonrası silinir (mevcut `Pipeline::processFile` deseniyle aynı).

**Sentetik veri (mevcut `synthetic_*` fixture'lar) SADECE bileşen regresyonu
içindir** — parser/sınıflama kod değişikliği kırıyor mu diye. **Ürün gerçekliğini
KANITLAMAZ**: gerçek grup emoji-spam, yarım cümle, 3 mesaja bölünmüş tek
düşünce, sesli mesaj placeholder'ı gibi kaos içerir; sentetik fixture'lar bunu
temsil etmez. Pilot başarısı sentetik test yeşilliğiyle DEĞİL, gerçek rızalı
kullanıcıların digest'i kullanmaya devam etmesiyle (ikinci, üçüncü hafta geri
gelme oranı) ölçülür.

---

## Soru 4 — Gap listesi (fazlara bölünmüş)

### v1a — Pilot-Hazır (ödeme YOK, gerçek kullanıcıyla test edilebilir)
Para akışı olmadan da pilot koşulabilir; bu faz "gelir modelinin kavramsal
olarak anlamlı olması" için gereken minimumu (hesap + kalıcılık) sağlar.

**Yeni dosyalar** (mevcut `WABridge\` PSR-4, PSR-12, `declare(strict_types=1)`
kalıbına uyar):
- `src/Storage/Database.php` (PDO/SQLite wrapper, `storage/wabridge.sqlite`),
  `src/Storage/Migrator.php` (idempotent migration), `storage/migrations/0001_init.sql`
- `src/Auth/MagicLinkService.php`, `src/Auth/AuthSession.php`
- `src/Mail/MailerInterface.php` + `CurlMailer.php` (prod) + `LogMailer.php`
  (dev/test) — `HeuristicClassifier`/`LlmClassifier` ile AYNI pluggable desen
- `src/Group/Group.php`, `src/Group/GroupRepository.php`
- `src/Digest/DigestRepository.php` — **yeni**; `DigestBuilder`'ın ürettiği
  array'i `digests` tablosuna JSON olarak yazar/okur. `DigestBuilder`'ın
  kendisine dokunmaz.
- `src/Web/AuthController.php`, `src/Web/HistoryController.php`
- `public/login.php`, `public/magic.php`, `public/gecmis.php`

**Değişen dosyalar**: `public/index.php` + `src/Web/DigestController.php`
(login gate + upload sonrası `DigestRepository::save()`; mevcut form/CSRF/KVKK
akışı korunur, KVKK metni genişletilir). `.env.example` (`WABRIDGE_DB_PATH`,
`WABRIDGE_MAIL_DRIVER`, `WABRIDGE_MAIL_*`, `WABRIDGE_APP_BASE_URL`,
`WABRIDGE_MAGIC_LINK_TTL_MIN`). `CLAUDE.md` ("hesap yok" → magic-link var +
yeni KESİN KURAL: digest saklanır ama ham .txt asla; token'lar hash'lenir).

**KVKK nüansı (kod okuyarak doğrulandı)**: `DigestBuilder`'ın `cocuk` alanı
heuristik yolda her zaman `null` (`HeuristicClassifier.php:119`, tek atandığı
yer) ama `senden_aksiyon`/`para_talepleri.ne` gibi serbest metin alanları
`summary()` (`HeuristicClassifier.php:315-323`) ile ham mesaj gövdesinin hafif
temizlenmiş hâlini taşıyor — yani **isim geçirebilir**. Digest'i kalıcı saklamak
"KVKK'dan tamamen muaf" değil; rıza metni bunu yansıtacak şekilde genişletildi,
ve hesap silme = digest'leri de silme (unutulma hakkı) v1a'nın parçası.

### v1b — Gelir-Ready (iyzico abonelik) — kod-dışı ön koşula bağlı
`src/Billing/*` (yukarıda), `public/billing_webhook.php`,
`.env.example` (`WABRIDGE_IYZICO_*`, `WABRIDGE_FREE_DIGEST_LIMIT=4`),
`DigestController::handleUpload()`'a entitlement gate.

### P1+ (bu planın kapsamı dışı, sadece işaretleniyor)
- WhatsApp Cloud API "incidental" utility-template hatırlatma (`src/Notify/WhatsAppReminder.php`)
- Havuz-ödeme otomasyonu (native ürün yok — manuel "kim ödedi" checklist)
- Organizatör/B2B2C çoklu grup UI (şema zaten 1:N, sadece UI eksik)
- `scripts/anonymize.php` (gerçek veri pilotu için ayrı ön-iş)

---

## Doğrulama planı

Mevcut `composer test` (`php tests/run.php`) yeni testleri **hiçbir değişiklik
gerektirmeden** otomatik keşfeder (`tests/Unit/*Test.php` glob + Reflection);
`tests/bootstrap.php` yeni namespace'leri (`Auth`, `Storage`, `Mail`, `Group`,
`Billing`) otomatik yükler.

**Yeni testler** (`:memory:` SQLite, dosya sistemine yan etkisi yok):
- `DatabaseTest.php` — migration idempotent mi, foreign key'ler aktif mi
- `MagicLinkTest.php` — token DB'de asla plaintext görünmez (sadece hash),
  süresi geçmiş/kullanılmış token reddedilir
- `KvkkPersistenceTest.php` — `DigestBuilder` çıktısı round-trip birebir aynı;
  kaydedilen satırların HİÇBİRİ ham export içeriğini (fixture'daki tam mesaj
  metni/gönderen adı) içermez — mevcut `KvkkTest.php` ile aynı ruhta
- `EntitlementTest.php` — 4 digest sonrası 5. çağrı reddedilir; aktif abonelik
  varsa limit uygulanmaz
- `WebhookTest.php` — sahte iyzico payload'ı (gerçek network çağrısı yok);
  geçersiz imza reddedilir; aynı `event_id` iki kez gelince tek kez işlenir

**Test runner kapsamayan, dürüstçe ayrı işaretli (LlmClassifier ile aynı ilke)**:
gerçek e-posta teslimi (dev'de `LogMailer` yeterli, prod öncesi tek smoke),
iyzico canlı akışı (key olmadan koşulamaz, kod-dışı ön koşula bağlı — tamamlanana
kadar "iskelet var, canlı doğrulanmadı").

Uçtan uca doğrulama (implementasyon sonrası, ayrı turda): `php -S localhost:8000
-t public` ile gerçek tarayıcı akışı — login→magic-link (LogMailer log'undan
linki al)→upload→digest→gecmis.php'de görünürlüğü→hesap silme→digest'in
gerçekten silindiğinin DB sorgusuyla kanıtlanması.

---

## Kapsam netliği (bu turda YAPILMADI)

Bu doküman yalnızca TASARIM. Onay alındı; v1a implementasyonu (hesap +
kalıcılık + upload entegrasyonu) **ayrı bir turda** kurulacak — motor
(`Parser`/`Classify`/`Digest`/`Pipeline`) değiştirilmeyecek, taze-bağlamlı
subagent + `verify-extraction` skill'iyle doğrulanacak, her iddia çalıştırılmış
komut çıktısıyla kanıtlanacak. v1b (iyzico) kod-dışı ön koşulun (şahıs şirketi)
durumuna göre ayrıca ele alınacak.
