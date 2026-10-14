# Konfigurasi Instance ERP LPK Jepang

Template ini mempertahankan schema, role, permission, workflow, dan aturan bisnis WMS. Perubahan instance dilakukan melalui `.env` dan halaman Pengaturan Sistem, bukan dengan mengubah schema atau menyalin data produksi.

## Database lokal

- Gunakan MySQL lokal yang khusus untuk instance ini dan tidak menunjuk ke host, kredensial, atau data produksi.
- Pertahankan `DB_CONNECTION=mysql`, `WMS_RUNTIME_DB_CONNECTION=mysql`, dan nama database runtime yang kompatibel dengan penjagaan WMS (`wms_mysql`).
- Jangan menjalankan `migrate:fresh`, `db:wipe`, reset, atau seeder terhadap database yang sudah berisi data.
- Migrasi dan import data harus dijalankan hanya melalui prosedur operasional WMS yang telah disetujui, setelah backup dan verifikasi target lokal.

## Branding

Salin variabel `BRAND_*` dari `.env.example` ke `.env` bila instance memerlukan identitas yang berbeda. Logo, favicon, background login, palet warna, nama aplikasi, serta placeholder organisasi dipusatkan di `config/branding.php`. Nilai yang disimpan melalui Pengaturan Sistem tetap menjadi sumber utama profil perusahaan pada runtime.

## Master administrator opsional

`MasterAdminSeeder` menggunakan tabel `MASTER_USER`, repository, hashing, dan role `ADMINISTRATOR` bawaan. Seeder bersifat idempoten: bila email `admin@localhost.test` atau username `masteradmin` sudah ada, akun serta password lama tidak diubah. Seeder juga tidak membuat role baru.

1. Set `MASTER_ADMIN_PASSWORD` hanya di `.env` lokal dengan password kuat.
2. Pastikan role `ADMINISTRATOR` aktif sudah tersedia pada schema WMS.
3. Jalankan hanya bila akun bootstrap benar-benar diperlukan:

   `php artisan db:seed --class=MasterAdminSeeder`

Jangan commit `.env`, password, token, database lokal, credential OAuth/SMTP, atau hasil export data.
