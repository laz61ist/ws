# WABridge — Ürün Tanıtım Dokümanı

| Alan | Değer |
|---|---|
| Belge türü | Resmî ürün tanıtımı |
| Sürüm | v1a |
| Hedef kitle | Veliler, veli grubu yöneticileri, okul/kurum yetkilileri |

---

## WhatsApp grubunuz kalabalık. Önemli olan kayboluyor.

Her sabah telefonunuz onlarca bildirimle çalıyor. Okul veli grubunda kimin
ne yazdığını takip etmek neredeyse tam zamanlı bir iş hâline geldi.
Bir yerlerde gerçekten önemli bir duyuru var — bir imza formu, bir gezi
ücreti, bir toplantı saati — ama o, yüzlerce "günaydın", "teşekkürler",
"katılıyorum" mesajının arasında kayboluyor.

**WABridge, bu kaosu haftada bir dakikalık okunabilir bir özete çevirir.**

## Sorun gerçek ve yaygın

Bu bir varsayım değil. Veli WhatsApp gruplarının günde 100-200 mesaja
ulaştığı, ebeveynlerin bunu stres kaynağı olarak tanımladığı ve okulların
(Türkiye'de Millî Eğitim Bakanlığı dahil) bu sorunu resmî olarak fark edip
iletişim kanallarını değiştirmeye başladığı, bağımsız haber kaynaklarınca
defalarca belgelenmiştir (ayrıntılı kaynaklar: `docs/PAZAR_ANALIZI_SWOT.md`).

## Ne yapıyoruz

WABridge, WhatsApp'ın **resmî "Sohbeti dışa aktar"** özelliğiyle aldığınız
dosyayı analiz eder ve haftalık, yapılandırılmış bir özet üretir:

| Bölüm | İçerik |
|---|---|
| 📅 **Takvim** | Yaklaşan etkinlikler — tarih, saat, ne olduğu |
| ✅ **Senden Aksiyon** | Sizin yapmanız gereken işler (imza, form, malzeme) |
| 💰 **Para Talepleri** | Kim, ne için, ne kadar, ne zamana kadar istiyor |
| 🔇 **Elenen Gürültü** | Kaç mesajın dedikodu/sohbet olarak filtrelendiği |

Yüzlerce mesajı okumak yerine, gerçekten bilmeniz gerekeni **30 saniyede**
öğrenirsiniz.

## Nasıl çalışır (basitçe)

1. WhatsApp'tan sohbeti dışa aktarın (uygulamanın kendi menüsünden).
2. WABridge'e e-postanızla giriş yapın (parola yok — size bir bağlantı
   gönderiyoruz).
3. Dosyayı yükleyin.
4. Özetinizi anında görün. Sonraki haftalarınız için geçmişte saklanır.

## Gizlilik önceliğimizdir — vaat değil, mimari

AI ürünlerine karşı güvensizlik günümüzün en büyük engellerinden biridir;
bağımsız araştırmalar kullanıcıların büyük çoğunluğunun yapay zekâya kişisel
veri vermekten çekindiğini göstermektedir. Biz bunu bir pazarlama cümlesi
değil, bir mimari karar olarak ele alıyoruz:

- **Ham sohbetiniz asla kalıcı olarak saklanmaz.** Yüklendiği an işlenir ve
  hemen silinir.
- **Yalnızca sizin oluşturduğunuz özet saklanır** — geçmişe dönüp bakabilmeniz
  için. Bunu sizden başka kimse göremez.
- **Sohbet içeriğiniz hiçbir zaman WhatsApp'ın kendi API'sine veya üçüncü bir
  yapay zekâ servisine iletilmez.**
- **İstediğiniz an hesabınızı ve tüm verilerinizi kalıcı olarak silebilirsiniz.**
  Tek tıkla, geri dönüşü olmayan, tam bir silme.
- Deterministik ayrıştırma **script** ile yapılır (yapay zekâ ile değil) —
  bu, veri güvenliği yanında doğruluk ve maliyet açısından da bilinçli bir
  tercihtir. Yapay zekâ yalnızca mesajları sınıflandırmak (etkinlik/görev/
  para/gürültü) için, dar ve kontrollü bir görevde kullanılır.

## Kimin için

- **Veliler**: Okul, kreş, spor kulübü, apartman gibi kalabalık gruplarda
  önemli bilgiyi kaçırmak istemeyen herkes.
- **Sınıf temsilcileri** (asıl odak kitlemiz): Grubun "koordinatör" rolünü
  gönüllü üstlenen, haftalık özeti diğer velilerle paylaşan kişi. Bu rol
  resmî düzenlemelere rağmen (bkz. "sınıf annesi → sınıf temsilcisi" geçişi)
  dirençli ve kalıcıdır — WABridge, bu kişinin zaten yaptığı işi (gruptaki
  kaosu özetleyip paylaşmak) dakikalar yerine saniyeler alan bir işe çevirir.
  Kurumsal bir onay veya satın alma süreci gerektirmez; tek bir veli hesap
  açıp özeti kendi inisiyatifiyle paylaşabilir.
- **Grup yöneticileri**: Grubunda paylaştığı bilginin ulaştığından emin olmak
  isteyen öğretmenler, yöneticiler.

## Fiyatlandırma yaklaşımı

İlk **4 haftalık özet ücretsizdir** — ürünün gerçek değerini, taahhüt
vermeden, kendi grubunuzla deneyimlersiniz. Değerini gördükten sonra uygun
bir abonelikle devam edebilirsiniz.

## Neden şimdi

Rakip çözümler zaten var ve gizliliği pazarlama argümanı olarak kullanıyor —
biz bunu bir adım öteye taşıyıp veriyi hiç saklamama mimarisiyle, iddiadan
öteye, doğrulanabilir bir mühendislik kararına dönüştürüyoruz. WABridge,
"bu ürünü kullanınca verim ne olacak" sorusuna güvenle "hiçbir şey — çünkü
zaten saklamıyoruz" diyebilen az sayıdaki üründen biridir.

---

*Bu doküman gerçek pazar araştırmasına dayanır (bkz. `docs/PAZAR_ANALIZI_SWOT.md`).
Ürünün teknik durumu ve doğrulanmış/doğrulanmamış bileşenler için
`docs/P0_STATUS.md` ve `docs/PRODUCT_v1_DESIGN.md`'ye bakınız.*
