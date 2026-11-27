# Online Sales Laravel — Web Application & REST API (Milestone M0–M11)

Aplikasi Penjualan Online berbasis **Laravel 13** yang menggabungkan antarmuka web monolitik (**Blade + Tailwind CSS**) dengan jalur layanan **REST API (`/api/v1`)** yang *stateless* menggunakan **Laravel Sanctum**, teruji melalui Postman dan Automated Feature Tests, serta di-deploy menggunakan **Docker/FrankenPHP** di **Vercel** dengan database persisten **Railway MySQL**.

---

## Production URLs

* **Aplikasi Web (Blade):** [https://laravel-online-sales-109062500018.vercel.app]
* **Base URL REST API (`/api/v1`):** [https://laravel-online-sales-109062500018.vercel.app/api/v1]

---

## Tech Stack & Arsitektur

* **Backend Framework:** Laravel 13.x (PHP 8.4)
* **Frontend Web:** Blade Templates, Tailwind CSS, Vite
* **API Authentication:** Laravel Sanctum (Bearer Token dengan kemampuan role `admin` & `customer`)
* **Testing:** PHPUnit / Laravel Feature Test (`RefreshDatabase`) & Postman Collection
* **Container & Web Server:** Docker (`Dockerfile.vercel`), FrankenPHP, Caddy
* **Cloud Hosting & Database:** Vercel Container Runtime (Stateless) + Railway MySQL (Persisten)

---

## Instalasi & Menjalankan Secara Lokal

1. **Clone repository:**
   ```bash
   git clone https://github.com/raraajeng/laravel-online-sales.git
   cd laravel-online-sales
   ```

2. **Install dependensi PHP & Node.js:**
   ```bash
   composer install
   npm install && npm run build
   ```

3. **Konfigurasi environment:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Sesuaikan konfigurasi database (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) pada file `.env`.

4. **Jalankan migrasi dan seeder:**
   ```bash
   php artisan migrate --seed
   ```

5. **Jalankan server pengembangan:**
   ```bash
   php artisan serve
   ```

---

## Pengujian Otomatis (Automated Feature Tests)

Seluruh skenario positif dan negatif API diuji menggunakan `RefreshDatabase` agar terisolasi dari database produksi:

```bash
# Menjalankan seluruh pengujian
php artisan test

# Menjalankan khusus pengujian REST API
php artisan test tests/Feature/Api
```

Daftar suite pengujian API (`tests/Feature/Api/`):
* `ProductApiTest.php` — Pengujian akses katalog publik dan filter produk.
* `AuthApiTest.php` — Pengujian login Sanctum dan penerbitan Bearer Token.
* `CheckoutApiTest.php` — Pengujian checkout *stateless*, pengurangan stok, dan pencatatan mutasi stok (`OUT`).
* `CustomerCancelOrderTest.php` — Pengujian pembatalan pesanan oleh pelanggan (`200 OK` + pengembalian stok `IN`) serta penolakan pembatalan untuk pesanan yang sudah dikonfirmasi (`409 Conflict`).

---

## Dokumentasi REST API (`/api/v1`)

### Header Standar
Setiap request ke endpoint API wajib menyertakan:
```http
Accept: application/json
Content-Type: application/json
```
Untuk endpoint yang memerlukan autentikasi, tambahkan:
```http
Authorization: Bearer <access_token>
```

### 1. Public & Authentication Endpoints

| Method | Endpoint | Akses | Deskripsi |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/products` | Public | Daftar produk aktif (mendukung filter `q`, `category`, `min_price`, `max_price`, `ready`, `sort`, `per_page`) |
| `GET` | `/api/v1/products/{slug}` | Public | Detail informasi produk berdasarkan slug |
| `GET` | `/api/v1/categories` | Public | Daftar master kategori produk |
| `GET` | `/api/v1/shipping-services` | Public | Daftar layanan ekspedisi aktif |
| `POST` | `/api/v1/auth/login` | Public | Login pengguna dan mendapatkan Bearer Token Sanctum |
| `GET` | `/api/v1/auth/me` | Authenticated | Melihat profil pengguna yang sedang login |
| `POST` | `/api/v1/auth/logout` | Authenticated | Mencabut (*revoke*) token aktif saat ini |

### 2. Customer Endpoints (`role:customer`)

| Method | Endpoint | Deskripsi |
| :--- | :--- | :--- |
| `POST` | `/api/v1/checkout` | Membuat pesanan baru secara *stateless* dari payload JSON `items` |
| `GET` | `/api/v1/orders` | Melihat riwayat pesanan milik pelanggan yang sedang login |
| `GET` | `/api/v1/orders/{order}` | Melihat detail pesanan tertentu milik pelanggan |
| `PATCH` | `/api/v1/orders/{order}/cancel` | Membatalkan pesanan milik sendiri yang masih berstatus `pending` |

### 3. Admin Endpoints (`role:admin`)

| Method | Endpoint | Deskripsi |
| :--- | :--- | :--- |
| `GET` | `/api/v1/admin/orders` | Melihat seluruh daftar pesanan pelanggan |
| `GET` | `/api/v1/admin/orders/{order}` | Melihat detail pesanan beserta data pelanggan dan item |
| `PATCH` | `/api/v1/admin/orders/{order}/confirm` | Memvalidasi pesanan dari `pending` menjadi `confirmed` |
| `PATCH` | `/api/v1/admin/orders/{order}/status` | Memperbarui alur status (`processed` -> `shipped` -> `completed`) |
| `PATCH` | `/api/v1/admin/orders/{order}/cancel` | Membatalkan pesanan dan mengembalikan stok produk |

---

## Detail Dokumentasi Endpoint Evaluasi Mandiri: Customer Cancel Order

Endpoint ini memungkinkan pelanggan membatalkan pesanannya sendiri apabila pesanan masih berstatus `pending`, sekaligus mengembalikan stok produk dan mencatat mutasi stok masuk (`IN`).

* **Method:** `PATCH`
* **URL:** `/api/v1/orders/{order}/cancel`
* **Production URL:** `[https://laravel-online-sales-109062500018.vercel.app/api/v1/orders/](https://laravel-online-sales-109062500018.vercel.app/api/v1/orders/){order}/cancel`
* **Authorization:** Bearer Token Sanctum (`role:customer`)
* **Request Body:** Tidak diperlukan (`None` / kosong).

### Response Sukses (`200 OK`)

```json
{
  "message": "Pesanan berhasil dibatalkan dan stok produk telah dikembalikan.",
  "data": {
    "id": 2,
    "code": "ORD-20261127-A1B2C3",
    "status": "cancelled",
    "recipient_name": "Rara Ajeng",
    "phone": "081234567890",
    "address": "Jl. DI Panjaitan No. 128, Purwokerto Selatan, Banyumas",
    "shipping": {
      "courier": "JNE",
      "service": "REG",
      "eta": "2-3 hari",
      "cost": 20000
    },
    "subtotal": 150000,
    "grand_total": 170000,
    "ordered_at": "2026-11-27T05:45:10.000000Z",
    "validated_at": null,
    "items": [
      {
        "id": 2,
        "product_id": 1,
        "product_name": "Produk Contoh",
        "price": 150000,
        "quantity": 1,
        "subtotal": 150000
      }
    ]
  }
}
```

### Response Error

* **`401 Unauthenticated`** — Token tidak disertakan atau tidak valid:
  ```json
  {
    "message": "Unauthenticated."
  }
  ```
* **`403 Forbidden`** — Token bukan milik pengguna dengan role `customer`:
  ```json
  {
    "message": "Anda tidak memiliki izin untuk mengakses resource ini."
  }
  ```
* **`404 Not Found`** — ID pesanan tidak ditemukan atau bukan milik pelanggan yang sedang login:
  ```json
  {
    "message": "No query results for model [App\\Models\\Order] 99"
  }
  ```
* **`409 Conflict`** — Pesanan sudah diproses atau tidak berstatus `pending`:
  ```json
  {
    "message": "Hanya pesanan berstatus pending yang dapat dibatalkan oleh pelanggan."
  }
  ```