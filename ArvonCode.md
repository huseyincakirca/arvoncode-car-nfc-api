# ArvonCode — Phoenix Master Doc (ARVONCODE-PHOENIX)

> Bu dosya **tek kaynak gerçeği (single source of truth)**.  
> Projede **her şey** (amaç, mimari, endpoint’ler, request/response, DB şeması, dosya yapısı, yapılanlar, kalanlar, checkpoint’ler) burada tutulur.  
> Yeni sekmeye geçerken bu dosyayı paylaş: Ben de **%100 buradan** devam ederim, karışıklık olmaz.

---

## 0) Kimlik

- **Proje Adı:** ArvonCode
- **Kod Adı / Hafıza Anahtarı:** ARVONCODE-PHOENIX
- **Proje Türü:** NFC + QR kodlu akıllı araç kartı sistemi (Laravel API + Flutter mobil)
- **Ana Problem:** Aracı bulan kişi/ziyaretçi, araç sahibine **anonim, hızlı, güvenli** şekilde ulaşabilsin.
- **Hedef Çıktı:**  
  1) Ziyaretçi QR/NFC okutur → araç ekranı açılır  
  2) “Hızlı mesaj” seçer veya mesaj yazar  
  3) (Opsiyonel) konum kaydeder  
  4) Araç sahibi mobil/panel üzerinden görür

---

## 1) Vizyon & Ürün Tanımı

### 1.1 Vizyon
Araç sahibine, aracın yanında kimseyle numara paylaşmadan iletişim kurulabilen; **tek dokunuşla** “mesaj bırakma / konum kaydetme / acil durum hızlı mesaj” sunan sistem.

### 1.2 Ürün Bileşenleri
- **Araç Kartı:** NFC tag + QR kod (vehicle_id ile)
- **Ziyaretçi Akışı (Guest):** QR/NFC → araç profili → hızlı mesaj / özel mesaj → gönder
- **Araç Sahibi Akışı (Owner):** login → araçlar → QR/NFC üret → gelen mesajlar → konum kayıtları

### 1.3 Kritik Tasarım İlkeleri
- **Anonimlik:** Ziyaretçi araç sahibinin telefonunu görmez.  
- **Hız:** 2 tıkta mesaj.  
- **Stabilite:** Endpoint isimleri asla rastgele değişmez.  
- **Standard JSON:** Tüm response formatı aynı olacak.

---

## 2) Teknoloji Yığını

### 2.1 Backend
- **Laravel 10**
- **Auth:** Laravel Sanctum (token)
- **DB:** MySQL/MariaDB
- **UUID:** `vehicle_id` (string, unique)

### 2.2 Mobil
- **Flutter (stable)**
- **QR okuma:** kamera
- **NFC okuma:** NDEF URI/record

---

## 3) Sistem Mimarisi (Özet)

```
[QR/NFC Kart]
     |
     v
[Flutter App / (opsiyonel web landing)]
     |
     v
[Laravel API]
     |
     v
[MySQL]
```

**En kritik karar:** QR/NFC içinde taşınan ana anahtar **vehicle_uuid** (`vehicles.vehicle_id`).  
DB’de arama `vehicles.vehicle_id = <vehicle_uuid>` ile yapılır.

---

## 4) Veri Modeli & Veritabanı Şeması

### 4.1 users
- `id` (PK)
- `name`
- `email` (unique)
- `password`
- timestamps

### 4.2 vehicles
| alan | tip | not |
|---|---|---|
| id | bigint unsigned | PK, auto |
| user_id | bigint unsigned | FK -> users.id |
| vehicle_id | varchar(255) | **unique** (UUID) |
| plate | varchar(255) | nullable |
| brand | varchar(255) | nullable |
| model | varchar(255) | nullable |
| color | varchar(255) | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

İlişki: `users (1) -> (N) vehicles`

### 4.3 messages
Amaç: Ziyaretçiden araç sahibine mesaj kaydı.

