# Streaming App

Aplikasi Laravel untuk mengelola koleksi video, menyusun playlist, dan menjalankan livestream YouTube secara looping menggunakan `ffmpeg` dan `pm2`.

## Ringkasan

Project ini dirancang untuk skenario "playlist streaming" per user:

- user login ke dashboard
- user upload video MP4
- user mengatur urutan video
- user membuat playlist dari video yang tersedia
- user memulai stream manual atau langsung dari playlist
- server menjalankan proses streaming ke YouTube Live melalui `ffmpeg`
- proses streaming dijaga oleh `pm2` dan dipantau dari dashboard

## Fitur Utama

- autentikasi user bawaan Laravel UI
- upload, edit judul, dan hapus video
- preview video dan thumbnail
- pengurutan video dengan drag-and-drop
- manajemen playlist
- pengurutan isi playlist dengan drag-and-drop
- simpan YouTube stream key per user
- mulai streaming dari pilihan video manual
- mulai streaming langsung dari playlist
- hentikan streaming aktif
- log streaming, log error, dan status "now playing"
- endpoint publik untuk stream video, thumbnail, dan status `now-playing`

## Teknologi

- PHP 7.3+ / 8.x
- Laravel 8
- Bootstrap 5
- Font Awesome
- Laravel UI
- MySQL atau database lain yang didukung Laravel
- `ffmpeg`
- `pm2`

## Struktur Domain

### Model

- `User`
  Memiliki relasi ke `videos`, `playlists`, dan `streamSettings`.

- `Video`
  Menyimpan metadata video user: `title`, `path`, `order`.

- `Playlist`
  Menyimpan kumpulan video milik user melalui tabel pivot `playlist_video`.

- `StreamSetting`
  Menyimpan `youtube_key`, status stream, playlist terakhir, dan video terakhir.

### Controller

- `VideoController`
  Menangani upload, edit, hapus, stream video, dan thumbnail.

- `PlaylistController`
  Menangani CRUD playlist, tambah/hapus video dari playlist, dan reorder isi playlist.

- `StreamController`
  Menangani dashboard streaming, penyimpanan stream key, start/stop stream, update order video, dan endpoint `nowPlaying`.

## Alur Streaming

1. User memilih satu atau lebih video, atau memilih playlist.
2. Aplikasi memvalidasi stream key dan keberadaan `ffmpeg` serta `pm2`.
3. Aplikasi membuat script shell per user di folder `scripts/`.
4. Script memutar video satu per satu secara looping tak terbatas.
5. Setiap video dikirim ke YouTube RTMPS dengan `ffmpeg`.
6. Script dijalankan dengan nama proses `pm2` berbentuk `stream_{user_id}`.
7. Dashboard membaca log untuk menampilkan status stream, error, dan video yang sedang diputar.

## Struktur Database

Migration penting yang membentuk aplikasi ini:

- `videos`
  Menyimpan video user.

- `stream_settings`
  Menyimpan stream key dan status stream.

- `playlists`
  Menyimpan playlist milik user.

- `playlist_video`
  Menyimpan relasi playlist dan video beserta urutannya.

## Routing Penting

Route utama ada di `routes/web.php`.

- `videos.*`
  Manajemen video

- `playlists.*`
  Manajemen playlist

- `stream.index`
  Dashboard streaming

- `stream.start`
  Mulai streaming dari pilihan manual

- `stream.startPlaylist`
  Mulai streaming dari playlist

- `stream.stop`
  Hentikan streaming

- `stream.nowPlaying`
  API status video yang sedang dimainkan

## Instalasi Lokal

1. Clone project ini.
2. Install dependency PHP:

```bash
composer install
```

3. Install dependency frontend:

```bash
npm install
```

4. Copy environment file:

```bash
cp .env.example .env
```

5. Atur koneksi database di `.env`.
6. Generate application key:

```bash
php artisan key:generate
```

7. Jalankan migration:

```bash
php artisan migrate
```

8. Buat symbolic link storage:

```bash
php artisan storage:link
```

9. Build asset frontend:

```bash
npm run dev
```

10. Jalankan aplikasi:

```bash
php artisan serve
```

## Kebutuhan Server Streaming

Fitur streaming aktif membutuhkan environment Linux yang menyediakan:

- `ffmpeg`
- `pm2`
- utilitas shell seperti `tail`, `grep`, `free`, `df`, dan `/proc/loadavg`
- permission yang cukup untuk menulis log dan membuat script di folder `scripts/`
- `PM2_HOME` yang bisa ditulis oleh user web server

Contoh dependency server:

```bash
sudo apt update
sudo apt install ffmpeg
sudo npm install pm2 -g
```

## Catatan Implementasi

- file video disimpan di disk `public`
- thumbnail dihasilkan dengan `ffmpeg` dan di-cache di `storage/app/public/thumbnails`
- stream video dan thumbnail saat ini bisa diakses tanpa autentikasi
- status stream tidak hanya bergantung pada database, tetapi juga pada proses `pm2` dan isi log file
- dashboard streaming saat ini diasumsikan berjalan di server Linux, bukan Windows

## Koneksi YouTube

Untuk fitur persiapan `Go Live`, aplikasi memakai cookie login Google/YouTube yang diunggah per user.

