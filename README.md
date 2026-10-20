# TestChecker

Aplikasi koreksi tes untuk **satu sekolah**, menggunakan Laravel 12, MySQL/MariaDB, Blade, Tailwind CSS, Alpine.js, dan Gemini API.

Antarmuka menggunakan bahasa Indonesia. Variabel, tabel, kolom, route, status internal, dan penamaan teknis menggunakan bahasa Inggris.

## Menjalankan proyek

Persyaratan: PHP 8.2+ dengan OpenSSL, PDO MySQL/SQLite, fileinfo, mbstring, DOM/XML, GD, dan ZIP; Composer; Node.js 22+; MySQL 8+ atau MariaDB 10.4+.

1. Jalankan composer install dan npm ci.
2. Salin .env.example menjadi .env. Isi DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, dan DB_PASSWORD menggunakan kredensial lokal Anda.
3. Buat database kosong dengan nama sesuai DB_DATABASE.
4. Jalankan php artisan key:generate, kemudian php artisan migrate.
5. Jalankan php artisan app:create-superadmin. Nama, email, dan kata sandi dimasukkan interaktif; tidak ada akun bawaan.
6. Jalankan npm run build.
7. Jalankan npm run serve, lalu buka http://127.0.0.1:8000.

Untuk pengembangan frontend, jalankan npm run dev pada terminal terpisah. Server PHP pengembangan dibatasi ke localhost; bukan server produksi.

### Konfigurasi lokal pada mesin pengembangan awal

Database aplikasi telah disiapkan pada instance MariaDB terpisah di localhost:3307, dengan data di .local/mysql dan pengguna aplikasi khusus. Kredensial hanya berada di .env. Database XAMPP bersama pada port 3306 tidak digunakan karena mengalami kerusakan tabel hak akses.

Jika instance terpisah belum berjalan, buka terminal PowerShell di proyek dan jalankan:

    powershell -ExecutionPolicy Bypass -File tools/start-local-mysql.ps1

Pertahankan terminal database tersebut. Untuk menghentikan instance ini secara normal:

    php tools/stop-local-mysql.php

Script startup memerlukan executable MariaDB pada lokasi yang disebut dalam script, atau parameter -MysqlExecutable. Konfigurasi .local bukan bagian repo. Instalasi baru dapat memakai server MySQL biasa tanpa script ini.

Jangan mengganti atau menghapus APP_KEY setelah API key guru disimpan. Simpan backup APP_KEY dan database dengan aman di luar repo publik.

## Pemakaian

- Superadmin membuat akun guru, tahun ajaran, kelas, siswa, dan penugasan guru.
- CSV siswa memiliki header student_code,name, maksimal 500 siswa per impor. Impor divalidasi sebagai satu transaksi.
- Keanggotaan kelas disimpan per tahun ajaran; perpindahan kelas tidak menghapus riwayat.
- Guru memasukkan API key Gemini melalui Pengaturan AI. Key dienkripsi di database dan tidak ditampilkan kembali.
- Simpan key, muat daftar model, pilih/simpan model, lalu jalankan uji koneksi dan uji gambar. Daftar API tidak menjamin setiap model memiliki free tier atau dukungan gambar.
- Buat tes dan unggah soal melalui teks, JPG/PNG/WebP, atau PDF. Dokumen maksimal 5 MB.
- Dokumen rubrik dapat diproses setelah soal terbaca; draf soal yang ada menjadi acuan penerapan rubrik. Rubrik juga dapat dibuat AI atau disunting manual.
- Tinjau dan setujui seluruh soal, opsi, kunci, bobot, dan rubrik.
- Saat koreksi pertama dibuka, daftar peserta dan versi soal/rubrik dikunci. Buat versi tes baru untuk mengubah soal.
- Pilih siswa, ambil/unggah foto, potong identitas, atur orientasi/urutan, lalu baca halaman.
- PDF jawaban dirender menjadi gambar di browser. Maksimal 10 halaman per revisi, 5 MB per berkas/halaman.
- Konfirmasikan setiap transkripsi dan pilihan jawaban, termasuk jawaban kosong. Gunakan foto pengganti untuk tulisan yang kurang jelas.
- Simpan tinjauan, nilai jawaban, periksa skor/pembahasan, lalu sahkan nilai.
- Diagram, kode, dan langkah hitungan dinilai dari interpretasi/transkripsi yang telah dikonfirmasi guru. Program siswa tidak dijalankan.
- Rekap hanya memakai revisi terakhir yang disahkan. Revisi baru tidak menggantikan nilai lama sebelum disahkan.
- Guru pemilik dapat menghapus tes dan seluruh data penilaiannya dengan mengetik HAPUS. Data siswa/sekolah tidak ikut dihapus.

