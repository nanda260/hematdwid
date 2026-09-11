# HematDwid - Kelola Keuangan Harian Mahasiswa

HematDwid adalah aplikasi manajemen keuangan berbasis PHP native yang dirancang untuk membantu mahasiswa mencatat pemasukan dan pengeluaran harian. Setiap pemasukan otomatis dialokasikan ke 3 kantong: **Kebutuhan Utama**, **Tabungan**, dan **Bebas Pakai**.

Aplikasi ini berjalan sebagai **Progressive Web App (PWA)** dengan dukungan mode *offline* serta keamanan berbasis **WebAuthn (Passkey/Biometrik)**.

---

## 🌟 Fitur Utama

- **Alokasi Otomatis:** Pembagian otomatis dari pemasukan ke 3 kantong sesuai rasio (Default: 55% Utama, 25% Tabungan, 20% Bebas Pakai).
- **Autentikasi Passkey (WebAuthn):** Login dan registrasi menggunakan sidik jari atau biometrik perangkat.
- **Progressive Web App (PWA):** Dapat diinstal di HP/Desktop, dilengkapi *Service Worker* dan dukungan navigasi *offline*.
- **Pencatatan & Transfer:** Pencatatan pemasukan, pengeluaran, serta fitur transfer saldo antar-kantong.
- **Riwayat & Dashboard:** Pemantauan saldo, kategori transaksi, dan riwayat berbasis API internal.

---

## 🚀 Petunjuk Instalasi

1. Salin seluruh folder proyek ke direktori web server (`htdocs/hematdwid` untuk XAMPP atau `www/hematdwid` untuk Laragon).
2. Buat database MySQL baru (misal: `hematdwid`) dan impor skema SQL Anda.
3. Sesuaikan konfigurasi database pada berkas `config/database.php` (host, nama database, username, dan password).
4. Buka `http://localhost/hematdwid/` di browser.

---

## 📖 Alur Penggunaan

1. **Daftar / Masuk:** Mendaftar akun baru (rasio awal 55/25/20 dan kategori dasar akan otomatis dibuat).
2. **Keamanan Passkey:** Tambahkan metode masuk biometrik melalui halaman **Keamanan** (`keamanan.php`).
3. **Pemasukan:** Catat pemasukan; nominal akan otomatis dipecah ke 3 kantong.
4. **Pengeluaran & Transfer:** Catat pengeluaran dari kantong yang sesuai atau lakukan pemindahan saldo antar-kantong di halaman **Transfer** (`transfer.php`).
5. **Dashboard:** Pantau ringkasan saldo dan penggunaan anggaran secara *real-time*.

---

## 🔒 Keamanan

- Password di-hash menggunakan `password_hash()` (bcrypt).
- Proteksi CSRF token pada seluruh form.
- Prepared statement (PDO) untuk mencegah SQL Injection.
- Isolasi data transaksi berdasarkan `user_id` sesi yang aktif.