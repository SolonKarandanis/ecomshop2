-- English and Turkish translations of the catalogue (#81).
--
-- Products: name and description in en and tr (their Greek text is the
-- existing el translation). Categories: name in el and tr (their English name
-- is the existing en translation). Brand names aren't translated.
--
-- Rows are found by slug, so this runs on any copy of the data (local or
-- production), and running it again updates the rows instead of duplicating
-- them. A product or category missing from the database is skipped.
--
-- Needs the 2026_11_01_120000 migration first. Run with:
--   mysql -u <user> -p <database> < database/sql/catalogue_translations_en_tr.sql
--
-- Note: the HP ProBook 455 G10's Greek description repeats the HP Victus's
-- text (Victus name, RTX 3050, 2.29 kg); these translations keep it as is.

SET NAMES utf8mb4;
START TRANSACTION;

-- Products: en
INSERT INTO product_translations (product_id, locale, name, description)
SELECT id, 'en', 'Apple MacBook Air 13.6"',
'With the 13-inch MacBook Air with the M4 chip, you fly through work and play. With a stunning Liquid Retina display, up to 18 hours of battery life and an impressively thin and light design, it''s built to keep going and handle almost anything, anywhere.'
FROM products WHERE slug = 'apple-macbook-air-136'
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO product_translations (product_id, locale, name, description)
SELECT id, 'en', 'Apple MacBook Pro 16" (2021)',
'Key features:

2021 model
16.2" 3456x2234 120Hz display
10 cores, 3.2GHz
Thunderbolt 4/DisplayPort/Wi-Fi 6/HDMI
Up to 21 hours of battery life
Weight 2.2kg

The MacBook Pro is aimed at professionals who need the most power possible for work such as image and audio editing, as well as professional developers.

Its Retina display, with a resolution of 3456x2234 at 120Hz, guarantees superb picture quality with unmatched smoothness. It''s a premium, high-performance device with high build quality.

It comes with the latest Wi-Fi 6 standard, for speeds up to 40% faster than Wi-Fi 5, and with top-tier Thunderbolt 4, supporting up to two 4K displays or one 8K display. Data transfer speeds also soar, for an unrivalled experience.

Finally, battery life can reach up to 21 hours on a single charge.'
FROM products WHERE slug = 'apple-macbook-pro-16-2021'
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO product_translations (product_id, locale, name, description)
SELECT id, 'en', 'HP ProBook 455 G10 15.6"',
'Key features:

Processor: AMD Ryzen 5 7530U
RAM: 16 GB DDR4
Storage: 512 GB
Display: 15.6" IPS 1920 x 1080
Weight: 1.74 kg

The HP Victus 15-fb3701nv combines power and portability, offering an excellent experience for everyday use and gaming. With a 15.6-inch IPS FHD display, you can enjoy impressively vivid colours and wide viewing angles, ideal for work and entertainment. The 144Hz refresh rate ensures smooth visuals, especially while gaming.

The Ryzen 5-8645HS processor, at up to 4.3GHz, combined with 16GB of DDR5 RAM, guarantees fast everyday tasks and multitasking. The GeForce RTX 3050 with 6GB of graphics memory delivers better performance in demanding games and graphics applications.

The 512GB SSD gives you fast access to your data, unlike traditional hard drives, while its weight of 2.29 kg makes it portable enough to carry every day.'
FROM products WHERE slug = 'hp-probook-455-g10-156'
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO product_translations (product_id, locale, name, description)
SELECT id, 'en', 'HP Victus 15-fb3701nv 15.6"',
'Key features:

2024 model
6 cores up to 5GHz/Zen 4
Ethernet/HDMI/USB-C
Weight 2.29kg

The HP Victus 15-fb3701nv combines power and portability, offering an excellent experience for everyday use and gaming. With a 15.6-inch IPS FHD display, you can enjoy impressively vivid colours and wide viewing angles, ideal for work and entertainment. The 144Hz refresh rate ensures smooth visuals, especially while gaming.

The Ryzen 5-8645HS processor, at up to 4.3GHz, combined with 16GB of DDR5 RAM, guarantees fast everyday tasks and multitasking. The GeForce RTX 3050 with 6GB of graphics memory delivers better performance in demanding games and graphics applications.

The 512GB SSD gives you fast access to your data, unlike traditional hard drives, while its weight of 2.29 kg makes it portable enough to carry every day.'
FROM products WHERE slug = 'hp-victus-15-fb3701nv-156'
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO product_translations (product_id, locale, name, description)
SELECT id, 'en', 'Samsung Galaxy Book5 Pro 360 16" OLED',
'Key features:

Galaxy AI
Copilot AI assistance
16" 3K Dynamic AMOLED 2X display
1 x HDMI 2.1 & 2 x Thunderbolt 4

The Samsung Galaxy Book5 Pro 360 redefines the premium laptop, combining the unmatched power of artificial intelligence with a refined 2-in-1 design. Equipped with the new Intel® Core™ Ultra 7 256V (Series 2) processor, this laptop is designed to deliver top AI performance, ensuring speed, privacy and smart features that adapt to your needs.

The impressive 16" Dynamic AMOLED 2X display, with 3K resolution and 360-degree rotation, turns the device from a powerful workstation into a beautiful tablet for creating and entertainment. With 16GB of RAM and a 512GB SSD, the Galaxy Book5 Pro 360 responds in a flash, while support for the S Pen (included) takes your creativity further. With Windows 11 Pro and full integration with the Galaxy ecosystem, it''s the ideal choice for professionals and creators who demand the best in every part of their digital lives.'
FROM products WHERE slug = 'samsung-galaxy-book5-pro-360-16-oled'
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO product_translations (product_id, locale, name, description)
SELECT id, 'en', 'Samsung Galaxy Book4 15.6" FHD',
'Key features:

Wi-Fi 6 & Bluetooth v5.2
45 W USB Type-C charger
Fingerprint reader

The Samsung Galaxy Book4 is ideal for office professionals, students and everyday users who want a reliable, portable and good-looking laptop for work, entertainment and light creative use. If you need portability and good performance, it''s an excellent choice.'
FROM products WHERE slug = 'samsung-galaxy-book4-156-fhd'
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO product_translations (product_id, locale, name, description)
SELECT id, 'en', 'LG Gram Book Ultra 15" IPS FHD',
'The LG Gram Book Ultra is a notebook with a 15" display.

• 2025 model
• 15" 1920x1080 display
• Core Ultra 7 255U 2GHz/Arrow Lake
• HDMI, USB 2.0, USB 3.2
• Weight 1.65kg

It''s powered by an Intel Core Ultra 7 255U processor clocked at 2GHz.

It has 16GB of DDR5 RAM and is recommended for demanding applications such as image and video editing, music production and audio editing.

The resolution is 1920x1080 (Full HD), the standard balance between picture quality, cost and battery consumption.

It has an IPS panel. IPS displays offer wider viewing angles, accurate colours, better contrast and low input lag, less image distortion and generally more natural and accurate colour reproduction. These advantages make IPS displays ideal for many uses, such as graphic design, video editing, gaming and everyday use.

It comes with a 512GB SSD. That''s enough for the operating system, for additional programs beyond the basics and for storing a good number of photos, videos and more. If it fills up, you can buy an internal or external drive.

It comes with Windows 11 Home.

Finally, it has HDMI, USB 2.0 and USB 3.2 connections. With the HDMI port you can connect an external monitor, a TV or a home cinema system with the same port, to extend your desktop, watch films from the laptop on a bigger screen and send the sound to better speakers.'
FROM products WHERE slug = 'lg-gram-book-ultra-15-ips-fhd'
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO product_translations (product_id, locale, name, description)
SELECT id, 'en', 'LG Ultra 15U50Q-G.APB5U1 15" IPS FHD',
'This LG laptop has a 15-inch display with a resolution of 1920x1280, giving a Full HD picture. Using IPS panel technology, it offers vivid colours and wide viewing angles.

Equipped with an Intel Core i5-1235P processor and 8GB of DDR4 RAM, it delivers fast performance for everyday tasks such as browsing the web and working on documents.

Storage is generous, with a 512GB SSD main drive and a second 512GB SSD, giving you fast access to your data. With USB 3.2 connections you can connect your devices directly.

Battery life and portability are also pleasant features, as the laptop weighs only 1.75kg. It runs Windows 11 Pro, with the latest updates and features.

For extra convenience at work it has a memory card reader, and it supports Wi-Fi and Bluetooth to connect to other devices. The keyboard is International English, which suits an international workplace.

LG offers a laptop that combines sturdy construction with excellent performance, making it ideal for professionals and students.'
FROM products WHERE slug = 'lg-ultra-15u50q-gapb5u1-15-ips-fhd'
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO product_translations (product_id, locale, name, description)
SELECT id, 'en', 'Dell XPS 13 9350 13.4" OLED',
'This Dell XPS 13 9350 has an impressive 13.4-inch OLED display with a resolution of 2880x1800, for stunning picture and colours. The display is also a touchscreen, for easy, interactive navigation. It runs on a 2.2 GHz Core Ultra 7 processor with 32GB of DDR5 RAM, giving excellent performance for multitasking and demanding applications.

It includes a 1TB SSD, giving plenty of room for your data. It weighs 1.26 kg, making it ideal to carry for work or on the move. With Thunderbolt 4 connections, connecting other devices is quick and easy.

The Dell XPS 13 9350 runs Windows 11 Pro and has a UK English keyboard for easy typing. NPU support adds extra artificial intelligence capabilities. Finally, Wi-Fi/Bluetooth keeps you connected at all times.

With an elegant silver finish and a modern design, this laptop is ideal for professionals and students looking for quality and performance in one portable device.'
FROM products WHERE slug = 'dell-xps-13-9350-134-oled'
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO product_translations (product_id, locale, name, description)
SELECT id, 'en', 'Dell XPS 16 9640 16.3" OLED UHD',
'Innovative interior

Switch tasks effortlessly with a modern, easy-to-use interior that has a clean, seamless look.

Powerful creativity

Run demanding applications more smoothly and faster with powerful Intel® Core™ Ultra 9 processors. Performance-class Intel® Core™ Ultra processors provide a dedicated engine to unlock artificial intelligence features on the PC.

NVIDIA Studio

NVIDIA Studio drivers let you edit and export video quickly and boost AI-enabled creative applications for 3D, video or graphic design, with NVIDIA® 40-series graphics up to a GeForce RTX™ 4070 Laptop GPU (60W).

Immersive visuals

Watch content come to life with crisp detail, true colours and rich, cinematic sound. Enjoy exceptionally vivid colours, strong contrast and rich detail with Dolby Vision, and vivid colours on true black with an OLED display. Eyesafe® technology reduces blue light without affecting colour.

Easy to use

Set your priorities and organise your navigation with MyDell, AI-based optimisation software that automatically tunes your PC for excellent sound, video, battery and performance. Customise XPS settings, set up software and more in one convenient place.

Design innovation

Built to meet your environmental goals, this laptop is designed with at least 25% recycled materials and an innovative combination of recycled and low-emission aluminium that reduces the material''s carbon footprint by 89%.

Higher standards

This laptop is ENERGY STAR 8.0 certified and meets the highest criteria with an EPEAT Gold registration, now designated Climate+. With Climate+, this laptop meets industry best practices for decarbonisation.'
FROM products WHERE slug = 'dell-xps-16-9640-163-oled-uhd'
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO product_translations (product_id, locale, name, description)
SELECT id, 'en', 'Samsung Smart TV 85" 4K UHD QLED Q80D HDR (2024) QE85Q80DATXXH',
'NQ4 AI Gen2 Processor
Feel the power of AI in 4K
Samsung''s AI-enhanced 4K processor creates a captivating 4K viewing experience. 4K AI Upscaling, boosted by 20 AI neural networks, brings content close to 4K picture quality. The NQ4 AI Gen2 Processor upgrades picture and sound so you get a 4K viewing experience whether you''re streaming on OTT services, playing your favourite video games or watching live sport.'
FROM products WHERE slug = 'samsung-smart-tileorasi-85-4k-uhd-qled-q80d-hdr-2024-qe85q80datxxh'
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO product_translations (product_id, locale, name, description)
SELECT id, 'en', 'Samsung Smart TV 43" 4K UHD QLED The Frame LS03F HDR (2025) QE43LS03FAUXXH',
'Modern frame design
Elegantly minimalist and modern, The Frame''s design is a work of art in itself that suits any home. With a slim profile (Slim Fit Wall Mount), The Frame can sit almost flush against the wall, like a real painting (the gap between the wall and the TV may vary depending on the wall type and installation method).'
FROM products WHERE slug = 'samsung-smart-tileorasi-43-4k-uhd-qled-the-frame-ls03f-hdr-2025-qe43ls03fauxxh'
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO product_translations (product_id, locale, name, description)
SELECT id, 'en', 'LG Smart TV 65" 4K UHD OLED AI B4 HDR (2024) OLED65B46LA',
'Key features:

Refresh rate: 100Hz
OLED
Dolby Vision, HDR10, HLG
4x HDMI/2x USB
Software: webOS


A masterpiece perfected over the years
Years of commitment to innovation can''t be copied overnight. The uniquely tailored alpha chipset of the world''s leading OLED takes the viewing experience to new heights.'
FROM products WHERE slug = 'lg-smart-tileorasi-65-4k-uhd-oled-ai-b4-hdr-2024-oled65b46la'
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);


