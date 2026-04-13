# POS Coffee Shop — Backend Setup Guide
**Stack**: CodeIgniter 4 + MariaDB 10.4 + JWT Auth

---

## 1. Install CodeIgniter 4

Pastikan sudah ada **Composer** dan **PHP 8.1+** di lokal.

```bash
# Buat project CI4 baru
composer create-project codeigniter4/appstarter pos-backend

cd pos-backend
```

---

## 2. Copy file-file dari paket ini

Salin semua file dari folder ini ke project CI4:

```
pos-backend/
├── .env                                    ← ganti dari env jadi .env
├── app/
│   ├── Config/
│   │   ├── App.php
│   │   ├── Cors.php
│   │   ├── Filters.php
│   │   └── Routes.php
│   ├── Controllers/
│   │   └── Api/
│   │       ├── BaseApiController.php
│   │       └── AuthController.php
│   ├── Filters/
│   │   ├── AuthFilter.php
│   │   └── CorsFilter.php
│   ├── Helpers/
│   │   └── uuid_helper.php
│   ├── Libraries/
│   │   └── JwtLibrary.php
│   └── Models/
│       ├── BaseModel.php
│       └── AllModels.php          ← semua model ada di sini
```

---

## 3. Konfigurasi .env

Edit file `.env`, sesuaikan:

```env
database.default.hostname = 127.0.0.1
database.default.database = pos_coffee_shop
database.default.username = root
database.default.password =            ← kosong jika XAMPP default

jwt.secret = GANTI_DENGAN_STRING_ACAK_MINIMAL_32_KARAKTER
```

Generate jwt.secret yang aman:
```bash
php -r "echo bin2hex(random_bytes(32));"
```

---

## 4. Load uuid_helper di semua controller

Tambahkan di `app/Config/Autoload.php`:

```php
public $helpers = ['url', 'uuid'];
```

Atau tambahkan di constructor masing-masing controller:
```php
helper('uuid');
```

---

## 5. Jalankan development server

```bash
php spark serve
# Server berjalan di http://localhost:8080
```

---

## 6. Test API dengan Postman / Insomnia

### Login
```
POST http://localhost:8080/api/v1/auth/login
Content-Type: application/json

{
    "username": "admin",
    "password": "password123"
}
```

### Request dengan Auth
```
GET http://localhost:8080/api/v1/auth/me
Authorization: Bearer <token dari login>
```

---

## 7. Buat user pertama (owner)

Jalankan query ini di Navicat setelah setup selesai:

```sql
INSERT INTO outlets (id, name, address, phone)
VALUES (UUID(), 'Coffee Shop Utama', 'Jl. Contoh No. 1', '08123456789');

-- Ambil id outlet yang baru dibuat
SET @outlet_id = (SELECT id FROM outlets LIMIT 1);

INSERT INTO users (id, outlet_id, role_id, name, username, password_hash)
VALUES (
    UUID(),
    @outlet_id,
    1,                          -- role owner
    'Administrator',
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
    -- password: password (bcrypt)
);
```

> Setelah login pertama, segera ganti password dari endpoint `/api/v1/users/{id}`.

---

## 8. Seed CoA (Chart of Accounts)

Uncomment dan jalankan bagian seed CoA di file `pos_coffee_shop_navicat.sql`,
isi `@oid` dengan UUID outlet yang sudah dibuat di step 7.

---

## 9. Setup Vue 3 + Vite (frontend)

```bash
# Di folder terpisah, bukan di dalam pos-backend
npm create vite@latest pos-frontend -- --template vue
cd pos-frontend
npm install
npm install axios vue-router@4 pinia
npm run dev
# Frontend berjalan di http://localhost:5173
```

---

## Struktur Response API

Semua endpoint mengembalikan format yang konsisten:

### Success
```json
{
    "status": true,
    "message": "Berhasil",
    "data": { ... }
}
```

### Error
```json
{
    "status": false,
    "message": "Pesan error",
    "errors": { ... },
    "data": null
}
```

### List dengan Pagination
```json
{
    "status": true,
    "message": "Berhasil",
    "data": {
        "items": [ ... ],
        "pagination": {
            "total": 100,
            "per_page": 20,
            "current_page": 1,
            "last_page": 5
        }
    }
}
```

---

## Alur Pengembangan Minggu 1

- [x] Database schema (MariaDB)
- [x] Config CI4 (App, CORS, Filters, Routes)
- [x] JWT Library + Auth Filter
- [x] Base Controller + Response helpers
- [x] UUID Helper
- [x] Auth Controller (login, logout, me, refresh)
- [x] Semua Model
- [ ] Product Controller
- [ ] Category Controller
- [ ] Order Controller (+ deduct stok BOM)
- [ ] Payment Controller

---

## Catatan Penting

1. **Semua transaksi order wajib pakai DB transaction** — lihat contoh di OrderController
2. **UUID di-generate di aplikasi**, bukan database — pakai `generate_uuid()`
3. **Outlet ID selalu diambil dari JWT**, bukan dari request body — security measure
4. **Kolom JSON** (modifiers, metadata, rule_value) disimpan sebagai string, parse dengan `json_decode()`
