# Kelola Keuangan Harian Mahasiswa

Aplikasi PHP native (tanpa framework) untuk mencatat pemasukan dan pengeluaran
harian, dengan setiap pemasukan otomatis dibagi ke 3 kantong: Kebutuhan Utama,
Tabungan, dan Bebas Pakai.

## Instalasi (XAMPP / Laragon / sejenisnya)

1. Salin folder ini ke `htdocs/keuangan-mahasiswa` (XAMPP) atau `www/keuangan-mahasiswa` (Laragon).
2. Buat database dengan menjalankan `sql/database.sql` di phpMyAdmin atau via CLI:
   ```
   mysql -u root -p < sql/database.sql
   ```
3. Sesuaikan kredensial database di `config/database.php` jika perlu (default: host `localhost`, user `root`, password kosong).
4. Buka `http://localhost/keuangan-mahasiswa/` di browser.
5. Klik **Daftar** untuk membuat akun pertama - kategori dasar akan dibuat otomatis dan presentase default diset 55% Kebutuhan Utama / 25% Tabungan / 20% Bebas Pakai.

## Struktur Folder

```
keuangan-mahasiswa/
├── assets/
│   ├── css/style.css
│   └── js/main.js
├── config/
│   └── database.php
├── includes/
│   ├── auth.php
│   ├── functions.php
│   ├── header.php
│   └── footer.php
├── sql/
│   └── database.sql
├── index.php
├── login.php
├── register.php
├── logout.php
├── dashboard.php
├── pemasukan.php
├── pengeluaran.php
├── kategori.php
└── presentase.php
```

## Alur Pengguna

1. **Daftar / Masuk** → sistem membuat baris pengaturan presentase (55/25/20) dan kategori dasar.
2. **Kelola Kategori** → tambah kategori pemasukan (mis. Uang Saku) dan pengeluaran (mis. Makan, dengan kantong sumber default).
3. **Presentase** → sesuaikan pembagian jika perlu, total wajib 100%.
4. **Pemasukan** → catat pemasukan; sistem otomatis membuat 3 baris alokasi (utama/nabung/bebas) sesuai presentase saat itu.
5. **Pengeluaran** → catat pengeluaran dan pilih kantong sumber dana (otomatis tersaran dari kategori, bisa diubah).
6. **Dashboard** → pantau saldo tiap kantong, persentase pemakaian bulan ini, transaksi terbaru, dan sebaran pengeluaran per kategori.

## Catatan Keamanan

- Password di-hash dengan `password_hash()` (bcrypt).
- Semua form dilindungi CSRF token.
- Semua query menggunakan prepared statement (PDO).
- Setiap query transaksi difilter `user_id` agar data antar akun terisolasi.