-- Products: tr
INSERT INTO product_translations (product_id, locale, name, description)
SELECT id, 'tr', 'Apple MacBook Air 13.6"',
'M4 çipli 13 inç MacBook Air ile iş ve oyunda hız kesmeden ilerlersiniz. Göz alıcı Liquid Retina ekranı, 18 saate varan pil ömrü ve etkileyici derecede ince ve hafif tasarımıyla, dayanmak ve neredeyse her şeyi her yerde başarmak için üretildi.'
FROM products WHERE slug = 'apple-macbook-air-136'
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO product_translations (product_id, locale, name, description)
SELECT id, 'tr', 'Apple MacBook Pro 16" (2021)',
'Temel özellikler:

2021 modeli
16,2" 3456x2234 120Hz ekran
10 çekirdek, 3,2GHz
Thunderbolt 4/DisplayPort/Wi-Fi 6/HDMI
21 saate kadar pil ömrü
Ağırlık 2,2kg

MacBook Pro, görüntü ve ses düzenleme gibi alanlarda mümkün olan en yüksek gücü arayan profesyonellere ve profesyonel yazılım geliştiricilere yöneliktir.

120Hz''de 3456x2234 çözünürlüklü Retina ekranı, eşsiz akıcılıkla mükemmel görüntü kalitesi sunar. Yüksek performanslı ve kaliteli yapıya sahip premium bir cihazdır.

