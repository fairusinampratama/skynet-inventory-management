# Panduan Penggunaan Sistem Inventaris Skynet

Panduan ini ditujukan untuk admin yang bertugas mengelola data inventaris barang melalui sistem Skynet. Tidak diperlukan pengetahuan teknis untuk mengikuti panduan ini.

---

## Daftar Isi

1. [Masuk ke Sistem](#1-masuk-ke-sistem)
2. [Mengelola Data Barang](#2-mengelola-data-barang)
3. [Mencatat Pergerakan Stok](#3-mencatat-pergerakan-stok)
4. [Menyesuaikan Stok (Opname)](#4-menyesuaikan-stok-opname)
5. [Menggunakan Filter Tabel](#5-menggunakan-filter-tabel)
6. [Memantau Stok di Dashboard](#6-memantau-stok-di-dashboard)
7. [Mengekspor Data ke CSV](#7-mengekspor-data-ke-csv)
8. [Pertanyaan Umum](#8-pertanyaan-umum)

---

## 1. Masuk ke Sistem

Buka browser dan akses alamat sistem yang diberikan. Masukkan **email** dan **kata sandi** Anda, lalu klik **Masuk**.

Setelah berhasil masuk, Anda akan melihat halaman Dashboard yang menampilkan ringkasan stok secara keseluruhan.

---

## 2. Mengelola Data Barang

### Melihat Daftar Barang

Klik menu **Barang** di navigasi sebelah kiri. Anda akan melihat tabel berisi seluruh barang yang terdaftar di sistem.

Kolom-kolom yang tersedia:

- **Kode** — Kode unik barang (dihasilkan otomatis)
- **Nama** — Nama barang
- **Kategori** — Kategori barang
- **Satuan** — Satuan pengukuran (Pcs, Kg, Liter, dll.)
- **Stok Per Lokasi** — Rincian jumlah barang di setiap gudang
- **Total Stok** — Total stok keseluruhan (atau stok di gudang tertentu jika filter gudang aktif)
- **Status** — Kondisi stok saat ini

Status stok menggunakan kode warna:

| Warna | Status | Arti |
|-------|--------|------|
| Hijau | Stok Aman | Jumlah stok di atas batas minimum |
| Kuning | Stok Menipis | Stok sudah menyentuh atau di bawah batas minimum |
| Merah | Kosong / Minus | Stok habis atau terjadi selisih negatif |

### Menambah Barang Baru

1. Klik tombol **Buat** di sudut kanan atas tabel.
2. Isi semua kolom yang diperlukan:
   - **Nama** — Nama barang (wajib diisi)
   - **Kategori** — Pilih dari daftar yang tersedia
   - **Satuan** — Pilih satuan yang sesuai
   - **Stok Minimum** — Jumlah minimum sebelum sistem memberi peringatan
   - **Harga** — Harga satuan barang (opsional)
   - **Catatan** — Informasi tambahan (opsional)
3. Klik **Simpan**.

Kode barang akan dibuat otomatis oleh sistem berdasarkan kategori yang dipilih. Anda tidak perlu mengisinya secara manual.

### Mengedit Data Barang

1. Klik ikon **Edit** di baris barang yang ingin diubah.
2. Ubah data yang diperlukan.
3. Klik **Simpan**.

Mengedit data barang hanya mengubah informasi barang itu sendiri (nama, kategori, harga, dll.), **bukan** mengubah jumlah stok. Untuk mengubah jumlah stok, gunakan fitur **Pergerakan Stok** atau **Sesuaikan Stok**.

### Menghapus Barang

Barang yang sudah pernah memiliki riwayat pergerakan stok tidak dapat dihapus untuk menjaga integritas data. Sistem akan memberitahu jika penghapusan tidak dapat dilakukan.

---

## 3. Mencatat Pergerakan Stok

Semua perubahan jumlah stok harus dicatat melalui menu **Pergerakan Stok**. Ada empat jenis pergerakan:

| Jenis | Kapan Digunakan |
|-------|----------------|
| **Barang Masuk** | Saat menerima barang dari supplier atau pembelian baru |
| **Barang Keluar** | Saat barang dikeluarkan untuk digunakan atau dijual |
| **Transfer** | Saat memindahkan barang dari satu gudang ke gudang lain |
| **Penyesuaian** | Saat hasil hitung fisik tidak sesuai dengan data sistem |

### Cara Membuat Pergerakan Stok Baru

1. Klik menu **Pergerakan Stok** di navigasi.
2. Klik tombol **Buat** di sudut kanan atas.
3. Isi data pergerakan:
   - **Nomor** — Diisi otomatis, tidak perlu diubah
   - **Tanggal** — Tanggal aktual pergerakan barang
   - **Jenis Pergerakan** — Pilih salah satu dari empat jenis di atas
   - **Lokasi Asal** — Muncul untuk jenis Keluar dan Transfer (gudang asal barang)
   - **Lokasi Tujuan** — Muncul untuk jenis Masuk dan Transfer (gudang tujuan barang)
   - **Alasan Penyesuaian** — Muncul khusus untuk jenis Penyesuaian
   - **PIC** — Nama penanggung jawab pergerakan (opsional)
   - **Catatan** — Informasi tambahan (opsional)

4. Di bagian **Detail Barang**, klik **Tambah Item** untuk menambahkan baris barang:
   - Pilih **Barang** dari daftar dropdown. Dropdown hanya menampilkan barang yang memiliki stok di lokasi asal.
   - Setelah barang dipilih, sistem akan menampilkan **sisa stok** barang tersebut di lokasi asal sebagai panduan (di bawah kolom Jumlah).
   - Masukkan **Jumlah** yang ingin dipindahkan. Satuan akan tampil otomatis di sebelah kanan angka.
   - Tambahkan lebih banyak baris jika pergerakan melibatkan lebih dari satu jenis barang.

5. Klik **Simpan**.

### Perlindungan Stok Minus

Sistem akan menolak penyimpanan jika jumlah yang dimasukkan melebihi sisa stok di lokasi asal. Pesan peringatan akan muncul secara otomatis di bawah kolom Jumlah saat Anda selesai mengetik.

Contoh pesan: *"Jumlah melebihi sisa stok yang tersedia di lokasi asal (Sisa: 15 Pcs)."*

Dalam satu pergerakan, barang yang sama tidak boleh muncul lebih dari satu baris. Jika ingin memindahkan dua jumlah berbeda dari barang yang sama, gabungkan jumlahnya dalam satu baris.

---

## 4. Menyesuaikan Stok (Opname)

Fitur **Sesuaikan Stok** digunakan saat hasil penghitungan fisik barang berbeda dengan data yang ada di sistem.

### Cara Menggunakan Sesuaikan Stok

1. Buka halaman **Barang**.
2. Temukan barang yang ingin disesuaikan.
3. Klik tombol **Sesuaikan Stok** di baris barang tersebut.
4. Pada jendela yang muncul:
   - Pilih **Lokasi** tempat penyesuaian dilakukan.
   - Sistem akan menampilkan **Stok Saat Ini** di lokasi tersebut secara otomatis.
   - Masukkan **Stok Aktual** — jumlah fisik yang benar-benar ada saat ini.
   - Pilih **Alasan Penyesuaian** sesuai kondisi (misal: Selisih Opname, Barang Rusak, dll.).
   - Isi **PIC** dan **Catatan** jika diperlukan.
5. Klik **Simpan Penyesuaian**.

Sistem akan secara otomatis menghitung selisih antara stok lama dan stok aktual, lalu membuat entri pergerakan stok bertipe Penyesuaian. Anda tidak perlu menghitung selisihnya secara manual.

---

## 5. Menggunakan Filter Tabel

Filter membantu Anda menemukan data yang Anda butuhkan dengan cepat. Filter tersedia di halaman **Barang** maupun **Pergerakan Stok**.

Untuk membuka panel filter, klik ikon filter yang berada di sudut kanan atas tabel.

### Filter di Halaman Barang

| Filter | Fungsi |
|--------|--------|
| **Kategori** | Tampilkan hanya barang dari kategori tertentu |
| **Satuan** | Tampilkan hanya barang dengan satuan tertentu |
| **Status Stok** | Tampilkan barang berdasarkan kondisi stok (Aman, Menipis, Kosong, Minus) |
| **Perlu Reorder** | Tampilkan barang yang stoknya sudah menyentuh atau di bawah batas minimum |
| **Gudang / Lokasi** | Tampilkan hanya barang yang tersedia di gudang tertentu |

Tips: Jika Anda mengaktifkan filter **Gudang / Lokasi**, kolom stok di tabel akan otomatis berubah menampilkan jumlah khusus di gudang tersebut — bukan total keseluruhan.

### Filter di Halaman Pergerakan Stok

| Filter | Fungsi |
|--------|--------|
| **Jenis** | Tampilkan hanya satu jenis pergerakan tertentu |
| **Rentang Tanggal** | Tampilkan pergerakan dalam periode tertentu |
| **Gudang / Lokasi** | Tampilkan pergerakan yang melibatkan gudang tertentu (sebagai asal maupun tujuan) |
| **Barang** | Tampilkan pergerakan yang menyertakan barang tertentu |
| **PIC** | Tampilkan pergerakan berdasarkan penanggung jawab |

---

## 6. Memantau Stok di Dashboard

Halaman utama (Dashboard) menampilkan tabel **Detail Stok** yang berisi seluruh barang, diurutkan berdasarkan prioritas kondisi:

1. Paling atas: Barang dengan **stok minus** — perlu segera ditangani
2. Berikutnya: Barang yang **kosong**
3. Berikutnya: Barang dengan **stok menipis**
4. Paling bawah: Barang dengan **stok aman**

Gunakan tabel ini setiap hari untuk memantau kondisi inventaris secara cepat dan menentukan barang mana yang perlu diisi ulang terlebih dahulu.

---

## 7. Mengekspor Data ke CSV

Sistem menyediakan fitur ekspor data ke format CSV yang dapat dibuka dengan Microsoft Excel atau Google Sheets.

### Ekspor Data Barang

1. Buka halaman **Barang**.
2. Klik tombol **Ekspor CSV** di sudut kanan atas.
3. File akan langsung terunduh ke komputer Anda.

File berisi: Kode, Nama, Kategori, Satuan, Stok Saat Ini, Stok Minimum, dan Status setiap barang.

### Ekspor Riwayat Pergerakan

1. Buka halaman **Pergerakan Stok**.
2. Klik tombol **Ekspor CSV** di sudut kanan atas.
3. File akan langsung terunduh.

---

## 8. Pertanyaan Umum

**T: Saya tidak bisa memilih barang tertentu saat membuat pergerakan keluar. Kenapa?**

Dropdown barang hanya menampilkan barang yang tersedia (stoknya lebih dari 0) di lokasi asal yang dipilih. Jika barang tidak muncul di daftar, berarti barang tersebut tidak memiliki stok di lokasi tersebut. Periksa apakah lokasi asal sudah dipilih dengan benar, atau pastikan barang tersebut memang sudah pernah dimasukkan ke lokasi itu.

---

**T: Saya salah memasukkan jumlah dan sudah terlanjur tersimpan. Apa yang harus dilakukan?**

Buka halaman **Pergerakan Stok**, temukan catatan yang salah, dan klik tombol **Edit**. Anda dapat memperbaiki data di sana. Jika pergerakan sudah tidak bisa diedit, buat pergerakan baru sebagai koreksi (misalnya, jika terlanjur mencatat masuk 100 padahal harusnya 50, buat pergerakan keluar sebanyak 50 untuk mengoreksinya).

---

**T: Ada barang dengan stok minus di Dashboard. Apa yang harus dilakukan?**

Stok minus terjadi jika ada kesalahan pencatatan di masa lalu. Lakukan penghitungan fisik barang, lalu gunakan fitur **Sesuaikan Stok** untuk menormalkan kembali jumlahnya sesuai kondisi nyata.

---

**T: Apakah saya bisa menambahkan lebih dari satu barang dalam satu pergerakan?**

Ya. Di bagian **Detail Barang** pada form pergerakan stok, klik **Tambah Item** untuk menambahkan baris barang baru. Anda bisa menambahkan sebanyak yang diperlukan, selama setiap barang hanya muncul sekali per pergerakan.

---

**T: Apakah data yang sudah dihapus bisa dikembalikan?**

Data yang sudah dihapus tidak dapat dikembalikan secara langsung. Pastikan untuk berhati-hati sebelum menghapus data apapun.

---

*Panduan ini dibuat untuk sistem Skynet Inventory Management. Terakhir diperbarui: Juni 2026.*
