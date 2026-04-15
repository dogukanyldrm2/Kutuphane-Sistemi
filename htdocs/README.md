# Kutuphane Sistemi - Guncellenmis Surum

Bu surumde uye ve admin tarafina yeni moduller eklendi:

- Kitaplarda fiyat alani
- Kitaplarda kapak resmi alani (`image_path`) 
- Odunc al yerine sepet sistemi
- Uye bakiyesi ile toplu kiralama
- Hesabim ekrani ve sifre degistirme
- Admin kullanici listeleme ve kullanicilarin aldigi kitaplari izleme
- Admin tarafinda bakiye ekleme

## Yeni Dosyalar

- `cart.php` -> sepete ekleme ve kiralama tamamlama
- `account.php` -> uye hesap ekranı ve sifre degistirme
- `users.php` -> admin kullanici ve kiralama yonetimi

## Veritabani Degisiklikleri

- `users.balance`
- `books.price`
- `books.image_path`
- `borrows.rental_price`
- `cart_items` tablosu

## Not

Mevcut projede `partials/header.php`, `partials/footer.php` ve `assets/css/style.css` dosyalari yuklu degil. Bu nedenle yeni sayfalara link eklemek icin header menusu tarafinda asagidaki linkleri de eklemeniz gerekir:

- `books.php`
- `cart.php`
- `my_loans.php`
- `account.php`
- `users.php` (sadece admin)