Wi-Fi 5''e göre %40''a kadar daha yüksek hızlar sunan en yeni Wi-Fi 6 standardı ve 2 adede kadar 4K ekranı ya da bir 8K ekranı destekleyen üst düzey Thunderbolt 4 ile donatılmıştır. Veri aktarım hızları da yükselerek rakipsiz bir kullanım deneyimi sağlar.

Son olarak pil ömrü tek bir şarjla 21 saate kadar ulaşabilir.'
FROM products WHERE slug = 'apple-macbook-pro-16-2021'
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO product_translations (product_id, locale, name, description)
SELECT id, 'tr', 'HP ProBook 455 G10 15.6"',
'Temel özellikler:

İşlemci: AMD Ryzen 5 7530U
RAM: 16 GB DDR4
Depolama: 512 GB
Ekran: 15,6" IPS 1920 x 1080
Ağırlık: 1,74 kg

HP Victus 15-fb3701nv, güç ve taşınabilirliği bir araya getirerek günlük kullanım ve oyun için mükemmel bir deneyim sunar. 15,6 inç IPS FHD ekranıyla etkileyici canlı renklerin ve geniş görüş açılarının tadını çıkarabilirsiniz; iş ve eğlence için idealdir. 144Hz yenileme hızı, özellikle oyun sırasında akıcı görüntüler sağlar.