Superadmin hanya mengelola akun/data induk melalui aplikasi; tidak dapat membaca soal, jawaban, atau nilai guru. Pengelola server/database tetap memiliki akses infrastruktur.

## Aturan nilai

- Esai/diagram/kode/hitungan memakai rubrik dengan jumlah skor kriteria sama dengan bobot soal.
- Benar-salah dan pilihan ganda dinilai dengan kunci.
- Pilihan ganda kompleks menyediakan semua-tepat-atau-nol dan parsial.
- Rumus parsial: bobot × max(0, benar_dipilih/jumlah_kunci − salah_dipilih/jumlah_pengecoh). Pengurangan nol jika tidak ada pengecoh.
- Nilai akhir = total skor / total bobot × 100, dua desimal.
- Jawaban kosong yang dikonfirmasi mendapat nol; jawaban belum terkonfirmasi menghalangi penilaian/pengesahan.
- Penyesuaian skor wajib disertai alasan. Jejak penyesuaian tetap tersedia meskipun tinjauan jawaban dibuka ulang.

## Privasi foto dan AI

Foto plaintext tidak disimpan ke filesystem, database, cache persisten browser, antrean, atau log aplikasi. Foto disimpan di memori tab dan dilepas saat pengesahan, pelepasan melalui tombol, atau penutupan tab. Refresh membutuhkan unggah ulang, dengan urutan halaman semula untuk mencocokkan sumber.

Payload gambar dienkripsi AES-256-GCM di browser dengan kunci sekali pakai yang dikirim melalui header terpisah. PHP/web server dapat melakukan buffering request besar ke berkas sementara; buffer ini berisi ciphertext, bukan foto plaintext. Dekripsi dilakukan dalam memori. Request keluar ke Gemini menggunakan stream php://memory untuk menghindari spill plaintext pada stream bawaan Guzzle.

Gunakan HTTPS di luar localhost karena Web Crypto memerlukan secure context. Jangan aktifkan pencatatan body/header X-Photo-Key/X-Photo-IV, debug dump, session replay, atau alat pelacakan request sensitif. Nonaktifkan request-body logging di reverse proxy/APM. APP_DEBUG dan LOG_CHANNEL dikonfigurasi untuk tidak menampilkan/merekam data sensitif.

Transkripsi yang dikonfirmasi, pembahasan, rincian skor, identitas model, dan riwayat revisi disimpan sampai dihapus guru. Identitas siswa tidak disertakan dalam prompt; guru perlu memotong identitas yang tercetak pada foto.

Penghapusan dari aplikasi tidak menjamin penghapusan oleh Gemini. Free tier memiliki kuota dan ketentuan penggunaan data penyedia. Tidak ada perpindahan otomatis ke layanan berbayar.

- Ketentuan Gemini: https://ai.google.dev/gemini-api/terms
- Harga/kuota model: https://ai.google.dev/gemini-api/docs/pricing

## Pengujian

    php artisan test
    npm run build
    npm run test:browser

Tes browser memakai database SQLite khusus, akun sintetis dengan password acak, dan server Gemini tiruan. Tidak memanggil AI eksternal dan tidak mengubah akun aplikasi nyata. Pada Windows, Chrome yang terpasang digunakan otomatis. Pada Linux/CI, jalankan npx playwright install --with-deps chromium terlebih dahulu.

Pengujian MySQL memakai konfigurasi terpisah:

    php vendor/bin/phpunit --configuration phpunit.mysql.xml

Siapkan database **testchecker_testing** dan izin pengguna yang sesuai terlebih dahulu. Perintah ini menghapus/membuat ulang tabel dalam database pengujian tersebut; jangan gunakan untuk data nyata. DB_HOST/PORT/USERNAME/PASSWORD mengikuti environment lokal. SQLite :memory: tetap menjadi default tes biasa.

Validasi OCR nyata dan kesesuaian skor dengan koreksi guru memerlukan API key guru serta sampel jawaban anonim. Hasil AI tetap memerlukan tinjauan guru.

## Publikasi kode

Commit dilakukan per tahap dan di-push ke repo publik. Sebelum commit/push:

    git add <berkas-yang-ditinjau>
    php tools/check-secrets.php
    git diff --cached --check
    git diff --cached

Pemindai memeriksa byte staged, pola kredensial, dan berkas data yang tidak boleh dipublikasikan. .env-example dan .env.example harus sama dan hanya berisi placeholder. .env, .local, database, foto, hasil ekspor, log, dan artefak pengujian diabaikan Git.

Hosting aplikasi belum termasuk. Deployment PHP memerlukan document root public/, HTTPS, APP_ENV=production, APP_DEBUG=false, SESSION_SECURE_COOKIE=true, environment rahasia, MySQL, dan kebijakan reverse proxy yang menjaga privasi request.
