# Kutuphane-Sistemi
# 📚 Kütüphane Yönetim ve Dijital Cüzdan Sistemi

![PHP](https://img.shields.io/badge/PHP-8.x-777bb4?style=for-the-badge&logo=php)
![MySQL](https://img.shields.io/badge/MySQL-PDO-4479A1?style=for-the-badge&logo=mysql)
![UI](https://img.shields.io/badge/UI-Custom_CSS-2f6fed?style=for-the-badge&logo=css3)

## 📌 Proje Hakkında
Bu proje, geleneksel kütüphane otomasyonlarını bir adım ileriye taşıyarak; içerisinde **E-Ticaret mantığında sepet sistemi**, **kullanıcı cüzdan yönetimi** ve **dinamik duyuru sistemi** barındıran profesyonel bir web uygulamasıdır.

## 🚀 Öne Çıkan Özellikler
* **💳 Dijital Cüzdan:** Kullanıcıların bakiyeleri üzerinden kitap kiralama ücretlerini ödeyebilmesi.
* **🛒 Sepet Sistemi:** Kitapları tek tek değil, sepete ekleyerek toplu halde kiralama imkanı.
* **🔒 Güvenli İade:** `PDO Transaction` (`beginTransaction`) yapısı ile veri kaybı yaşanmadan kitap iadesi.
* **📢 Duyuru Panosu:** Yöneticilerin sistem üzerinden anlık duyuru yayınlayabilmesi.
* **📉 Fiyat Koruma:** Kiralama tarihindeki fiyatın (`rental_price`) sabitlenerek geçmişe dönük korunması.

---

## 🛠 Teknik Mimari
Sistem, performans ve güvenlik odaklı modern PHP standartları ile geliştirilmiştir:

| Katman | Araç / Teknoloji | Teknik Detaylar |
| :--- | :--- | :--- |
| **Arka Uç** | **PHP 8.x** | `strict_types=1` tanımlaması ile katı tip güvenliği. |
| **Veritabanı** | **MySQL (PDO)** | SQL Injection korumalı Prepared Statements mimarisi. |
| **Ön Yüz** | **Modern CSS** | `:root` değişkenleri ile merkezi tema ve renk yönetimi. |
| **Güvenlik** | **RBAC** | Rol Tabanlı Erişim Kontrolü (Yönetici / Üye ayrımı). |

---

## 👥 Proje Ekibi ve Görev Dağılımı

| Birim | Ekip Üyeleri | Sorumluluk |
| :--- | :--- | :--- |
| **Proje Yöneticisi** | Muhammet Ali | Süreç yönetimi, denetim ve kalite kontrol. |
| **Yazılım Geliştirme** | Alihan, Anvarbek | CRUD işlemleri, sepet algoritmaları ve DB yönetimi. |
| **Veri Analizi** | Enes, İnci, Atamert, Eren, Medine, Muhammed, Yeliz, Emre, Seyran | Katalog verileri ve içerik araştırması. |
| **GitHub & Dağıtım** | Doğukan, Erdi | Repo yönetimi, hosting kurulumları ve sürüm kontrolü. |
| **Raporlama** | Levent | Proje ilerleme raporları ve dökümantasyon. |

---

## 📅 4 Haftalık Yol Haritası (Roadmap)
- [x] **1. Hafta:** Veritabanı şemasının tasarımı ve `auth.php` ile çekirdek bağlantı sisteminin kurulması.
- [x] **2. Hafta:** Kitap yönetim modülleri ve CSS tema motorunun (`style.css`) oluşturulması.
- [x] **3. Hafta:** Sepet mantığı, bakiye kontrol sistemleri ve ödünç alma süreçlerinin kodlanması.
- [x] **4. Hafta:** Güvenli iade sistemi, duyuru paneli ve final canlı testlerinin tamamlanması.

---

## ⚙️ Hızlı Kurulum
1. Repoyu klonlayın: `git clone https://github.com/dogukanyldrm2/Kutuphane-Sistemi.git`
2. `library_system.sql` dosyasını veritabanınıza içe aktarın.
3. `config.php` içindeki DB bilgilerini (Host, DB Name, User, Password) kendi sunucunuza göre düzenleyin.
4. Tarayıcınızdan `index.php` dosyasını çalıştırın.

---
*Geliştirici Notu: Bu yazılım, modern PHP pratikleri ve güvenli kodlama standartları gözetilerek bir ekip çalışması sonucunda ortaya çıkarılmıştır.*