4,3GHz''e kadar çıkan Ryzen 5-8645HS işlemci, 16GB DDR5 RAM ile birlikte günlük işlerin ve çoklu görevlerin hızlı yürütülmesini garanti eder. 6GB grafik belleğine sahip GeForce RTX 3050, zorlu oyunlarda ve grafik uygulamalarında daha iyi performans sağlar.

512GB SSD, geleneksel sabit disklerin aksine verilerinize hızlı erişim sunar; 2,29 kg ağırlığı ise onu her gün yanınızda taşıyabileceğiniz kadar taşınabilir kılar.'
FROM products WHERE slug = 'hp-probook-455-g10-156'
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO product_translations (product_id, locale, name, description)
SELECT id, 'tr', 'HP Victus 15-fb3701nv 15.6"',
'Temel özellikler:

2024 modeli
5GHz''e kadar 6 çekirdek/Zen 4
Ethernet/HDMI/USB-C
Ağırlık 2,29kg

HP Victus 15-fb3701nv, güç ve taşınabilirliği bir araya getirerek günlük kullanım ve oyun için mükemmel bir deneyim sunar. 15,6 inç IPS FHD ekranıyla etkileyici canlı renklerin ve geniş görüş açılarının tadını çıkarabilirsiniz; iş ve eğlence için idealdir. 144Hz yenileme hızı, özellikle oyun sırasında akıcı görüntüler sağlar.

