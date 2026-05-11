# Skynet Inventory Management

Aplikasi inventory Laravel + Filament untuk operasional gudang Skynet. Aplikasi ini menyediakan master barang, pergerakan stok, saldo multi-lokasi, laporan stok menipis/kosong/minus, dan ekspor CSV.

## Kebutuhan

- PHP 8.3+
- Composer
- Node.js + npm
- MySQL/MariaDB
- Ekstensi PHP: `pdo_mysql`, `intl`, `mbstring`, `xml`

## Setup Docker

Development lokal memakai Docker Compose dan MariaDB 11.4:

```bash
cp .env.example .env
docker compose up --build -d
```

Aplikasi berjalan di `http://localhost:8000`; panel admin ada di `http://localhost:8000/admin`.

Container akan menunggu MariaDB, menjalankan migrasi, seed user/pengaturan default, membersihkan cache, lalu menjalankan `php artisan serve`.

Catatan local Docker:

- Source code tidak di-mount ke container. Setelah mengubah PHP, Blade, CSS, JS, migration, atau seeder, jalankan ulang `docker compose up --build -d`.
- Data database tetap tersimpan di volume `mariadb-data`, jadi rebuild image tidak menghapus data.
- Untuk reset penuh database lokal, hapus volume dengan `docker compose down -v`, lalu jalankan lagi `docker compose up --build -d`.

Default MariaDB:

| Variabel | Nilai |
| --- | --- |
| Database | `skynet_inventory` |
| User | `skynet_inventory` |
| Password | `skynet_inventory` |
| Root password | `root` |

## Setup Manual

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run build
```

User default:

| Peran | Email | Password |
| --- | --- | --- |
| Admin | `admin@skynet.local` | `password` |
| Gudang | `warehouse@skynet.local` | `password` |

Buka panel admin di `/admin`.

## Data Demo Inventory

Data demo dipisahkan dari seeder default supaya deployment produksi hanya mendapat user dan pengaturan dasar yang aman.

Setelah Docker berjalan dan migrasi selesai, muat atau reset data demo dengan:

```bash
docker compose exec app php artisan db:seed --class=DemoInventorySeeder
```

Seeder demo membuat dataset training inventory ISP yang lebih realistis:

- 53 barang demo dengan kode `DEMO-...`.
- Kategori barang seperti Distribusi, Feeder, IKR/PSB, ONT/Router, Aksesoris, Alat, dan Bahan Habis Pakai.
- Lokasi `MAIN`, `KRIAN`, `SDA`, `SBYB`, `GRS`, dan `FIELD`.
- Pergerakan stok berupa saldo awal, barang masuk supplier, transfer cabang, pemakaian harian, retur, dan penyesuaian stok.
- Contoh status barang: stok aman, stok menipis, kosong, dan stok minus.

Seeder ini idempotent: jika dijalankan ulang, data item dan pergerakan `DEMO-` lama akan diganti dengan dataset training yang sama.

Alur coba cepat:

1. Login di `http://localhost:8000/admin` memakai `admin@skynet.local` / `password`.
2. Buka `Barang` untuk melihat contoh stok aman, stok menipis, kosong, dan stok minus.
3. Buka `Pergerakan Stok` lalu filter berdasarkan jenis pergerakan, keperluan, atau PIC.
4. Ekspor stok saat ini atau riwayat pergerakan dari action di header tabel.

## Catatan Fitur

- Bahasa aplikasi dan data operasional memakai Bahasa Indonesia.
- Import barang tidak digunakan.
- Alias barang tidak digunakan.
- Kode barang bisa dikosongkan saat membuat barang baru; sistem akan membuat kode otomatis berdasarkan kategori.
- Untuk mengisi kode pada barang lama yang belum punya kode, jalankan:

```bash
docker compose exec app php artisan items:generate-missing-codes
```

## Deployment Nixpacks

Deployment memakai `nixpacks.toml`, `deploy.sh`, `supervisord.conf`, dan konfigurasi nginx/php-fpm di folder `docker/`, mengikuti pola aplikasi Laravel Skynet lain.

Set environment variable berikut di Coolify/Nixpacks:

```bash
APP_KEY=base64:...
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-inventory-anda
APP_LOCALE=id
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=id_ID
APP_TIMEZONE=Asia/Jakarta
DB_CONNECTION=mysql
DB_HOST=<mariadb-host>
DB_PORT=3306
DB_DATABASE=skynet_inventory
DB_USERNAME=<user>
DB_PASSWORD=<password>
CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
FILESYSTEM_DISK=public
FILAMENT_FILESYSTEM_DISK=public
```

`deploy.sh` menunggu database, menjalankan migrasi, seed user/pengaturan default, membuat storage link, lalu cache config/view.

## Command Berguna

```bash
php artisan test
php artisan route:list --path=exports
composer run dev
```
