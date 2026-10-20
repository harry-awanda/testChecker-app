# TestChecker
Aplikasi koreksi tes untuk satu sekolah. Antarmuka berbahasa Indonesia; penamaan teknis berbahasa Inggris.

## Persiapan lokal
- PHP 8.2+, Composer, Node 22+, MySQL 8+/MariaDB.
- Jalankan composer install dan npm ci.
- Salin .env.example menjadi .env; isi konfigurasi MySQL lokal.
- Jalankan php artisan key:generate lalu php artisan migrate.
- Jalankan php artisan app:create-superadmin (kata sandi dimasukkan interaktif).
- Jalankan npm run build lalu php artisan serve.

Tidak ada akun/kata sandi bawaan atau pendaftaran publik. Simpan APP_KEY dengan aman: diperlukan untuk mendekripsi API key guru. Jangan commit .env, foto siswa, hasil ekspor, atau dump database.

## Pengembangan
Jalankan npm run dev dan php artisan serve pada terminal terpisah.
Jalankan php artisan test untuk pengujian.
Implementasi dipublikasikan bertahap; lihat riwayat commit.