4,3GHz''e kadar çıkan Ryzen 5-8645HS işlemci, 16GB DDR5 RAM ile birlikte günlük işlerin ve çoklu görevlerin hızlı yürütülmesini garanti eder. 6GB grafik belleğine sahip GeForce RTX 3050, zorlu oyunlarda ve grafik uygulamalarında daha iyi performans sağlar.

512GB SSD, geleneksel sabit disklerin aksine verilerinize hızlı erişim sunar; 2,29 kg ağırlığı ise onu her gün yanınızda taşıyabileceğiniz kadar taşınabilir kılar.'
FROM products WHERE slug = 'hp-victus-15-fb3701nv-156'
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO product_translations (product_id, locale, name, description)
SELECT id, 'tr', 'Samsung Galaxy Book5 Pro 360 16" OLED',
'Temel özellikler:

Galaxy AI
Copilot yapay zekâ desteği
16" 3K Dynamic AMOLED 2X ekran
1 x HDMI 2.1 ve 2 x Thunderbolt 4

Samsung Galaxy Book5 Pro 360, yapay zekânın eşsiz gücünü zarif bir 2''si 1 arada tasarımla birleştirerek premium dizüstü bilgisayar kavramını yeniden tanımlıyor. Yeni Intel® Core™ Ultra 7 256V (Seri 2) işlemciyle donatılan bu dizüstü bilgisayar, en üst düzey yapay zekâ performansı sunmak için tasarlandı; hız, gizlilik ve ihtiyaçlarınıza uyum sağlayan akıllı özellikler sağlar.

3K çözünürlüklü ve 360 derece dönebilen etkileyici 16" Dynamic AMOLED 2X ekran, cihazı güçlü bir iş istasyonundan yaratıcılık ve eğlence için şık bir tablete dönüştürür. 16GB RAM ve 512GB SSD ile Galaxy Book5 Pro 360 şimşek hızında yanıt verir; S Pen desteği (kutuya dahil) ise yaratıcılığınızı bir üst seviyeye taşır. Windows 11 Pro ve Galaxy ekosistemiyle tam entegrasyonuyla, dijital yaşamlarının her alanında en iyisini isteyen profesyoneller ve içerik üreticileri için ideal seçimdir.'
FROM products WHERE slug = 'samsung-galaxy-book5-pro-360-16-oled'
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO product_translations (product_id, locale, name, description)
SELECT id, 'tr', 'Samsung Galaxy Book4 15.6" FHD',
'Temel özellikler:

Wi-Fi 6 ve Bluetooth v5.2
45 W USB Type-C şarj cihazı
Parmak izi okuyucu

