# Skynet Inventory Management

Laravel + Filament inventory management for Skynet warehouse operations. The app manages item masters, category-based item codes, multi-location stock movements, low/empty/negative stock reporting, and CSV exports.

## Features

- **Multi-location stock tracking** — track inventory across multiple warehouses and branches
- **Stock movement recording** — log stock-in, stock-out, transfer between locations, and adjustments
- **Real-time stock validation** — prevents negative stock by blocking movements that exceed available quantity
- **Stock adjustment (opname)** — reconcile physical counts against system records with reason tracking
- **Categorized adjustment reasons** — stock adjustment reasons are grouped by type for easier selection
- **Rich table filters** — filter items by category, unit, stock status, reorder need, and location; filter movements by date range, location, item, and PIC
- **Dynamic location view** — when filtering items by a specific warehouse, the stock column automatically shows stock at that location only
- **CSV exports** — export current stock snapshot and full movement history
- **Activity log** — all changes are recorded for audit purposes
- **Admin user guide** — see `PANDUAN_ADMIN.md` for a full usage guide in Bahasa Indonesia

## Requirements

- PHP 8.3+
- Composer
- Node.js + npm
- MySQL or MariaDB
- PHP extensions: `pdo_mysql`, `intl`, `mbstring`, `xml`

## Docker Setup

Local development uses Docker Compose with MariaDB 11.4:

```bash
cp .env.example .env
docker compose up --build -d
```

The app runs at `http://localhost:8000`; the Filament admin panel is at `http://localhost:8000/admin`.

On startup, the app container waits for MariaDB, runs migrations, seeds the default admin/settings data, clears caches, and starts `php artisan serve`.

Local Docker notes:

- Source code is not mounted into the app container. After changing PHP, Blade, CSS, JS, migrations, or seeders, run `docker compose up --build -d` again.
- Database data is persisted in the `mariadb-data` volume, so rebuilding the image does not wipe data.
- To fully reset local data, run `docker compose down -v`, then `docker compose up --build -d`.

Default MariaDB credentials:

| Setting | Value |
| --- | --- |
| Database | `skynet_inventory` |
| User | `skynet_inventory` |
| Password | `skynet_inventory` |
| Root password | `root` |

## Manual Setup

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run build
```

Default admin user:

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@skynet.local` | `password` |

Open the admin panel at `/admin`.

## Inventory Domain Notes

- The application UI and operational seed data use Indonesian business labels.
- Item codes are generated automatically from the selected item category code, for example `DST-0001`, `FDR-0001`, `IKR-0001`, `AKS-0001`, `BHP-0001`, `ONT-0001`, and `ALT-0001`.
- Barang records require name, category, unit, and minimum stock. Notes and price remain optional.
- Stock locations support `warehouse`, `branch`, and `field` types. These are currently informational labels used for reporting and grouping, not hard movement restrictions.
- Stock levels are computed in real-time from `stock_movement_lines` — there is no dedicated ledger table. The `Item::stockForLocation()` method is the single source of truth.
- Negative stock is prevented at two layers: the Filament form validates quantity against available stock on the frontend (real-time, debounced), and `ManageStockMovements` re-validates inside a `DB::transaction` on the backend as a last line of defense.
- Excel item import is available through `ExcelInventorySeeder` and is disabled by default unless `SEED_EXCEL_INVENTORY=true`.

To generate codes for legacy items without a code:

```bash
docker compose exec app php artisan items:generate-missing-codes
```

## Demo Inventory Data

Demo inventory data is separated from the default seeder so production deployments only receive the safe baseline admin/settings data.

After Docker is running and migrations have completed, load or refresh demo inventory data with:

```bash
docker compose exec app php artisan db:seed --class=DemoInventorySeeder
```

The demo seeder creates a realistic ISP warehouse training dataset:

- 53 demo items with category-based item codes.
- Item categories such as Distribusi, Feeder, IKR/PSB, ONT/Router, Aksesoris, Alat, and Bahan Habis Pakai.
- Locations `MAIN`, `KRIAN`, `SDA`, `SBYB`, `GRS`, and `FIELD`.
- Stock movements for opening balances, supplier restocks, branch transfers, daily usage, returns, and adjustments.
- Stock adjustment reasons categorized into groups: Audit, Pengurangan, and Lainnya.
- Example stock statuses: safe stock, low stock, empty stock, and negative stock.

The demo seeder is idempotent for the demo dataset. When rerun, it replaces previous demo items and demo movements with the same training dataset.

Quick trial flow:

1. Log in at `http://localhost:8000/admin` with `admin@skynet.local` / `password`.
2. Open `Barang` to inspect item codes, categories, units, and stock status examples.
3. Use the filter panel to filter by stock status, location, or reorder need.
4. Open `Pergerakan Stok` and filter by movement type, date range, location, item, or PIC.
5. Export current stock or movement history from the table header actions.
6. Refer to `PANDUAN_ADMIN.md` for a full step-by-step guide in Bahasa Indonesia.

## Deployment With Nixpacks

Deployment uses `nixpacks.toml`, `deploy.sh`, `supervisord.conf`, and nginx/php-fpm configuration under `docker/`, following the same pattern as other Skynet Laravel apps.

Set these environment variables in Coolify/Nixpacks:

```bash
APP_KEY=base64:...
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-inventory-domain
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
SEED_EXCEL_INVENTORY=false
```

`deploy.sh` waits for the database, runs migrations, seeds default admin/settings data, creates the storage link, and caches configuration/views.

Set `SEED_EXCEL_INVENTORY=true` only for deployments where the workbook at `Stock Material Skynet NEW (1).xlsx` should overwrite item master fields from Excel. Leave it `false` for normal production deploys after live inventory edits begin.

## Useful Commands

```bash
php artisan route:list --path=exports
php artisan test
composer run dev
docker compose logs -f app
```

Note: the production-style Docker image installs Composer dependencies with `--no-dev`, so PHPUnit is expected to run on the host development environment rather than inside the app container.