| alan | tip | not |
|---|---|---|
| id | bigint unsigned | PK |
| vehicle_id | bigint unsigned | FK -> vehicles.id (**dikkat: numeric ID**) |
| message | text | required |
| phone | varchar(255) | nullable |
| sender_ip | varchar(255) | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

> **Çok kritik nokta:** `messages.vehicle_id` burada **vehicles tablosundaki numeric id**.  
> Ziyaretçi request’te `vehicle_uuid` (vehicles.vehicle_id) gönderirse, backend önce `vehicles.id`’yi bulup messages’a onu yazmalı.

### 4.4 quick_messages (önerilen)
- `id` (PK)
- `text` (string/text)
- `is_active` (bool, default true)
- timestamps

### 4.5 locations (önerilen)
- `id` (PK)
- `vehicle_id` (FK -> vehicles.id)
- `lat` (decimal)
- `lng` (decimal)
- `accuracy` (decimal, nullable)
- `source` (string: "guest_qr" / "guest_nfc", nullable)
- timestamps

---

## 5) API Tasarımı (Single Source)

### 5.1 Response Standardı
Tüm endpoint’ler aynı format döner:

```json
{
  "ok": true,
  "message": "Human readable info",
  "data": {}
}
```

Hata:

```json
{
  "ok": false,
  "message": "Error message",
  "errors": {
    "field": ["reason"]
  }
}
```

### 5.2 Base URL
- `https://<domain>/api` (prod)
- `http://localhost:<port>/api` (dev)

---

## 6) Endpoint Listesi (Net ve Değişmez)

> Bu liste “gerçek sözleşme”. İsimler değişirse Flutter kırılır.

### 6.1 Auth

#### (A1) Register
- **POST** `/auth/register`
- Request:
```json
{ "name":"", "email":"", "password":"", "password_confirmation":"" }
```

#### (A2) Login
- **POST** `/auth/login`
- Request:
```json
{ "email":"", "password":"" }
```
- Response (örnek):
```json
{ "ok": true, "data": { "token":"...", "user":{...} } }
```

#### (A3) Logout
- **POST** `/auth/logout` (auth required)
- Header: `Authorization: Bearer <token>`

---

### 6.2 Vehicles (Owner)

#### (V1) Listele
- **GET** `/vehicles` (auth required)

#### (V2) Oluştur
- **POST** `/vehicles` (auth required)
- Request:
```json
{ "plate":"", "brand":"", "model":"", "color":"" }
```
- Backend otomatik `vehicle_id (uuid)` üretir.

#### (V3) Tek Araç
- **GET** `/vehicles/{vehicle_uuid}` (auth required)
- `{vehicle_uuid}` = `vehicles.vehicle_id`

---

### 6.3 Public (Guest) — QR/NFC

#### (P1) Araç Profilini Getir
- **GET** `/public/vehicle/{vehicle_uuid}`
- Response (örnek):
```json
{
  "ok": true,
  "data": {
    "vehicle_uuid": "...",
    "plate": "...",
    "brand": "...",
    "model": "...",
    "color": "...",
    "quick_messages": [
      {"id":1,"text":"5 dk geliyorum"},
      {"id":2,"text":"Acil, aşağıdan ulaşın"}
    ]
  }
}
```

---

### 6.4 Messages

#### (M1) Ziyaretçi Özel Mesaj Gönder
- **POST** `/public/message`
- Request:
```json
{
  "vehicle_uuid": "UUID",
  "message": "Aracınız yolu kapatıyor",
  "phone": "optional"
}
```
- Backend:
  1) `vehicles` tablosunda `vehicle_id=vehicle_uuid` bul  
  2) `messages.vehicle_id` alanına **vehicles.id** yaz  
  3) `sender_ip` kaydet

#### (M2) Owner Mesajları Listele
- **GET** `/messages` (auth required)

---

### 6.5 Quick Messages

#### (Q1) Hızlı Mesajları Listele
- **GET** `/public/quick-messages`