Samsung Galaxy Book4; iş, eğlence ve hafif yaratıcı kullanım için güvenilir, taşınabilir ve şık bir dizüstü bilgisayar isteyen ofis çalışanları, öğrenciler ve günlük kullanıcılar için idealdir. Taşınabilirlik ve iyi performansa ihtiyacınız varsa mükemmel bir seçimdir.'
FROM products WHERE slug = 'samsung-galaxy-book4-156-fhd'
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO product_translations (product_id, locale, name, description)
SELECT id, 'tr', 'LG Gram Book Ultra 15" IPS FHD',
'LG Gram Book Ultra, 15" ekranlı bir dizüstü bilgisayardır.

• 2025 modeli
• 15" 1920x1080 ekran
• Core Ultra 7 255U 2GHz/Arrow Lake
• HDMI, USB 2.0, USB 3.2
• Ağırlık 1,65kg

2GHz''de çalışan Intel Core Ultra 7 255U işlemciye sahiptir.

16GB DDR5 RAM''e sahiptir ve görüntü ve video düzenleme, müzik prodüksiyonu ve ses düzenleme gibi zorlu uygulamalar için önerilir.

Çözünürlüğü 1920x1080 (Full HD) olup görüntü kalitesi, maliyet ve pil tüketimi arasında standart dengeyi sunar.

IPS panele sahiptir. IPS ekranlar daha geniş görüş açısı, renk doğruluğu, daha iyi kontrast ve düşük giriş gecikmesi, daha az görüntü bozulması ve genel olarak daha doğal ve doğru renk üretimi sağlar. Bu avantajlar, IPS ekranları grafik tasarım, video düzenleme, oyun ve günlük kullanım gibi pek çok kullanım için ideal kılar.

512GB SSD ile donatılmıştır. Bu kapasite işletim sistemi, temel programların dışındaki ek programlar ve epey fotoğraf, video vb. depolamak için yeterlidir. Dolarsa dahili veya harici bir disk satın alabilirsiniz.

Windows 11 Home işletim sistemiyle gelir.

Son olarak HDMI, USB 2.0 ve USB 3.2 bağlantılarına sahiptir. HDMI bağlantı noktasıyla aynı bağlantı noktasına sahip harici bir monitör, televizyon veya ev sinema sistemi bağlayarak masaüstünüzü genişletebilir, dizüstü bilgisayardaki filmleri daha büyük ekranlarda izleyebilir ve sesi daha kaliteli kaynaklara aktarabilirsiniz.'
FROM products WHERE slug = 'lg-gram-book-ultra-15-ips-fhd'
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO product_translations (product_id, locale, name, description)
SELECT id, 'tr', 'LG Ultra 15U50Q-G.APB5U1 15" IPS FHD',
'LG''nin bu dizüstü bilgisayarı, Full HD görüntü kalitesi sunan 1920x1280 çözünürlüklü 15 inç bir ekrana sahiptir. IPS panel teknolojisiyle canlı renkler ve geniş görüş açıları sunar.

Intel Core i5-1235P işlemci ve 8GB DDR4 RAM ile donatılan bilgisayar, internette gezinme ve belge düzenleme gibi günlük işler için hızlı performans sunar.

Depolama oldukça yeterlidir: 512GB SSD ana disk ve yine 512GB SSD ikinci disk sayesinde verilerinize hızlı erişim sağlanır. USB 3.2 bağlantılarıyla cihazlarınızı doğrudan bağlayabilirsiniz.

Pil ömrü ve taşınabilirlik de hoş özelliklerdir; bilgisayar yalnızca 1,75kg ağırlığındadır. En yeni güncellemeleri ve özellikleri sunan Windows 11 Pro ile çalışır.

İşte ek kolaylık için hafıza kartı okuyucusu bulunur; diğer cihazlarla bağlantı için Wi-Fi ve Bluetooth''u destekler. Klavye International English düzenindedir, bu da onu uluslararası çalışma ortamları için uygun kılar.

