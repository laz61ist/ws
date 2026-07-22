# WABridge — Kullanıcı Yolculuğu

| Alan | Değer |
|---|---|
| Belge türü | Kullanıcı deneyimi akış dokümanı (resmî) |
| Sürüm | v1a |
| Kapsam | Siteye ilk giden kullanıcıdan haftalık düzenli kullanıma kadar tüm akış |
| İlgili kod | `public/login.php`, `magic.php`, `index.php`, `gecmis.php` |

## 1. Amaç

Bu doküman, bir kullanıcının (tipik profil: teknik olmayan, okul/kreş veli
grubunda bunalmış bir ebeveyn) WABridge'e ilk geldiği andan haftalık düzenli
kullanıcıya dönüşene kadar attığı HER adımı, o adımda ne gördüğünü, ne
yaptığını ve bundan ne fayda sağladığını sırayla anlatır.

## 2. Ön koşul

Kullanıcının elinde WhatsApp'ın resmî **"Sohbeti dışa aktar"** özelliğiyle
aldığı bir `.txt` dosyası olması gerekir (Medya olmadan seçeneğiyle). Bu
dosyayı nasıl aldığı WABridge'in kapsamı dışındadır; WhatsApp'ın kendi
uygulama içi menüsünden yapılır.

## 3. Adım adım akış

### Adım 1 — Giriş sayfasına geliş (`login.php`)
Kullanıcı siteye ilk geldiğinde (veya oturumu kapalıyken herhangi bir sayfaya
gitmeye çalıştığında) otomatik olarak giriş sayfasına yönlendirilir. Sayfada
tek bir alan vardır: **e-posta adresi**. Parola yoktur, kayıt formu yoktur.

Sayfanın altında, kullanıcının güvenini kazanmak için **somut gizlilik
taahhütleri** açıkça listelenir: ham sohbetin asla saklanmayacağı, üretilen
özeti kimsenin göremeyeceği, istediği an hesabını silebileceği.

**Fayda**: Kullanıcı hiçbir kayıt formu doldurmadan, saniyeler içinde işe
başlar. Gizlilik endişesi (araştırmalarda kullanıcıların büyük çoğunluğunun
AI'a veri vermekten çekindiği tespit edilmiştir) daha ilk ekranda giderilir.

### Adım 2 — E-posta gönderimi
Kullanıcı e-postasını yazıp **"Bağlantı gönder"**e basar. Sistem arka planda:
e-postayı kontrol eder (yoksa otomatik bir hesap açar), tek kullanımlık,
20 dakika geçerli, güvenli bir bağlantı üretir ve e-postaya gönderir.

Ekranda "E-postanı kontrol et" mesajı belirir; birkaç dakika içinde gelmezse
spam/gereksiz klasörünü kontrol etmesi önerilir ve **"Bağlantıyı tekrar
gönder"** seçeneği sunulur.

**Fayda**: Kullanıcı parola hatırlamak zorunda kalmaz; e-posta gecikirse
tıkanıp kalmaz, tek tıkla yeniden deneyebilir.

### Adım 3 — Bağlantıya tıklama (`magic.php`)
Kullanıcı e-postasındaki bağlantıya tıklar. Sistem bağlantının geçerli
(süresi dolmamış, daha önce kullanılmamış) olduğunu doğrular ve kullanıcıyı
otomatik olarak içeri alır. İlk girişte kullanıcı için sessizce bir "Grubum"
kaydı oluşturulur — ayrıca bir kurulum adımı istenmez.

**Fayda**: Tek tık ile giriş tamamlanır; grup adı sormak gibi ek bir
sürtünme adımı yoktur.

### Adım 4 — Sohbeti yükleme (`index.php`)
Kullanıcı ana sayfada karşısında basit bir yükleme formu bulur: dosya seçme
alanı ve bir onay kutusu. Onay kutusu, sohbette üçüncü kişilerin verisi
olabileceğini ve ham dosyanın işlenip **anında silineceğini** açıkça belirtir.

Kullanıcı `.txt` dosyasını seçer, onay kutusunu işaretler, **"Digest
oluştur"**a basar.

**Fayda**: Kullanıcı ne olacağını tam olarak bilerek, bilinçli onay vererek
ilerler — KVKK'ya uygun, şeffaf bir süreç.

### Adım 5 — Digest'i görme
Birkaç saniye içinde (yüzlerce mesajlık bir grup için bile) ekranda
yapılandırılmış özet belirir:
- **📅 Takvim**: yaklaşan etkinlikler (tarih, saat, ne olduğu)
- **✅ Senden aksiyon**: kullanıcının yapması gereken işler
- **💰 Para talepleri**: kimin, ne için, ne kadar, ne zamana kadar
- Kaç mesajın "gürültü" (dedikodu, selamlaşma vb.) olarak elendiği

**Fayda**: Kullanıcı yüzlerce mesajı tek tek okumak yerine, 30 saniyede
"bu hafta gerçekten ne olmuş, ne yapmam gerekiyor" sorusunun cevabını alır.

### Adım 6 — Geçmişe erişim (`gecmis.php`)
Kullanıcı istediği an "Geçmiş digest'lerim" bağlantısına tıklayarak daha
önce oluşturduğu tüm digest'lerin özet listesini görebilir (hangi hafta,
kaç takvim/aksiyon/para maddesi, kaç gürültü elendi).

