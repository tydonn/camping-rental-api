# PRD — Camping Rental Web
### React SPA (Vite + TypeScript)

**Versi:** 1.0
**Tanggal:** 23 Agustus 2026
**Pemilik Produk:** Tydon
**Tujuan dokumen:** Referensi pengembangan frontend sebagai consumer dari Camping Rental API (Laravel)

---

## 1. Latar Belakang & Tujuan

Frontend ini adalah Single Page Application yang mengonsumsi Camping Rental API (Laravel + Sanctum). Dibangun terpisah dari backend, dikomunikasikan murni lewat REST API menggunakan token autentikasi. Frontend dibangun fungsional dan rapi, dengan fokus pada konsumsi API yang baik, tanpa kompleksitas berlebih di sisi UI/UX.

## 2. Cakupan (Scope)

### 2.1 Termasuk dalam scope
- Halaman autentikasi: registrasi & login pelanggan
- Halaman katalog alat: daftar, filter kategori, pencarian, detail alat
- Alur booking: pilih alat, pilih rentang tanggal, cek ketersediaan, checkout
- Pelaporan pembayaran manual: pelanggan upload bukti transfer untuk booking
- Halaman riwayat booking milik pelanggan, termasuk status pembayaran
- Panel admin: kelola alat & kategori, daftar booking, verifikasi/tolak pembayaran, konfirmasi peminjaman/pengembalian

### 2.2 Di luar scope (versi awal)
- Aplikasi mobile native
- Desain UI kustom yang kompleks/animasi lanjutan
- Multi-bahasa

## 3. Arsitektur

React (Vite + TypeScript) dibangun sebagai SPA murni yang berkomunikasi dengan Laravel API lewat axios, menggunakan token Sanctum yang disimpan di sisi client. Routing ditangani React Router. Hasil build (folder statis) disajikan oleh Nginx pada domain yang sama dengan API, dibedakan lewat path (`/api/*` ke Laravel, sisanya ke React), sehingga tidak diperlukan konfigurasi CORS lintas origin.

## 4. Struktur Halaman

| Halaman | Rute | Akses | Deskripsi singkat |
|---|---|---|---|
| Login / Register | /login, /register | Publik | Form autentikasi pelanggan |
| Katalog | /katalog | Publik | Daftar alat, filter kategori, search |
| Detail Alat | /katalog/:id | Publik | Info alat, cek ketersediaan tanggal |
| Checkout Booking | /booking/checkout | Pelanggan | Ringkasan pesanan & buat booking |
| Lapor Pembayaran | /booking/:id/payment | Pelanggan | Upload bukti transfer untuk booking tertentu |
| Riwayat Booking | /booking/riwayat | Pelanggan | Daftar booking milik user & status terkini (termasuk status pembayaran) |
| Admin - Alat | /admin/equipment | Admin | CRUD alat & kategori |
| Admin - Pembayaran | /admin/payments | Admin | Daftar pembayaran menunggu verifikasi, verifikasi/tolak |
| Admin - Booking | /admin/bookings | Admin | Daftar booking, konfirmasi pinjam/kembali |

## 5. Struktur Proyek

Struktur folder mengikuti pemisahan tanggung jawab yang jelas agar mudah dipelihara:
- `src/api` — instance axios & fungsi pemanggilan tiap endpoint
- `src/pages` — halaman-halaman sesuai struktur rute di atas
- `src/components` — komponen UI yang dipakai ulang
- `src/hooks` — custom hooks (mis. `useAuth`, `useEquipmentAvailability`)
- `src/types` — interface TypeScript yang merefleksikan resource API (Equipment, Booking, Payment, dll)
- `src/context` — auth context untuk menyimpan token & data user

## 6. Alur Interaksi Utama

### 6.1 Booking & Pembayaran (Pelanggan)
1. Pelanggan memilih alat di katalog dan menentukan rentang tanggal sewa
2. Frontend memanggil endpoint pengecekan ketersediaan; jika stok tidak cukup, tampilkan pesan & saran tanggal lain
3. Jika tersedia, pelanggan checkout — frontend memanggil `POST /api/bookings` (status booking: pending)
4. Frontend menampilkan info rekening tujuan transfer & form upload bukti pembayaran
5. Pelanggan upload bukti transfer — frontend memanggil `POST /api/bookings/{id}/payments`
6. Pelanggan dapat memantau status pembayaran (menunggu verifikasi/terverifikasi/ditolak) di halaman riwayat booking; jika ditolak, pelanggan dapat melapor ulang

### 6.2 Admin
1. Admin login dan melihat daftar pembayaran berstatus `pending` yang menunggu verifikasi
2. Admin memverifikasi atau menolak pembayaran — frontend memanggil endpoint verify/reject; jika diverifikasi, status booking otomatis menjadi `paid` di backend
3. Admin melihat daftar booking berstatus `paid`, lalu mengonfirmasi peminjaman — frontend memanggil endpoint konfirmasi (stock berkurang di backend)
4. Saat alat kembali, admin memverifikasi lewat endpoint konfirmasi pengembalian (stock bertambah kembali)

## 7. Kebutuhan Non-Fungsional
- Autentikasi token disimpan dengan aman (mis. httpOnly cookie atau strategi penyimpanan yang meminimalkan risiko XSS)
- Penanganan error API konsisten (pesan kesalahan validasi ditampilkan per-field)
- Loading state & empty state pada seluruh halaman yang memuat data dari API
- Upload bukti transfer mendukung preview gambar sebelum dikirim & validasi ukuran/format file di sisi client
- Desain responsif dasar (mobile & desktop)

## 8. Milestone Pengembangan
1. Setup project Vite + TypeScript, routing dasar, axios instance
2. Autentikasi (login, register, penyimpanan token)
3. Halaman katalog & detail alat
4. Alur booking & cek ketersediaan
5. Alur pelaporan pembayaran manual (upload bukti transfer)
6. Panel admin: verifikasi/tolak pembayaran, kelola alat, konfirmasi booking (pinjam & kembali)
7. Polishing UI & responsive check

## 9. Metrik Keberhasilan
- Seluruh halaman berhasil mengonsumsi API tanpa error penanganan yang tidak tertangkap
- Alur booking & pelaporan pembayaran manual dapat diselesaikan dari sisi pelanggan tanpa hambatan UX
- Panel admin dapat menjalankan seluruh siklus: verifikasi/tolak pembayaran, konfirmasi peminjaman, konfirmasi pengembalian
