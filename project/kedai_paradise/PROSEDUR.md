# PROSEDUR OPERASIONAL STANDAR (SOP): PEMULIHAN DATABASE & GANGGUAN JARINGAN

**Sistem Kasir & Keuangan Kedai Paradise (SRS Rev. D.2 — NF09, BR-27, TC-D24)**

---

## 1. Keamanan Direktori Cadangan & Skrip

- Direktori `/private/` (termasuk `/private/config.local.php`, `/private/.my.cnf`, `/private/backups/`, dan `/private/logs/`) **wajib berada di luar `public_html`** agar tidak dapat diakses melalui browser publik.
- Direktori `/scripts/` dan seluruh file `*.sql`, `*.sh`, `*.md`, serta `*.log` di dalam `public_html` **wajib diblokir menggunakan `.htaccess`** (`Require all denied`).
- Cadangan database otomatis dijalankan setiap hari pukul **23:30 WIB** (setelah jam operasional kedai 08:00–22:00 WIB) dan disimpan di `private/backups/kedai-YYYYMMDD.sql.gz` dengan masa simpan 30 hari. Folder `uploads/` dicadangkan setiap minggu ke `private/backups/uploads-YYYYMMDD.tar.gz`.

---

## 2. Prosedur Pemulihan Database (Restore via phpMyAdmin)

**Target Waktu Pemulihan (RTO): Maksimal 1 Jam (<= 60 Menit)**

Gunakan prosedur ini apabila terjadi kerusakan data atau kegagalan database:

1. **Unduh File Cadangan Terbaru:**
   - Masuk ke **Hostinger hPanel** -> **Files** -> **File Manager**.
   - Buka folder `private/backups/` (satu level di atas `public_html`).
   - Pilih file cadangan terbaru (`kedai-YYYYMMDD.sql.gz`) dan unduh ke komputer lokal.

2. **Buka phpMyAdmin:**
   - Di **hPanel**, buka menu **Databases** -> **phpMyAdmin**, lalu klik tombol **Enter phpMyAdmin** pada database Kedai Paradise.
   - Klik nama database di panel sebelah kiri.

3. **Kosongkan Tabel Lama (Jika Database Masih Ada Tabel yang Rusak):**
   - Di tab **Structure** (Struktur), centang **Check all** (Pilih semua) pada ke-11 tabel.
   - Pada menu dropdown _With selected_, pilih **Drop** (Hapus), lalu konfirmasi. _(Jika terkendala Foreign Key, hapus centang "Enable foreign key checks" sebelum menekan Yes)._

4. **Impor File Cadangan (`.sql.gz`):**
   - Klik tab **Import** di bagian atas phpMyAdmin.
   - Klik **Choose File** dan pilih file `kedai-YYYYMMDD.sql.gz` yang telah diunduh (phpMyAdmin dapat membaca `.sql.gz` secara langsung tanpa perlu diekstrak).
   - Pastikan format diatur ke **SQL**, lalu klik tombol **Go** / **Impor**.

5. **Verifikasi Hasil Pemulihan:**
   - Pastikan muncul pesan sukses berwarna hijau dan seluruh **11 tabel** kembali tampil (`users`, `menu`, `menu_modifier_groups`, `menu_modifier_items`, `transactions`, `orders`, `order_item_modifiers`, `finance`, `settings`, `cash_closings`, `login_attempts`).
   - Jika folder `uploads/` juga perlu dipulihkan, ekstrak `uploads-YYYYMMDD.tar.gz` kembali ke dalam `public_html/uploads/`.
   - Masuk ke aplikasi sebagai Admin, buka halaman **Dasbor**, **Riwayat**, dan **Laporan**, lalu pastikan saldo dan transaksi terakhir sesuai.

---

## 3. Prosedur Penanganan Gangguan Internet / Outage (SRS Lampiran F #15 & BR-27)

Apabila koneksi internet di meja kasir terputus atau server tidak dapat diakses selama **lebih dari 5 menit** pada jam operasional:

### A. Saat Gangguan Berlangsung (Mode Buku Catatan Manual)

1. **Jangan menekan tombol "Bayar" berulang-ulang** jika layar menampilkan pesan _"Koneksi bermasalah. Jangan kirim ulang; periksa Riwayat dulu"_.
2. Segera beralih ke **Buku Catatan Kasir Manual** yang tersedia di laci kasir.
3. Untuk setiap pesanan pelanggan selama internet mati, catat secara berurutan:
   - Waktu pesanan (jam & menit)
   - Nomor Meja atau Bawa Pulang (`TA`)
   - Daftar menu yang dipesan, jumlah porsi, dan pilihan modifier (contoh: _2x Mie Goreng [Level 3, Telur Tambahan]_)
   - Harga satuan dan total tagihan
   - Metode pembayaran (`Tunai` atau `QRIS`) serta nominal uang diterima dan kembalian
4. Berikan struk tulis tangan sementara kepada pelanggan dan teruskan pesanan ke dapur secara manual.
5. Untuk pembayaran **QRIS**, kasir/pemilik tetap wajib memastikan dana telah masuk melalui aplikasi m-banking di ponsel sebelum menyerahkan pesanan.

### B. Setelah Koneksi Internet Pulih (Sinkronisasi ke Sistem)

1. **Periksa Menu Riwayat Terlebih Dahulu (Wajib):**
   - Buka menu **Riwayat** (`history.php`) dan periksa pesanan terakhir yang berhasil masuk tepat sebelum internet terputus.
   - Langkah ini **wajib dilakukan** untuk mencegah duplikasi input atas transaksi terakhir yang mungkin sudah tersimpan di server saat koneksi mulai tidak stabil.
2. **Input Ulang Transaksi dari Buku Manual:**
   - Sesuai kesepakatan dengan pemilik (Bapak Fajar / Ibu Fitri), masukkan seluruh transaksi dari Buku Catatan Manual ke halaman **Kasir** (`pos.php`) satu per satu sesuai urutan terjadinya transaksi, dengan memilih item, modifier, meja, dan metode pembayaran yang sama persis.
   - Tandai setiap baris di Buku Catatan Manual dengan paraf `"SUDAH INPUT"` beserta `No. Pesanan` sistem agar tidak ada pesanan yang terlewat atau tercatat dua kali.
3. **Selesaikan Status Antrian & Tutup Kasir:**
   - Buka halaman **Antrian** (`queue.php`) dan klik **Tandai Selesai** untuk pesanan manual yang sudah disajikan.
   - Pada akhir hari, lakukan rekonsiliasi laci uang tunai melalui halaman **Tutup Kasir** (`cash_close.php`).