#### (Q2) Ziyaretçi Hızlı Mesaj Gönder
- **POST** `/public/quick-message/send`
- Request:
```json
{ "vehicle_uuid":"UUID", "quick_message_id": 2, "phone":"optional" }
```
- Not: Bu işlem `messages` tablosuna da yazılabilir (tek inbox).

---

### 6.6 Locations

#### (L1) Konum Kaydet (Guest)
- **POST** `/public/location/save`
- Request:
```json
{
  "vehicle_uuid":"UUID",
  "lat": 40.123,
  "lng": 29.456,
  "accuracy": 12.5,
  "source": "guest_qr"
}
```

#### (L2) Konumları Listele (Owner)
- **GET** `/locations` (auth required)

---

## 7) Laravel Dosya Yapısı (Referans)

```
routes/
  api.php

app/Http/Controllers/
  AuthController.php
  VehicleController.php
  PublicVehicleController.php
  MessageController.php
  QuickMessageController.php
  LocationController.php

app/Models/
  User.php
  Vehicle.php
  Message.php
  QuickMessage.php
  Location.php

database/migrations/
  xxxx_create_users_table.php
  xxxx_create_vehicles_table.php
  xxxx_create_messages_table.php
  xxxx_create_quick_messages_table.php
  xxxx_create_locations_table.php

database/seeders/
  QuickMessageSeeder.php
```

---

## 8) Flutter Dosya Yapısı (Referans)

```
lib/
  main.dart
  config/
    api_config.dart
  models/
    vehicle.dart
    quick_message.dart
    message.dart
    location.dart
  services/
    api_client.dart
    auth_service.dart
    vehicle_service.dart
    public_service.dart
  pages/
    auth/
      login_page.dart
      register_page.dart
    owner/
      vehicles_page.dart
      messages_page.dart
      locations_page.dart
    guest/
      scan_page.dart
      vehicle_profile_page.dart
      send_message_page.dart
  widgets/
    primary_button.dart
```

---

## 9) QR & NFC İçerik Formatı

### 9.1 QR İçeriği
Önerilen QR payload:
- `arvoncode://v/<vehicle_uuid>` (deep link)
veya
- `https://<domain>/v/<vehicle_uuid>`

### 9.2 NFC İçeriği
NDEF URI record:
- `arvoncode://v/<vehicle_uuid>`

---

## 10) Checkpoint Sistemi (Zorunlu)

Şablon:
```
### CHECKPOINT #N — (Tarih)
- Tamamlanan: ...
- Etkilenen dosyalar: ...
- Eklenen endpoint: ...
- Test sonucu: ...
```
---
### CHECKPOINT #1 — 2025-12-12
- Tamamlanan:
  - quick_messages tablosu oluşturuldu
  - QuickMessageSeeder yazıldı ve çalıştırıldı
- Etkilenen dosyalar:
  - database/migrations/2025_12_12_135247_create_quick_messages_table.php
  - database/seeders/QuickMessageSeeder.php
- Eklenen DB yapıları:
  - quick_messages (id, text, is_active, timestamps)
- Test sonucu:
  - quick_messages tablosunda 5 adet aktif hızlı mesaj doğrulandı

### CHECKPOINT #2 — 2025-12-12
- Tamamlanan:
  - GET /api/public/quick-messages endpoint’i çalışır hale getirildi
  - POST /api/public/quick-message/send endpoint’i çalışır hale getirildi
  - Ziyaretçiden gelen quick message, messages tablosuna kaydedildi
- Etkilenen dosyalar:
  - routes/api.php
  - app/Http/Controllers/QuickMessageController.php
  - database/migrations/2025_12_12_135247_create_quick_messages_table.php
  - database/seeders/QuickMessageSeeder.php
- Teknik notlar:
  - Laravel built-in server (php artisan serve) üzerinden test edildi
  - API istekleri 127.0.0.1:8000 portu üzerinden çalıştırıldı
  - vehicle_uuid → vehicles.vehicle_id eşleşmesi yapıldı
  - messages.vehicle_id alanına numeric vehicles.id yazıldı