LG, sağlam yapıyı mükemmel performansla birleştiren bir bilgisayar sunuyor; bu da onu profesyoneller ve öğrenciler için ideal kılıyor.'
FROM products WHERE slug = 'lg-ultra-15u50q-gapb5u1-15-ips-fhd'
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO product_translations (product_id, locale, name, description)
SELECT id, 'tr', 'Dell XPS 13 9350 13.4" OLED',
'Bu Dell XPS 13 9350, 2880x1800 çözünürlüklü etkileyici 13,4 inç OLED ekranıyla muhteşem görüntü ve renkler sunar. Ekran aynı zamanda dokunmatiktir ve kolay, etkileşimli gezinme sağlar. 2,2 GHz Core Ultra 7 işlemci ve 32GB DDR5 RAM ile çoklu görevlerde ve zorlu uygulamalarda üstün performans sunar.

Cihazda 1TB SSD bulunur ve verileriniz için bolca alan sağlar. 1,26 kg ağırlığıyla iş için ya da yolculuklarda taşımaya idealdir. Thunderbolt 4 bağlantılarıyla diğer cihazlara bağlanmak kolay ve hızlıdır.

Dell XPS 13 9350, Windows 11 Pro ile çalışır ve kolay yazım için İngilizce UK klavyeye sahiptir. NPU desteği, yapay zekâ alanında ek yetenekler sunar. Son olarak Wi-Fi/Bluetooth bağlantısı her zaman bağlı kalmanızı sağlar.

Şık gümüş rengi ve modern tasarımıyla bu dizüstü bilgisayar, tek bir taşınabilir cihazda kalite ve performans arayan profesyoneller ve öğrenciler için idealdir.'
FROM products WHERE slug = 'dell-xps-13-9350-134-oled'
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO product_translations (product_id, locale, name, description)
SELECT id, 'tr', 'Dell XPS 16 9640 16.3" OLED UHD',
'Yenilikçi iç tasarım

Temiz ve uyumlu bir görünüm sunan modern, kullanımı kolay iç tasarımla işler arasında zahmetsizce geçiş yapın.

Güçlü yaratıcılık

Güçlü Intel® Core™ Ultra 9 işlemcilerle zorlu uygulamaları daha akıcı ve daha hızlı çalıştırın. Performans sınıfı Intel® Core™ Ultra işlemciler, bilgisayarda yapay zekâ özelliklerinin kilidini açmak için özel bir motor sunar.

NVIDIA Studio

NVIDIA Studio sürücüleri; GeForce RTX™ 4070 Laptop GPU''ya (60W) kadar NVIDIA® 40 serisi grafiklerle 3B, video veya grafik tasarım için hızlı düzenleme yapmanızı, video dışa aktarmanızı ve yapay zekâ destekli yaratıcı uygulamaları güçlendirmenizi sağlar.

Sürükleyici görseller

İçeriğin net ayrıntılar, gerçek renkler ve zengin, sinematik sesle canlandığını izleyin. Dolby Vision ile son derece canlı renklerin, güçlü kontrastın ve zengin ayrıntıların; OLED ekranla da gerçek siyah üzerinde canlı renklerin tadını çıkarın. Eyesafe® teknolojisi, renkleri etkilemeden mavi ışığı azaltır.

Kullanımı kolay

Yapay zekâ tabanlı optimizasyon yazılımı MyDell ile önceliklerinizi belirleyin ve gezinmenizi düzenleyin; bilgisayarınızı mükemmel ses, video, pil ve performans için otomatik olarak ayarlar. XPS ayarlarını özelleştirin, yazılımları kurun ve daha fazlasını tek bir kullanışlı yerden yapın.

Tasarım yeniliği

Çevresel hedeflerinizi karşılamak için üretilen bu dizüstü bilgisayar, en az %25 geri dönüştürülmüş malzemeyle ve malzemenin karbon ayak izini %89 azaltan, geri dönüştürülmüş ve düşük emisyonlu alüminyumun yenilikçi bir birleşimiyle tasarlandı.

Daha yüksek standartlar

