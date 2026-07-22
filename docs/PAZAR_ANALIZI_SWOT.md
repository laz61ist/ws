# WABridge — Pazar Analizi ve SWOT Raporu

| Alan | Değer |
|---|---|
| Belge türü | Pazar/kullanıcı araştırması + stratejik analiz |
| Kapsam | WABridge v1a/v1b sonrası ürün konumlandırması |
| Yöntem | Gerçek, kaynaklı web araştırması (WebSearch) — uydurma veri yok |
| Durum | Onaylı — bulgular ürüne (login.php gizlilik/resend bölümü) işlendi |

## 1. Amaç

Bu rapor, WABridge'in hedeflediği sorunun (veli WhatsApp grubu kaosu) gerçek
dünyada ne kadar ciddi olduğunu, benzer ürünlere yönelik kullanıcı
şikayetlerini ve genel güven/UX risklerini kaynaklı olarak tespit eder; buradan
çıkan SWOT ile mevcut v1a/v1b uygulamasındaki somut boşlukları belirler.

## 2. Araştırma bulguları (kaynaklı)

### 2.1 Veli/okul WhatsApp grubu şikayetleri

- **TR**: Ekşi Sözlük'te veli gruplarında sabahları 150-200 mesaj biriktiği,
  dedikodu/gereksiz tartışmanın yoğun olduğu aktarılıyor.
  ([kaynak](https://eksisozluk.com/whatsapp-veli-gruplarindaki-mesaj-cilginligi--6819653))
- **TR**: Milli Eğitim Bakanlığı, öğretmenlerin bildirim yoğunluğu şikayeti
  üzerine resmi iletişimi e-okul'a taşıdı; okul gruplarında sadece öğretmen/
  temsilci mesaj atabilsin şeklinde düzenleme getirildi.
  ([kaynak](https://t24.com.tr/haber/ogretmenlerin-whatsapp-grubu-sorununu-bakanlik-cozdu-e-okul-sistemiyle-haberlesecekler,532621))
- **Global (NYC)**: Veli grupları günde 100+ mesajla stres kaynağı;
  ebeveynler "Oh my God, it's hell" diyor.
  ([kaynak](https://gothamist.com/arts-entertainment/nyc-parents-on-school-whatsapp-group-chats-oh-my-god-its-hell))
- **Global (İrlanda)**: Haftada ~100 mesaj, sessize alınsa bile 65 mesaj
  birikiyor; şikayetler ev ödevinden kayıp montlara kadar geniş bir yelpazede.
  ([kaynak](https://www.thejournal.ie/readme/parenting-and-whatsapp-6715885-May2025/))

**Sonuç**: Bildirim yorgunluğu ve kritik bilginin gürültüde kaybolması —
gerçek, şiddetli ve coğrafyadan bağımsız bir acı noktası. WABridge'in
"gürültüyü ele, lojistiği çıkar" değer önerisi doğrudan buna hitap ediyor.

### 2.2 Rakip/benzer ürünler

- **ChatRecap AI Insights**: Google Play yorumlarında "ücretli plan olmadan
  hiçbir şey çalışmıyor" şikayeti öne çıkıyor.
  ([kaynak](https://play.google.com/store/apps/details?id=com.ure.chat_aura))
- **GistGem** (WhatsApp Web eklentisi): "%100 private, veri tarayıcıdan
  çıkmıyor" iddiasını pazarlama argümanı yapıyor, 4.7 puan.
  ([kaynak](https://chromewebstore.google.com/detail/gistgem-whatsapp-group-ch/pdeglbcbdehfjfllclngapefcppbbjmp))
- **ThreadRecap**: Karar/aksiyon/anlaşmazlık/ödeme çıkarımı — WABridge'e en
  yakın konumlanan rakip. ([kaynak](https://www.threadrecap.com/en/about))
- **Remind** (okul iletişim uygulaması): Şikayetler bildirim aşırılığı üzerine.
  ([kaynak](https://justuseapp.com/en/app/522826277/remind-school-communication/reviews))
- **ClassDojo**: Veli şikayetleri çocuk verisinin nereye gittiği belirsizliği
  üzerine. ([kaynak](https://blogs.lse.ac.uk/parenting4digitalfuture/2017/01/04/classdojo-poses-data-protection-concerns-for-parents/))

**Sonuç**: Pazar boş değil; rakipler gizliliği ZATEN farklılaştırıcı olarak
kullanıyor. WABridge'in "ham veri hiç saklanmaz, işlenip anında silinir"
mimarisi bu rakiplerden daha güçlü bir iddia ama bunu ÜRÜNDE görünür kılmak
gerekiyor.

### 2.3 AI ile özel sohbet analizi — güven açığı

- Malwarebytes anketi (2026): katılımcıların %90'ı AI'a verisiyle ilgili
  güvenmiyor, %90'ı rızasız veri kullanımından endişeli.
  ([kaynak](https://www.malwarebytes.com/blog/privacy/2026/03/90-of-people-dont-trust-ai-with-their-data))
- PPC Land anketi (2026): %81'i AI'ın özel konuşmalarına erişmesinden endişeli.
  ([kaynak](https://ppc.land/81-of-consumers-fear-ai-data-access-but-daily-use-keeps-climbing/))
- Stanford Report (2025): uzun veri saklama süresi ve şeffaflık eksikliği risk
  olarak tanımlanıyor.
  ([kaynak](https://news.stanford.edu/stories/2025/10/ai-chatbot-privacy-concerns-risks-research))

> **DOĞRULANMADI (genel gözlem)**: Bu anketler genel AI/chatbot güven ölçümü;
> "WhatsApp sohbetimi bir SaaS'a yüklüyorum" senaryosuna özel bir anket
> bulunamadı — makul bir proxy olarak değerlendirildi, birebir doğrulanmadı.

**Sonuç**: Güven açığı çok büyük (%81-90). Somut, kanıtlanabilir gizlilik
taahhütleri olmadan dönüşüm ciddi şekilde engellenir.

### 2.4 Magic-link (parolasız giriş) sürtünmesi

- Magic-link deneyimi üçüncü parti e-posta servisinin hızına bağımlı;
  gecikme/spam belirsizlik yaratıyor.
  ([kaynak](https://www.baytechconsulting.com/blog/magic-links-ux-security-and-growth-impacts-for-saas-platforms-2025))
- Ayrı bir "magic link troubleshooting" yardım makalesinin varlığı, bunun
  gerçek ve tekrarlayan bir destek talebi olduğuna işaret ediyor.
  ([kaynak](https://help.heysummit.com/en/articles/8257884-magic-link-troubleshooting))
- Karşı örnek: Calendly'de parola adımı kayıtların %67'sini terk ettiriyordu;
  magic-link'e geçince tamamlama oranı %43'ten %71'e çıktı.
  ([kaynak](https://mojoauth.com/blog/how-authentication-friction-affects-conversion-rates-the-data-behind-frictionless-login))

**Sonuç**: Magic-link net kazanç sağlıyor ama spam/gecikme riski gerçek bir
sürtünme noktası — yeniden gönderme + yardım metni gün 1'den gerekli.

### 2.5 Türkiye'de abonelik yorgunluğu

- Artan yaşam maliyetiyle tüketiciler dijital abonelikleri gözden geçiriyor;
  "izle-iptal et" davranışı artıyor.
  ([kaynak](https://www.yeniasir.com.tr/ekonomi/2026/05/31/dijital-konforun-faturasi-agirlasti-abonelik-yorgunlugu))
- Unutulan otomatik yenilenen abonelikler büyük fatura oluşturuyor.
  ([kaynak](https://bigpara.hurriyet.com.tr/haberler/ekonomi-haberleri/unutulan-aboneliklere-dikkat-kucuk-odeme-buyuk-fatura_ID100913052/))

> **DOĞRULANMADI (genel gözlem)**: Bu kaynaklar genel dijital abonelik
> (yayın platformu vb.) yorgunluğuna dair; okul/veli bağlamında spesifik
> "bu işe neden para ödeyeyim" direncine dair doğrudan kaynak bulunamadı.

## 3. SWOT

| | Olumlu | Olumsuz |
|---|---|---|
| **İç** | **Güçlü yönler**: gerçek şiddetli acı noktası hedefleniyor; KVKK-öncelikli mimari (ham veri hiç saklanmaz) rakiplerin argümanından güçlü; ücretsiz-4-digest test edilebilir giriş noktası; magic-link kanıtlı dönüşüm kazancı | **Zayıf yönler**: gizlilik/güven mesajı üründe yeterince görünür değildi (düzeltildi, bkz. §4); magic-link gecikme/spam UX hazırlığı yoktu (düzeltildi); iyzico/LLM canlı değil, gerçek export verisi yok (bilinen, belgeli) |
| **Dış** | **Fırsatlar**: MEB'in "sadece admin mesaj atsın" trendi → okullar gürültü sorununu fark etmiş (B2B2C fırsatı); rakibin "ücretsiz olmadan çalışmıyor" şikayeti → ücretsiz-4-digest modelimiz farklılaştırıcı | **Tehditler**: AI güven açığı çok büyük (%81-90 güvensiz); rakipler zaten var (ChatRecap, GistGem, ThreadRecap); TR'de abonelik yorgunluğu → churn riski |

## 4. SWOT'tan çıkan eksik listesi ve durumu

| # | Eksik (SWOT kaynaklı) | Durum |
|---|---|---|
| 1 | UI'da somut, görünür gizlilik/güven mesajı yok | ✅ **Kapatıldı** — `public/login.php`'ye "Gizliliğin nasıl korunuyor" bölümü eklendi (ham veri silinir, digest'i sadece sen görürsün, hesap silinebilir, 3. servise iletilmez) |
| 2 | Magic-link gecikme/spam'e karşı UX yok | ✅ **Kapatıldı** — `login.php`'ye spam uyarısı + "Bağlantıyı tekrar gönder" butonu eklendi, e2e doğrulandı |
| 3 | iyzico canlı entegrasyon | ⚠️ **Kod-dışı ön koşula bağlı** (şahıs şirketi/sandbox key) — bilinçli, belgeli blok, bkz. `docs/PRODUCT_v1_DESIGN.md` |
| 4 | LLM sınıflandırıcı canlı doğrulama | ⚠️ **API key'e bağlı** — bilinçli, belgeli blok |
| 5 | Gerçek anonimleştirilmiş export verisi | ⚠️ **Kullanıcı girdisine bağlı** — `raw_exports/` hâlâ boş, bkz. `docs/P0_REAL_DATA_VALIDATION.md` süreci |
| 6 | B2B2C (okul/organizatör) kanalı | 📋 **P1+ fırsatı** — SWOT bunu güçlendirdi (MEB trendi), henüz kapsam dışı |

**Genel karar**: SWOT'tan çıkan, KOD İLE kapatılabilir iki somut boşluk (1 ve 2)
kapatıldı ve doğrulandı. Kalan maddeler (3-5) zaten bilinen, dürüstçe
belgelenmiş dış-bağımlı bloklardır — kodla aşılamaz. Madde 6 gelecek faz notu.