- Test sonucu:
  - GET /api/public/quick-messages → 200 OK, aktif quick messages listelendi
  - POST /api/public/quick-message/send → 200 OK, message başarıyla kaydedildi




## 11) Yapılanlar / Kalanlar (Durum Tablosu)

### 11.1 Yapılanlar (Bilinen)
- [x] `vehicles` tablosu mevcut (DESCRIBE çıktısı görüldü)
- [x] `messages` migration hazır (paylaşıldı)
- [x] Genel akış net: QR/NFC → vehicle_uuid → profil/mesaj/konum
- [x] quick_messages tablosu ve varsayılan hızlı mesajlar (seeder ile) eklendi
- [x] Public quick_messages listeleme endpoint’i (GET /api/public/quick-messages)
- [x] Public quick_message gönderme endpoint’i (POST /api/public/quick-message/send)
- [x] Quick message → messages tablosuna kayıt akışı tamamlandı



> Not: Endpoint’lerin “kesin çalışır” listesini repo/dosya içeriğiyle doğrulayıp buraya kilitleyeceğiz.

### 11.2 Kalanlar (Sırayla)
1) QuickMessage migration + seeder + public endpoint’ler  
2) Public vehicle profile endpoint’i standardize et  
3) Public message endpoint’i (vehicle_uuid -> vehicles.id mapping kesin)  
4) Location save + owner list  
5) Flutter scan → profile → quick message → send  
6) Owner ekranları (messages/locations)  
7) Rate limit + basic abuse protection

---

## 12) Test Planı (Minimum)

### 12.1 Backend Smoke Test
- Register → Login → token al
- POST /vehicles → uuid dönüyor mu?
- GET /public/vehicle/{uuid} → araç + quick messages geliyor mu?
- POST /public/message → messages tablosuna yazıyor mu?
- POST /public/location/save → locations tablosuna yazıyor mu?
- GET /messages (owner) → sadece owner araçlarına ait mi?
- GET /locations (owner) → sadece owner araçlarına ait mi?

### 12.2 Flutter Smoke Test
- QR okutup deeplink alıyor mu?
- vehicle profile çekiyor mu?
- quick message gönderiyor mu?
- özel mesaj gönderiyor mu?
- konum kaydediyor mu?

---

## 13) Değişiklik Günlüğü (Çok Önemli)

Şablon:
```
### [YYYY-MM-DD] Değişiklik
- Ne değişti:
- Neden:
- Etkilenen endpoint/dosya:
- Flutter etkisi:
```

---

## 14) Çalışma Protokolü (Seninle Nasıl Çalışacağız)

1) **Her yeni oturumda** bu dosyayı gönderiyorsun.  
2) Ben sadece bu dosyaya dayanarak “şu an durum” çıkarıyorum.  
3) Yeni endpoint/dosya adı önermeden önce bu dosyadaki standartlara bakarım.  
4) Bir adımı bitirdiğinde:
   - Sen **Checkpoint** eklersin
   - “Değişiklik Günlüğü”ne yazarsın
5) Yeni sekmeye geçince:
   - Dosyayı yapıştırırsın
   - “ARVONCODE-PHOENIX devam” dersin
   - Ben kaldığımız checkpoint’ten yürürüm

---

## 15) Şu An Başlamak İçin 1 Numara Adım (Öneri)

**QuickMessage sistemi** en önce.  
Hedef:
- `quick_messages` migration
- `QuickMessageSeeder`
- `GET /public/quick-messages`
- `POST /public/quick-message/send` (messages tablosuna da yaz)

---

# EK: Kırmızı Çizgiler

- Endpoint isimleri keyfine göre değişmez.
- `vehicle_uuid` (public) ile `vehicles.id` (internal) karıştırılırsa proje sürekli kırılır.
- Flutter “hangi endpoint’i çağırıyor?” sorusu bu dosyada her zaman net olmalı.