Bu dizüstü bilgisayar ENERGY STAR 8.0 sertifikalıdır ve artık Climate+ olarak adlandırılan EPEAT Gold kaydıyla en yüksek kriterleri karşılar. Climate+ ile bu dizüstü bilgisayar, karbondan arındırma konusunda sektörün en iyi uygulamalarını karşılamıştır.'
FROM products WHERE slug = 'dell-xps-16-9640-163-oled-uhd'
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO product_translations (product_id, locale, name, description)
SELECT id, 'tr', 'Samsung Smart Televizyon 85" 4K UHD QLED Q80D HDR (2024) QE85Q80DATXXH',
'NQ4 AI Gen2 İşlemci
Yapay zekânın gücünü 4K''da hissedin
Samsung''un yapay zekâ ile güçlendirilmiş 4K işlemcisi, büyüleyici bir 4K izleme deneyimi yaratır. 20 yapay zekâ sinir ağıyla güçlendirilen 4K AI Upscaling teknolojisi, içeriği neredeyse 4K görüntü kalitesine taşır. NQ4 AI Gen2 İşlemci görüntüyü ve sesi iyileştirerek, ister OTT hizmetlerinde yayın izleyin, ister en sevdiğiniz video oyunlarını oynayın, ister canlı spor yayınlarını takip edin, 4K izleme deneyimi yaşamanızı sağlar.'
FROM products WHERE slug = 'samsung-smart-tileorasi-85-4k-uhd-qled-q80d-hdr-2024-qe85q80datxxh'
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO product_translations (product_id, locale, name, description)
SELECT id, 'tr', 'Samsung Smart Televizyon 43" 4K UHD QLED The Frame LS03F HDR (2025) QE43LS03FAUXXH',
'Modern çerçeve tasarımı
Zarif biçimde minimalist ve modern olan The Frame''in tasarımı, her eve yakışan başlı başına bir sanat eseridir. İnce profili (Slim Fit Wall Mount) sayesinde The Frame duvara neredeyse tamamen yaslanarak gerçek bir tablo gibi yerleştirilebilir (duvar ile televizyon arasındaki mesafe, duvar türüne ve montaj yöntemine göre değişebilir).'
FROM products WHERE slug = 'samsung-smart-tileorasi-43-4k-uhd-qled-the-frame-ls03f-hdr-2025-qe43ls03fauxxh'
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO product_translations (product_id, locale, name, description)
SELECT id, 'tr', 'LG Smart Televizyon 65" 4K UHD OLED AI B4 HDR (2024) OLED65B46LA',
'Temel özellikler:

Yenileme hızı: 100Hz
OLED
Dolby Vision, HDR10, HLG
4x HDMI/2x USB
Yazılım: webOS


Yıllar içinde mükemmelleştirilmiş bir sanat eseri
Yıllara dayanan inovasyon taahhüdü bir gecede taklit edilemez. Dünyanın lider OLED''inin kendine özgü alpha yonga seti, izleme deneyimini yeni zirvelere taşır.'
FROM products WHERE slug = 'lg-smart-tileorasi-65-4k-uhd-oled-ai-b4-hdr-2024-oled65b46la'
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

-- Categories: el
INSERT INTO category_translations (category_id, locale, name)
SELECT id, 'el', 'Φορητοί υπολογιστές' FROM categories WHERE slug = 'laptops'
ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO category_translations (category_id, locale, name)
SELECT id, 'el', 'Κινητά τηλέφωνα' FROM categories WHERE slug = 'smartphones'
ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO category_translations (category_id, locale, name)
SELECT id, 'el', 'Τηλεοράσεις' FROM categories WHERE slug = 'tv'
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Categories: tr
INSERT INTO category_translations (category_id, locale, name)
SELECT id, 'tr', 'Dizüstü bilgisayarlar' FROM categories WHERE slug = 'laptops'
ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO category_translations (category_id, locale, name)
SELECT id, 'tr', 'Akıllı telefonlar' FROM categories WHERE slug = 'smartphones'
ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO category_translations (category_id, locale, name)
SELECT id, 'tr', 'Televizyonlar' FROM categories WHERE slug = 'tv'
ON DUPLICATE KEY UPDATE name = VALUES(name);

COMMIT;
