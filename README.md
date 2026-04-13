# POS Coffee Shop — Full Stack Setup Guide
**Backend**: CodeIgniter 4 + MariaDB 10.4
**Frontend**: Vue 3 + Vite

---

## STRUKTUR FILE YANG PERLU DICOPY

### Backend — copy ke `C:\xampp\htdocs\pos-backend\`
```
app/
├── Controllers/Api/
│   ├── CategoryController.php   ← BARU
│   ├── ProductController.php    ← BARU
│   ├── MaterialController.php   ← BARU (include RecipeController)
│   ├── OrderController.php      ← BARU (inti POS)
│   ├── DiscountController.php   ← BARU
│   └── ReportController.php     ← BARU
└── Libraries/
    └── DiscountEngine.php       ← BARU
```

### Frontend — copy ke `C:\xampp\htdocs\pos-frontend\`
```
index.html
package.json
vite.config.js
src/
├── main.js
├── App.vue
├── assets/main.css
├── router/index.js
├── stores/index.js
├── services/api.js
├── layouts/AppLayout.vue
└── views/
    ├── LoginView.vue
    ├── KasirView.vue       ← UI kasir utama
    ├── OrderView.vue       ← riwayat order
    ├── ProductView.vue     ← manajemen produk
    ├── MaterialView.vue    ← stok bahan baku
    ├── DiscountView.vue    ← manajemen diskon
    └── ReportView.vue      ← laporan harian
```

---

## CARA SETUP FRONTEND

```powershell
# Buka PowerShell baru, masuk ke folder frontend
cd C:\xampp\htdocs\pos-frontend

# Install dependencies
npm install

# Jalankan dev server
npm run dev
# Frontend berjalan di http://localhost:5173
```

---

## CARA JALANKAN SEKARANG (dua terminal)

**Terminal 1 — Backend:**
```powershell
cd C:\xampp\htdocs\pos-backend
php spark serve
# http://localhost:8080
```

**Terminal 2 — Frontend:**
```powershell
cd C:\xampp\htdocs\pos-frontend
npm run dev
# http://localhost:5173
```

Buka browser ke **http://localhost:5173**
Login dengan: `admin` / `password`

---

## ALUR KERJA KASIR

1. Buka `http://localhost:5173`
2. Login sebagai admin
3. Halaman Kasir tampil otomatis
4. Klik produk → masuk ke cart
5. Atur qty dengan tombol +/-
6. Opsional: isi kode voucher → klik Pakai
7. Pilih metode bayar (Cash / QRIS / EDC)
8. Klik **Bayar Rp xxx** → order dibuat + stok berkurang + jurnal otomatis terposting
9. Modal konfirmasi muncul → klik **Order Baru** untuk reset cart

---

## API ENDPOINT YANG SUDAH AKTIF

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| POST | /api/v1/auth/login | Login |
| GET  | /api/v1/categories | List kategori |
| GET  | /api/v1/products | List produk |
| POST | /api/v1/orders | Buat order + deduct stok |
| POST | /api/v1/orders/{id}/payment | Proses bayar |
| GET  | /api/v1/orders | Riwayat order |
| POST | /api/v1/discounts/calculate | Preview diskon |
| GET  | /api/v1/reports/sales | Laporan penjualan |
| GET  | /api/v1/reports/products | Produk terlaris |
| GET  | /api/v1/reports/profit-loss | Laba rugi |
| GET  | /api/v1/materials/low-stock | Alert stok rendah |

---

## FITUR YANG SUDAH JALAN

- [x] Login / logout dengan JWT
- [x] Tampilan kasir — pilih produk, cart, checkout
- [x] Buat order — validasi stok + deduct otomatis via BOM
- [x] Discount engine — priority, stackable, voucher, time-based rules
- [x] Posting jurnal akuntansi otomatis tiap transaksi
- [x] Update kas besar otomatis saat bayar
- [x] Laporan penjualan harian
- [x] Laporan produk terlaris
- [x] Laporan laba rugi ringkasan
- [x] Monitor stok bahan baku + alert rendah
- [x] Adjust stok manual
- [x] Manajemen diskon + voucher code
- [x] Riwayat order dengan detail

## NEXT — Phase 2 (setelah go live)
- [ ] QRIS Dinamis via Midtrans
- [ ] Virtual Account H2H
- [ ] Form tambah/edit produk lengkap
- [ ] Manajemen bundle
- [ ] Laporan neraca lengkap
- [ ] Export PDF / Excel