Langkah praktis:

1. Login ke akun Google yang memiliki akses ke channel YouTube.
2. Buka `youtube.com` atau `studio.youtube.com` di browser Anda.
3. Export cookie memakai extension seperti Cookie-Editor atau EditThisCookie.
4. Simpan hasil export sebagai file `.json`.
5. Di halaman `Stream`, isi email Google dan `channel ID`.
6. Upload file cookie tersebut di panel `Koneksi YouTube`.
7. Klik `Coba Buka Go Live Sekarang`.

Format cookie yang didukung:

- array JSON berisi cookie
- objek JSON dengan properti `cookies`
- objek JSON dengan properti `data.cookies`

Catatan:

- cookie harus masih valid
- jika Google meminta login ulang, cookie perlu diexport ulang
- hasil screenshot terakhir automasi disimpan di folder session YouTube pada server untuk debugging

## Auto-Restart Jam 06:00

Keputusan operasional terbaru: auto-restart dibuat sederhana dan tidak bergantung pada cookie, login Chrome VPS, OAuth, atau hasil cek live YouTube.

Mekanisme saat ini:

1. Laravel scheduler menjalankan `php artisan stream:auto-restart` setiap hari jam `06:00` WIB.
2. Command hanya memproses user yang punya `youtube_key` dan `auto_restart_enabled = true`.
3. Command mengambil playlist terakhir atau pilihan video terakhir dari tiap user yang aktif auto-restart.
4. Command membuat ulang script streaming `scripts/stream_{user_id}.sh`.
5. Command menjalankan stop/play PM2 sebanyak `5` siklus.
6. Setiap siklus akan menghapus proses `pm2` lama jika ada, lalu menjalankan ulang proses `stream_{user_id}`.
7. Status database `is_active` diset aktif jika siklus terakhir berhasil menghasilkan PID PM2.

Pengaturan per akun:

- di halaman `Manajemen Streaming` terdapat kartu `Auto-Restart Jam 06:00`
- tombol `Aktifkan` membuat akun ikut auto-restart jam 06:00
- tombol `Matikan` membuat akun dilewati scheduler
- nilai disimpan di kolom `stream_settings.auto_restart_enabled`

Command uji manual:

```bash
php artisan stream:auto-restart --dry-run
php artisan stream:auto-restart --user=1
```

Catatan:

- command ini tidak mengecek apakah YouTube sedang live atau mati
- command ini tidak membuka YouTube Studio
- command ini tidak membutuhkan cookie login
- command ini tidak membutuhkan OAuth Google
- jika stream key salah atau YouTube belum siap menerima stream, PM2/FFmpeg tetap akan dicoba jalan sesuai konfigurasi aplikasi

## Catatan Start Manual

Keputusan operasional terbaru juga berlaku untuk tombol start/play manual di dashboard:

- tombol start langsung membuat script streaming dan menjalankan PM2/FFmpeg
- tombol start tidak membuka YouTube Go Live
- tombol start tidak mengecek status live YouTube
- tombol start tidak membutuhkan cookie, OAuth, atau session Chrome VPS
- panel konfigurasi YouTube/cookie/OAuth tetap disimpan sebagai catatan/fasilitas lanjutan, tetapi tidak menjadi syarat untuk menjalankan streaming saat ini

## Catatan Lanjutan OAuth YouTube

OAuth resmi YouTube API sempat disiapkan sebagai jalur cek live tanpa cookie, tetapi belum menjadi strategi operasional utama karena Google OAuth membutuhkan redirect URI dengan domain publik valid dan tidak menerima IP/port custom seperti `:82`.

Jika OAuth dilanjutkan nanti, siapkan domain/subdomain tanpa port custom, misalnya:

```text
https://streaming.example.com/youtube/oauth/callback
```

Lalu isi env server:

```env
GOOGLE_YOUTUBE_CLIENT_ID=
GOOGLE_YOUTUBE_CLIENT_SECRET=
GOOGLE_YOUTUBE_REDIRECT_URI=
```

## Lokasi File Penting

- `app/Http/Controllers/StreamController.php`
- `app/Http/Controllers/VideoController.php`
- `app/Http/Controllers/PlaylistController.php`
- `app/Models/Video.php`
- `app/Models/Playlist.php`
- `app/Models/StreamSetting.php`
- `resources/views/stream/index.blade.php`
- `resources/views/videos/index.blade.php`
- `resources/views/playlists/index.blade.php`
- `resources/views/playlists/show.blade.php`
- `routes/web.php`

## Catatan Perubahan

### 2026-06-14

- README bawaan Laravel diganti dengan dokumentasi khusus aplikasi streaming ini
- ditambahkan ringkasan arsitektur, fitur, alur streaming, kebutuhan server, dan catatan implementasi
- ditambahkan rancangan koneksi YouTube per user: email Google, upload cookie JSON, dan persiapan `Go Live` sebelum stream dimulai

## Saran Pengembangan Lanjutan

- tambahkan dokumentasi deployment ke VPS
- pindahkan hardcoded path Linux ke file konfigurasi
- tambahkan test untuk controller utama
- amankan endpoint publik video/thumbnail jika diperlukan
- pisahkan logic shell/PM2/FFmpeg ke service class agar controller lebih tipis