**Fayda**: Önceki haftaların özetine geri dönebilir; sayfa kapansa/tarayıcı
değişse de veri kaybolmaz (mevcut sistemin aksine — daha önce her digest
tek seferlikti, kapanınca kaybolurdu).

### Adım 7 — Haftalık tekrar kullanım
Kullanıcı her hafta aynı akışı (WhatsApp'tan export al → yükle) tekrarlar.
İlk **4 digest ücretsizdir** — bu, kullanıcının gerçek değeri deneyimlemesi
için tasarlanmıştır. 5. digest'ten itibaren aktif abonelik istenir; net,
Türkçe bir mesajla bilgilendirilir.

**Fayda**: Kullanıcı önce ürünün kendisi için gerçekten çalıştığını görür,
sonra ödeme kararı verir — riski kullanıcı değil ürün üstlenir.

### Adım 8 — Hesap ve veri kontrolü
Kullanıcı istediği an "Geçmiş digest'lerim" sayfasından hesabını ve tüm
digest kayıtlarını kalıcı olarak silebilir (KVKK unutulma hakkı). Bu işlem
geri alınamaz olduğu açıkça belirtilir ve onay istenir.

**Fayda**: Kullanıcı verisi üzerinde tam kontrol sahibidir; hiçbir zaman
"verilerim nerede, nasıl silinir" belirsizliği yaşamaz.

## 5. Uçtan uca özet akış şeması

```
Ziyaret → [Giriş yok] → login.php (e-posta gir)
                              │
                              ▼
                    "E-postanı kontrol et"
                    (spam uyarısı + tekrar gönder)
                              │
                              ▼
                    E-postadaki bağlantıya tıkla
                    (magic.php → otomatik grup oluşturma)
                              │
                              ▼
                    index.php: .txt yükle + KVKK onayı
                              │
                              ▼
              Digest görüntülenir (takvim/aksiyon/para/gürültü)
                              │
                              ▼
              gecmis.php: geçmiş digest'ler + hesap silme
                              │
                              ▼
              [Haftalık tekrar] → 5. digest'te abonelik istenir
```

## 6. Doğrulama notu

Bu akışın tamamı (adım 1'den 8'e) gerçek bir HTTP oturumuyla, iki bağımsız
denetim turunda uçtan uca çalıştırılıp doğrulanmıştır (bkz. `docs/P0_STATUS.md`
ve bu depodaki denetim geçmişi). Uydurma değildir — her adım çalıştırılmış
komut çıktısına dayanır.
