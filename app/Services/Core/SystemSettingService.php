<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\SystemParameterRepositoryInterface;
use App\Interfaces\GoogleSheets\SystemSettingRepositoryInterface;
use App\Support\CoordinateNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class SystemSettingService
{
    protected $settingRepo;

    protected $paramRepo;

    public function __construct(SystemSettingRepositoryInterface $settingRepo, SystemParameterRepositoryInterface $paramRepo)
    {
        $this->settingRepo = $settingRepo;
        $this->paramRepo = $paramRepo;
    }

    public function getDefaultSettings(): array
    {
        return [
            // BRANDING
            [
                'Setting_ID' => 'SET_BRAND_PRIMARY',
                'Category' => 'Branding',
                'Setting_Key' => 'BRAND_PRIMARY_COLOR',
                'Setting_Name' => 'Warna Utama (Primary Color)',
                'Description' => 'Warna utama untuk tombol primary, aksen, dan elemen aktif di WMS.',
                'Value_Type' => 'color',
                'Setting_Value' => '#38BDF8',
            ],
            [
                'Setting_ID' => 'SET_BRAND_SECONDARY',
                'Category' => 'Branding',
                'Setting_Key' => 'BRAND_SECONDARY_COLOR',
                'Setting_Name' => 'Warna Sekunder (Secondary Color)',
                'Description' => 'Warna sekunder untuk latar belakang elemen kontras.',
                'Value_Type' => 'color',
                'Setting_Value' => '#0F172A',
            ],
            [
                'Setting_ID' => 'SET_BRAND_SIDEBAR_BG',
                'Category' => 'Branding',
                'Setting_Key' => 'BRAND_SIDEBAR_BG',
                'Setting_Name' => 'Background Sidebar',
                'Description' => 'Warna latar belakang navigasi sidebar.',
                'Value_Type' => 'color',
                'Setting_Value' => '#111827',
            ],
            [
                'Setting_ID' => 'SET_BRAND_SIDEBAR_TEXT',
                'Category' => 'Branding',
                'Setting_Key' => 'BRAND_SIDEBAR_TEXT',
                'Setting_Name' => 'Teks Sidebar',
                'Description' => 'Warna teks default menu sidebar.',
                'Value_Type' => 'color',
                'Setting_Value' => '#94A3B8',
            ],
            [
                'Setting_ID' => 'SET_BRAND_SIDEBAR_ACTIVE_BG',
                'Category' => 'Branding',
                'Setting_Key' => 'BRAND_SIDEBAR_ACTIVE_BG',
                'Setting_Name' => 'Background Menu Aktif Sidebar',
                'Description' => 'Warna latar belakang menu yang sedang aktif di sidebar.',
                'Value_Type' => 'color',
                'Setting_Value' => '#1E293B',
            ],
            [
                'Setting_ID' => 'SET_BRAND_SIDEBAR_ACTIVE_TEXT',
                'Category' => 'Branding',
                'Setting_Key' => 'BRAND_SIDEBAR_ACTIVE_TEXT',
                'Setting_Name' => 'Teks Menu Aktif Sidebar',
                'Description' => 'Warna teks menu yang sedang aktif di sidebar.',
                'Value_Type' => 'color',
                'Setting_Value' => '#38BDF8',
            ],
            [
                'Setting_ID' => 'SET_BRAND_TOPBAR_BG',
                'Category' => 'Branding',
                'Setting_Key' => 'BRAND_TOPBAR_BG',
                'Setting_Name' => 'Background Topbar',
                'Description' => 'Warna latar belakang header topbar.',
                'Value_Type' => 'color',
                'Setting_Value' => '#111827',
            ],
            [
                'Setting_ID' => 'SET_BRAND_CARD_BG',
                'Category' => 'Branding',
                'Setting_Key' => 'BRAND_CARD_BG',
                'Setting_Name' => 'Background Kartu (Card)',
                'Description' => 'Warna latar belakang kontainer kartu data.',
                'Value_Type' => 'color',
                'Setting_Value' => '#FFFFFF',
            ],
            [
                'Setting_ID' => 'SET_BRAND_PAGE_BG',
                'Category' => 'Branding',
                'Setting_Key' => 'BRAND_PAGE_BG',
                'Setting_Name' => 'Background Halaman (Page)',
                'Description' => 'Warna latar belakang utama seluruh halaman WMS.',
                'Value_Type' => 'color',
                'Setting_Value' => '#E2E8F0',
            ],

            // GENERAL
            [
                'Setting_ID' => 'SET_GENERAL_APP_NAME',
                'Category' => 'General',
                'Setting_Key' => 'COMPANY_NAME',
                'Setting_Name' => 'Nama Aplikasi / Sistem',
                'Description' => 'Nama resmi sistem yang ditampilkan pada header dan footer.',
                'Value_Type' => 'text',
                'Setting_Value' => 'WAKAMIYA MANAGEMENT SYSTEM',
            ],
            [
                'Setting_ID' => 'SET_GENERAL_TIMEZONE',
                'Category' => 'General',
                'Setting_Key' => 'APP_TIMEZONE',
                'Setting_Name' => 'Zona Waktu Sistem',
                'Description' => 'Zona waktu acuan transaksi dan absensi (Default: Asia/Jakarta).',
                'Value_Type' => 'text',
                'Setting_Value' => 'Asia/Jakarta',
            ],
            [
                'Setting_ID' => 'SET_GENERAL_LOCALE',
                'Category' => 'General',
                'Setting_Key' => 'APP_LOCALE',
                'Setting_Name' => 'Bahasa Sistem',
                'Description' => 'Bahasa bawaan antarmuka pengguna WMS (Default: id).',
                'Value_Type' => 'text',
                'Setting_Value' => 'id',
            ],
            [
                'Setting_ID' => 'SET_GENERAL_DATE_FORMAT',
                'Category' => 'General',
                'Setting_Key' => 'APP_DATE_FORMAT',
                'Setting_Name' => 'Format Tanggal',
                'Description' => 'Format tampilan tanggal standar pada laporan dan dokumen (d/m/Y).',
                'Value_Type' => 'text',
                'Setting_Value' => 'd/m/Y',
            ],

            [
                'Setting_ID' => 'SET_HR_LPK_GEOFENCE_ENABLED',
                'Category' => 'Attendance',
                'Setting_Key' => 'LPK_GEOFENCE_ENABLED',
                'Setting_Name' => 'Aktifkan Geofence (Validasi Lokasi)',
                'Description' => 'Jika diaktifkan, sistem akan menolak presensi yang dilakukan di luar radius LPK.',
                'Value_Type' => 'boolean',
                'Setting_Value' => 'true',
            ],

            // COMPANY PROFILE
            [
                'Setting_ID' => 'SET_COMPANY_NAME',
                'Category' => 'Company',
                'Setting_Key' => 'COMPANY_NAME',
                'Setting_Name' => 'Nama Perusahaan / LPK',
                'Description' => 'Nama resmi perusahaan/lembaga yang ditampilkan pada logo, kop surat, dan dokumen.',
                'Value_Type' => 'text',
                'Setting_Value' => 'WAKAMIYA MANAGEMENT SYSTEM',
            ],
            [
                'Setting_ID' => 'SET_COMPANY_TAGLINE',
                'Category' => 'Company',
                'Setting_Key' => 'COMPANY_TAGLINE',
                'Setting_Name' => 'Slogan / Tagline Perusahaan',
                'Description' => 'Slogan resmi perusahaan yang ditampilkan di bawah logo pada sidebar.',
                'Value_Type' => 'text',
                'Setting_Value' => 'Enterprise Human Resource Engine',
            ],
            [
                'Setting_ID' => 'SET_COMPANY_LOGO',
                'Category' => 'Company',
                'Setting_Key' => 'COMPANY_LOGO',
                'Setting_Name' => 'Logo Utama Perusahaan / LPK',
                'Description' => 'File gambar logo resmi perusahaan (PNG/JPG/WEBP, Max 5MB).',
                'Value_Type' => 'file',
                'Setting_Value' => 'img/logo.png.jpeg',
            ],
            [
                'Setting_ID' => 'SET_COMPANY_ADDRESS',
                'Category' => 'Company',
                'Setting_Key' => 'COMPANY_ADDRESS',
                'Setting_Name' => 'Alamat Lengkap Perusahaan',
                'Description' => 'Alamat kantor resmi yang dicantumkan pada kop surat dan kwitansi.',
                'Value_Type' => 'text',
                'Setting_Value' => 'Jl. Raya Wakamiya No. 88, Jakarta Selatan 12930',
            ],
            [
                'Setting_ID' => 'SET_COMPANY_PHONE',
                'Category' => 'Company',
                'Setting_Key' => 'COMPANY_PHONE',
                'Setting_Name' => 'Nomor Telepon Kantor',
                'Description' => 'Nomor telepon resmi perusahaan.',
                'Value_Type' => 'text',
                'Setting_Value' => '(021) 8000-9999',
            ],
            [
                'Setting_ID' => 'SET_COMPANY_EMAIL',
                'Category' => 'Company',
                'Setting_Key' => 'COMPANY_EMAIL',
                'Setting_Name' => 'Email Perusahaan',
                'Description' => 'Alamat kontak email utama perusahaan.',
                'Value_Type' => 'text',
                'Setting_Value' => 'hr@wakamiya.ac.id',
            ],
            [
                'Setting_ID' => 'SET_COMPANY_WEB',
                'Category' => 'Company',
                'Setting_Key' => 'COMPANY_WEB',
                'Setting_Name' => 'Website Resmi',
                'Description' => 'Alamat situs resmi perusahaan.',
                'Value_Type' => 'text',
                'Setting_Value' => 'https://wakamiya.ac.id',
            ],
            [
                'Setting_ID' => 'SET_COMPANY_NPWP',
                'Category' => 'Company',
                'Setting_Key' => 'COMPANY_NPWP',
                'Setting_Name' => 'Nomor NPWP Perusahaan',
                'Description' => 'Nomor Pokok Wajib Pajak (NPWP) resmi perusahaan.',
                'Value_Type' => 'text',
                'Setting_Value' => '01.234.567.8-901.000',
            ],

            // COMPANY DOCUMENT & SIGNATURE
            [
                'Setting_ID' => 'SET_COMPANY_SIGNER_NAME',
                'Category' => 'Company_Document',
                'Setting_Key' => 'COMPANY_SIGNER_NAME',
                'Setting_Name' => 'Nama Penandatangan Resmi',
                'Description' => 'Nama pejabat berwenang yang tercantum pada Sertifikat & Invoice.',
                'Value_Type' => 'text',
                'Setting_Value' => 'Dr. Reza Pekanbaru',
            ],
            [
                'Setting_ID' => 'SET_COMPANY_SIGNER_TITLE',
                'Category' => 'Company_Document',
                'Setting_Key' => 'COMPANY_SIGNER_TITLE',
                'Setting_Name' => 'Jabatan Penandatangan',
                'Description' => 'Jabatan resmi pejabat penandatangan dokumen.',
                'Value_Type' => 'text',
                'Setting_Value' => 'General Director & Founder',
            ],
            [
                'Setting_ID' => 'SET_COMPANY_SIGNATURE_URL',
                'Category' => 'Company_Document',
                'Setting_Key' => 'COMPANY_SIGNATURE_URL',
                'Setting_Name' => 'File Tanda Tangan Digital',
                'Description' => 'Gambar spesimen tanda tangan transparan (PNG/WEBP, Max 5MB).',
                'Value_Type' => 'file',
                'Setting_Value' => '',
            ],
            [
                'Setting_ID' => 'SET_COMPANY_STAMP_URL',
                'Category' => 'Company_Document',
                'Setting_Key' => 'COMPANY_STAMP_URL',
                'Setting_Name' => 'File Stempel Perusahaan',
                'Description' => 'Gambar stempel resmi perusahaan transparan (PNG/WEBP, Max 5MB).',
                'Value_Type' => 'file',
                'Setting_Value' => '',
            ],
            [
                'Setting_ID' => 'SET_DOCUMENT_PREFIX',
                'Category' => 'Company_Document',
                'Setting_Key' => 'DOCUMENT_PREFIX',
                'Setting_Name' => 'Prefix Nomor Dokumen',
                'Description' => 'Awalan penomoran dokumen resmi WMS.',
                'Value_Type' => 'text',
                'Setting_Value' => 'DOC/WMS/',
            ],

            // HR ATTENDANCE & GEOFENCE H8.22
            [
                'Setting_ID' => 'SET_HR_LPK_LATITUDE',
                'Category' => 'Attendance',
                'Setting_Key' => 'LPK_LATITUDE',
                'Setting_Name' => 'Latitude Titik Geofence LPK',
                'Description' => 'Koordinat latitude titik pusat geofence absensi (Default: -6.81234).',
                'Value_Type' => 'text',
                'Setting_Value' => '-6.81234',
            ],
            [
                'Setting_ID' => 'SET_HR_LPK_LONGITUDE',
                'Category' => 'Attendance',
                'Setting_Key' => 'LPK_LONGITUDE',
                'Setting_Name' => 'Longitude Titik Geofence LPK',
                'Description' => 'Koordinat longitude titik pusat geofence absensi (Default: 107.19451).',
                'Value_Type' => 'text',
                'Setting_Value' => '107.19451',
            ],
            [
                'Setting_ID' => 'SET_HR_LPK_RADIUS',
                'Category' => 'Attendance',
                'Setting_Key' => 'LPK_ALLOWED_RADIUS_METERS',
                'Setting_Name' => 'Radius Maksimal Geofence (Meter)',
                'Description' => 'Jarak toleransi maksimal absensi dari titik lokasi LPK (Default: 20m).',
                'Value_Type' => 'number',
                'Setting_Value' => '20',
            ],
            [
                'Setting_ID' => 'SET_HR_QR_TTL',
                'Category' => 'Attendance',
                'Setting_Key' => 'QR_TOKEN_TTL_SECONDS',
                'Setting_Name' => 'QR Code Token TTL (Detik)',
                'Description' => 'Masa berlaku token QR dinamis sebelum diperbarui otomatis (Default: 25s).',
                'Value_Type' => 'number',
                'Setting_Value' => '25',
            ],
            [
                'Setting_ID' => 'SET_HR_WORK_START_TIME',
                'Category' => 'Attendance',
                'Setting_Key' => 'WORK_START_TIME',
                'Setting_Name' => 'Jam Masuk Presensi Standar',
                'Description' => 'Waktu batas presensi masuk pegawai (format HH:mm).',
                'Value_Type' => 'text',
                'Setting_Value' => '08:00',
            ],
            [
                'Setting_ID' => 'SET_HR_WORK_END_TIME',
                'Category' => 'Attendance',
                'Setting_Key' => 'WORK_END_TIME',
                'Setting_Name' => 'Jam Pulang Presensi Standar',
                'Description' => 'Waktu jam pulang presensi pegawai (format HH:mm).',
                'Value_Type' => 'text',
                'Setting_Value' => '17:00',
            ],
            [
                'Setting_ID' => 'SET_STUDENT_ATTENDANCE_START_TIME',
                'Category' => 'Attendance',
                'Setting_Key' => 'STUDENT_ATTENDANCE_START_TIME',
                'Setting_Name' => 'Jam Buka Absensi Siswa',
                'Description' => 'Waktu mulai presensi harian siswa dalam WIB.',
                'Value_Type' => 'text',
                'Setting_Value' => '06:00',
            ],
            [
                'Setting_ID' => 'SET_STUDENT_ATTENDANCE_END_TIME',
                'Category' => 'Attendance',
                'Setting_Key' => 'STUDENT_ATTENDANCE_END_TIME',
                'Setting_Name' => 'Jam Tutup Absensi Siswa',
                'Description' => 'Waktu selesai presensi harian siswa dalam WIB.',
                'Value_Type' => 'text',
                'Setting_Value' => '08:30',
            ],
            [
                'Setting_ID' => 'SET_HR_LATE_TOLERANCE',
                'Category' => 'Attendance',
                'Setting_Key' => 'LATE_TOLERANCE_MINUTES',
                'Setting_Name' => 'Toleransi Keterlambatan (Menit)',
                'Description' => 'Batas toleransi setelah jam masuk sebelum presensi ditandai terlambat.',
                'Value_Type' => 'number',
                'Setting_Value' => '30',
            ],
            [
                'Setting_ID' => 'SET_HR_GEOLOCATION_STRICT',
                'Category' => 'Attendance',
                'Setting_Key' => 'GEOLOCATION_STRICT',
                'Setting_Name' => 'Verifikasi Lokasi Geofence Ketat',
                'Description' => 'Meminta persetujuan izin lokasi GPS saat scan QR absensi.',
                'Value_Type' => 'boolean',
                'Setting_Value' => 'true',
            ],

            // HR PAYROLL & GAJI
            [
                'Setting_ID' => 'SET_HR_DEFAULT_BASIC_SALARY',
                'Category' => 'Payroll',
                'Setting_Key' => 'DEFAULT_BASIC_SALARY',
                'Setting_Name' => 'Gaji Pokok Standar Staff (Rp)',
                'Description' => 'Nominal acuan standar gaji pokok untuk staff/pegawai umum.',
                'Value_Type' => 'number',
                'Setting_Value' => '3500000',
            ],
            [
                'Setting_ID' => 'SET_HR_SALARY_FINANCE',
                'Category' => 'Payroll',
                'Setting_Key' => 'SALARY_FINANCE',
                'Setting_Name' => 'Gaji Pokok Staff Finance (Rp)',
                'Description' => 'Nominal acuan standar gaji pokok untuk divisi Finance/Keuangan.',
                'Value_Type' => 'number',
                'Setting_Value' => '3800000',
            ],
            [
                'Setting_ID' => 'SET_HR_SALARY_TEACHER',
                'Category' => 'Payroll',
                'Setting_Key' => 'SALARY_TEACHER',
                'Setting_Name' => 'Gaji Pokok Guru / Sensei (Rp)',
                'Description' => 'Nominal acuan standar gaji pokok untuk Tenaga Pendidik / Guru.',
                'Value_Type' => 'number',
                'Setting_Value' => '4000000',
            ],
            [
                'Setting_ID' => 'SET_HR_SALARY_ACADEMIC',
                'Category' => 'Payroll',
                'Setting_Key' => 'SALARY_ACADEMIC',
                'Setting_Name' => 'Gaji Pokok Tim Akademik (Rp)',
                'Description' => 'Nominal acuan standar gaji pokok untuk divisi Akademik.',
                'Value_Type' => 'number',
                'Setting_Value' => '3700000',
            ],
            [
                'Setting_ID' => 'SET_HR_SALARY_MARKETING',
                'Category' => 'Payroll',
                'Setting_Key' => 'SALARY_MARKETING',
                'Setting_Name' => 'Gaji Pokok Tim Marketing (Rp)',
                'Description' => 'Nominal acuan standar gaji pokok untuk divisi Marketing.',
                'Value_Type' => 'number',
                'Setting_Value' => '3500000',
            ],
            [
                'Setting_ID' => 'SET_HR_SALARY_HR',
                'Category' => 'Payroll',
                'Setting_Key' => 'SALARY_HR',
                'Setting_Name' => 'Gaji Pokok Tim HR (Rp)',
                'Description' => 'Nominal acuan standar gaji pokok untuk divisi HR.',
                'Value_Type' => 'number',
                'Setting_Value' => '4000000',
            ],
            [
                'Setting_ID' => 'SET_HR_OVERTIME_RATE_HOUR',
                'Category' => 'Payroll',
                'Setting_Key' => 'OVERTIME_RATE_HOUR',
                'Setting_Name' => 'Tarif Lembur per Jam (Rp)',
                'Description' => 'Nominal insentif lembur standar per jam kerja pegawai.',
                'Value_Type' => 'number',
                'Setting_Value' => '25000',
            ],
            [
                'Setting_ID' => 'SET_HR_TAX_PERCENTAGE',
                'Category' => 'Payroll',
                'Setting_Key' => 'TAX_PERCENTAGE',
                'Setting_Name' => 'Potongan Pajak PPh21 (%)',
                'Description' => 'Persentase pengurang pajak PPh21 (Isi 0 jika tidak ada potongan pajak).',
                'Value_Type' => 'number',
                'Setting_Value' => '0.0',
            ],
            [
                'Setting_ID' => 'SET_HR_BPJS_PERCENTAGE',
                'Category' => 'Payroll',
                'Setting_Key' => 'BPJS_PERCENTAGE',
                'Setting_Name' => 'Potongan BPJS Ketenagakerjaan (%)',
                'Description' => 'Persentase pengurang BPJS dalam perhitungan gaji bersih (Isi 0 jika tidak ada potongan BPJS).',
                'Value_Type' => 'number',
                'Setting_Value' => '0.0',
            ],
            [
                'Setting_ID' => 'SET_HR_ABSENCE_DEDUCTION_PER_DAY',
                'Category' => 'Payroll',
                'Setting_Key' => 'ABSENCE_DEDUCTION_PER_DAY',
                'Setting_Name' => 'Potongan Mangkir per Hari (Rp)',
                'Description' => 'Nominal potongan gaji per hari jika pegawai tidak hadir (Isi 0 jika tanpa potongan).',
                'Value_Type' => 'number',
                'Setting_Value' => '0',
            ],
            [
                'Setting_ID' => 'SET_HR_AUTO_ATTENDANCE_DEDUCTION',
                'Category' => 'Payroll',
                'Setting_Key' => 'ENABLE_AUTO_ATTENDANCE_DEDUCTION',
                'Setting_Name' => 'Potongan Otomatis Dari Data Absensi',
                'Description' => 'Jika diaktifkan, sistem akan memotong gaji secara otomatis dari data rekap absensi QR.',
                'Value_Type' => 'boolean',
                'Setting_Value' => 'false',
            ],
            [
                'Setting_ID' => 'SET_HR_PAYROLL_CUTOFF_DATE',
                'Category' => 'Payroll',
                'Setting_Key' => 'PAYROLL_CUTOFF_DATE',
                'Setting_Name' => 'Tanggal Cutoff Rekap Kehadiran',
                'Description' => 'Tanggal batas penutupan absensi bulanan untuk payroll (1 - 31).',
                'Value_Type' => 'number',
                'Setting_Value' => '25',
            ],
            [
                'Setting_ID' => 'SET_HR_PAYROLL_DISBURSEMENT_DATE',
                'Category' => 'Payroll',
                'Setting_Key' => 'PAYROLL_DISBURSEMENT_DATE',
                'Setting_Name' => 'Tanggal Pencairan Payroll',
                'Description' => 'Tanggal rutin pencairan & penerbitan slip gaji pegawai (1 - 31).',
                'Value_Type' => 'number',
                'Setting_Value' => '1',
            ],

            // FINANCE
            [
                'Setting_ID' => 'SET_FIN_DEFAULT_TUITION_FEE',
                'Category' => 'Finance',
                'Setting_Key' => 'DEFAULT_TUITION_FEE',
                'Setting_Name' => 'Biaya Pendidikan Default (Rp)',
                'Description' => 'Nominal biaya pendidikan default untuk siswa baru jika program/batch belum memiliki tarif khusus.',
                'Value_Type' => 'number',
                'Setting_Value' => '7500000',
            ],
            [
                'Setting_ID' => 'SET_FIN_INVOICE_CATEGORIES',
                'Category' => 'Finance',
                'Setting_Key' => 'INVOICE_CATEGORIES',
                'Setting_Name' => 'Kategori Tagihan',
                'Description' => 'Daftar kategori invoice dipisahkan koma.',
                'Value_Type' => 'text',
                'Setting_Value' => 'Biaya Pendidikan, Medical, JFT, JLPT, Dormitory, Air Ticket, Administration, SSW, Equipment, Other',
            ],
            [
                'Setting_ID' => 'SET_FIN_INVOICE_DUE_DAYS',
                'Category' => 'Finance',
                'Setting_Key' => 'INVOICE_DUE_DAYS',
                'Setting_Name' => 'Default Jatuh Tempo Invoice (Hari)',
                'Description' => 'Jumlah hari default dari tanggal terbit sampai jatuh tempo invoice.',
                'Value_Type' => 'number',
                'Setting_Value' => '14',
            ],
            [
                'Setting_ID' => 'SET_FIN_ALLOW_PARTIAL_PAYMENT',
                'Category' => 'Finance',
                'Setting_Key' => 'ALLOW_PARTIAL_PAYMENT',
                'Setting_Name' => 'Izinkan Pembayaran Bertahap',
                'Description' => 'Jika aktif, siswa dapat membayar invoice sebagian sampai lunas.',
                'Value_Type' => 'boolean',
                'Setting_Value' => 'true',
            ],
            [
                'Setting_ID' => 'SET_FIN_PAYMENT_VERIFICATION_REQUIRED',
                'Category' => 'Finance',
                'Setting_Key' => 'PAYMENT_VERIFICATION_REQUIRED',
                'Setting_Name' => 'Wajib Verifikasi Pembayaran',
                'Description' => 'Jika aktif, pembayaran siswa masuk status menunggu verifikasi sebelum diakui lunas.',
                'Value_Type' => 'boolean',
                'Setting_Value' => 'true',
            ],
            [
                'Setting_ID' => 'SET_FIN_LATE_FEE_PERCENTAGE',
                'Category' => 'Finance',
                'Setting_Key' => 'LATE_FEE_PERCENTAGE',
                'Setting_Name' => 'Denda Keterlambatan (%)',
                'Description' => 'Persentase denda invoice overdue. Isi 0 jika tidak menggunakan denda.',
                'Value_Type' => 'number',
                'Setting_Value' => '0',
            ],
            [
                'Setting_ID' => 'SET_FIN_RECEIPT_PREFIX',
                'Category' => 'Finance',
                'Setting_Key' => 'RECEIPT_PREFIX',
                'Setting_Name' => 'Prefix Nomor Kuitansi',
                'Description' => 'Awalan nomor kuitansi resmi pembayaran.',
                'Value_Type' => 'text',
                'Setting_Value' => 'RCPT/WMS/',
            ],
            [
                'Setting_ID' => 'SET_FIN_INVOICE_PREFIX',
                'Category' => 'Finance',
                'Setting_Key' => 'INVOICE_PREFIX',
                'Setting_Name' => 'Prefix Nomor Invoice',
                'Description' => 'Awalan nomor invoice resmi.',
                'Value_Type' => 'text',
                'Setting_Value' => 'INV/WMS/',
            ],

            // COMPANY BANK
            [
                'Setting_ID' => 'SET_BANK_NAME',
                'Category' => 'Company_Bank',
                'Setting_Key' => 'COMPANY_BANK_NAME',
                'Setting_Name' => 'Nama Bank Utama',
                'Description' => 'Nama bank yang ditampilkan pada invoice dan instruksi pembayaran.',
                'Value_Type' => 'text',
                'Setting_Value' => 'BANK BCA',
            ],
            [
                'Setting_ID' => 'SET_BANK_ACCOUNT',
                'Category' => 'Company_Bank',
                'Setting_Key' => 'COMPANY_BANK_ACCOUNT',
                'Setting_Name' => 'Nomor Rekening',
                'Description' => 'Nomor rekening penerimaan pembayaran siswa.',
                'Value_Type' => 'text',
                'Setting_Value' => '888-999-777',
            ],
            [
                'Setting_ID' => 'SET_BANK_HOLDER',
                'Category' => 'Company_Bank',
                'Setting_Key' => 'COMPANY_BANK_HOLDER',
                'Setting_Name' => 'Nama Pemilik Rekening',
                'Description' => 'Nama pemilik rekening yang ditampilkan pada dokumen pembayaran.',
                'Value_Type' => 'text',
                'Setting_Value' => 'PT WAKAMIYA INDONESIA',
            ],
            [
                'Setting_ID' => 'SET_BANK_BRANCH',
                'Category' => 'Company_Bank',
                'Setting_Key' => 'COMPANY_BANK_BRANCH',
                'Setting_Name' => 'Cabang Bank',
                'Description' => 'Cabang bank penerima pembayaran.',
                'Value_Type' => 'text',
                'Setting_Value' => 'KCU Jakarta',
            ],
            [
                'Setting_ID' => 'SET_BANK_QR_PAYMENT_URL',
                'Category' => 'Company_Bank',
                'Setting_Key' => 'COMPANY_QR_PAYMENT_URL',
                'Setting_Name' => 'URL QRIS / QR Pembayaran',
                'Description' => 'URL gambar QR pembayaran opsional untuk invoice dan instruksi pembayaran.',
                'Value_Type' => 'text',
                'Setting_Value' => '',
            ],

            // ACADEMIC GENERAL
            [
                'Setting_ID' => 'SET_ACADEMIC_YEAR',
                'Category' => 'Academic',
                'Setting_Key' => 'ACADEMIC_YEAR',
                'Setting_Name' => 'Tahun Akademik / Angkatan Aktif',
                'Description' => 'Tahun ajaran akademik yang saat ini sedang berlangsung (misal: 2025/2026).',
                'Value_Type' => 'text',
                'Setting_Value' => '2025/2026',
            ],
            [
                'Setting_ID' => 'SET_ACADEMIC_SEMESTER',
                'Category' => 'Academic',
                'Setting_Key' => 'ACADEMIC_SEMESTER',
                'Setting_Name' => 'Semester Aktif',
                'Description' => 'Semester akademik berjalan (Ganjil / Genap).',
                'Value_Type' => 'text',
                'Setting_Value' => 'Ganjil',
            ],
            [
                'Setting_ID' => 'SET_ACADEMIC_MIN_ATTENDANCE',
                'Category' => 'Academic',
                'Setting_Key' => 'MIN_ATTENDANCE_PERCENTAGE',
                'Setting_Name' => 'Batas Kehadiran Minimum Ujian (%)',
                'Description' => 'Persentase kehadiran minimum siswa untuk dapat mengikuti ujian kelulusan.',
                'Value_Type' => 'number',
                'Setting_Value' => '75',
            ],
            [
                'Setting_ID' => 'SET_ACADEMIC_MAX_STUDENTS_PER_CLASS',
                'Category' => 'Academic',
                'Setting_Key' => 'MAX_STUDENTS_PER_CLASS',
                'Setting_Name' => 'Kapasitas Maksimal Siswa per Kelas',
                'Description' => 'Batas jumlah siswa maksimal dalam 1 rombongan belajar/kelas.',
                'Value_Type' => 'number',
                'Setting_Value' => '30',
            ],
            [
                'Setting_ID' => 'SET_ACADEMIC_AUTO_ENROLL',
                'Category' => 'Academic',
                'Setting_Key' => 'AUTO_ENROLL_STUDENTS',
                'Setting_Name' => 'Penempatan Otomatis Siswa Baru',
                'Description' => 'Otomatis menempatkan siswa baru terdaftar ke kelas aktif.',
                'Value_Type' => 'boolean',
                'Setting_Value' => 'true',
            ],

            // ACADEMIC ASSESSMENT & GRADING H8.21
            [
                'Setting_ID' => 'SET_ASSESSMENT_PASSING_GRADE',
                'Category' => 'Assessment',
                'Setting_Key' => 'PASSING_GRADE_MINIMUM',
                'Setting_Name' => 'Nilai KKM / Minimum Kelulusan (H8.21)',
                'Description' => 'Batas nilai minimum (skala 0 - 100) untuk dinyatakan LULUS mata pelajaran.',
                'Value_Type' => 'number',
                'Setting_Value' => '75',
            ],
            [
                'Setting_ID' => 'SET_ASSESSMENT_MAX_SCORE',
                'Category' => 'Assessment',
                'Setting_Key' => 'MAX_SCORE_SCALE',
                'Setting_Name' => 'Skala Maksimum Penilaian',
                'Description' => 'Skala batas maksimum skor nilai siswa (Default: 100).',
                'Value_Type' => 'number',
                'Setting_Value' => '100',
            ],
            [
                'Setting_ID' => 'SET_ASSESSMENT_WEIGHT_EXAM',
                'Category' => 'Assessment',
                'Setting_Key' => 'WEIGHT_EXAM_PERCENT',
                'Setting_Name' => 'Bobot Ujian Akhir / UKK (%)',
                'Description' => 'Persentase bobot nilai ujian kelulusan akhir terhadap Nilai Raport.',
                'Value_Type' => 'number',
                'Setting_Value' => '40',
            ],
            [
                'Setting_ID' => 'SET_ASSESSMENT_WEIGHT_ASSIGNMENT',
                'Category' => 'Assessment',
                'Setting_Key' => 'WEIGHT_ASSIGNMENT_PERCENT',
                'Setting_Name' => 'Bobot Tugas & Evaluasi (%)',
                'Description' => 'Persentase bobot akumulasi nilai tugas harian terhadap Nilai Raport.',
                'Value_Type' => 'number',
                'Setting_Value' => '30',
            ],
            [
                'Setting_ID' => 'SET_ASSESSMENT_WEIGHT_ATTENDANCE',
                'Category' => 'Assessment',
                'Setting_Key' => 'WEIGHT_ATTENDANCE_PERCENT',
                'Setting_Name' => 'Bobot Kehadiran & Presensi (%)',
                'Description' => 'Persentase bobot kehadiran siswa terhadap Nilai Raport.',
                'Value_Type' => 'number',
                'Setting_Value' => '30',
            ],

            // NOTIFICATION
            [
                'Setting_ID' => 'SET_NOTIF_ENABLE',
                'Category' => 'Notification',
                'Setting_Key' => 'NOTIFICATION_ENABLED',
                'Setting_Name' => 'Aktifkan Notifikasi Sistem',
                'Description' => 'Mengontrol pengiriman notifikasi internal ke pengguna.',
                'Value_Type' => 'boolean',
                'Setting_Value' => 'true',
            ],
            [
                'Setting_ID' => 'SET_NOTIF_SOUND',
                'Category' => 'Notification',
                'Setting_Key' => 'NOTIFICATION_SOUND',
                'Setting_Name' => 'Suara Notifikasi',
                'Description' => 'Bunyi beep/suara saat notifikasi baru masuk.',
                'Value_Type' => 'boolean',
                'Setting_Value' => 'true',
            ],

            // EMAIL DELIVERY CONNECTION CENTER
            [
                'Setting_ID' => 'SET_EMAIL_PROVIDER',
                'Category' => 'Email_Delivery',
                'Setting_Key' => 'EMAIL_PROVIDER',
                'Setting_Name' => 'Provider Email Aktif',
                'Description' => 'Provider autentikasi pengiriman email WMS (google, microsoft, smtp, none).',
                'Value_Type' => 'text',
                'Setting_Value' => 'none',
            ],
            [
                'Setting_ID' => 'SET_EMAIL_STATUS',
                'Category' => 'Email_Delivery',
                'Setting_Key' => 'EMAIL_CONNECTION_STATUS',
                'Setting_Name' => 'Status Koneksi Email',
                'Description' => 'Status koneksi pengiriman email (connected, error, disconnected).',
                'Value_Type' => 'text',
                'Setting_Value' => 'disconnected',
            ],
            [
                'Setting_ID' => 'SET_EMAIL_CONNECTED_AT',
                'Category' => 'Email_Delivery',
                'Setting_Key' => 'EMAIL_CONNECTED_AT',
                'Setting_Name' => 'Waktu Terhubung Email',
                'Description' => 'Waktu terakhir akun email terhubung ke WMS.',
                'Value_Type' => 'text',
                'Setting_Value' => '',
            ],
            [
                'Setting_ID' => 'SET_EMAIL_CONNECTED_ACCOUNT',
                'Category' => 'Email_Delivery',
                'Setting_Key' => 'EMAIL_CONNECTED_ACCOUNT',
                'Setting_Name' => 'Akun Email Terhubung',
                'Description' => 'Alamat akun email yang saat ini terautentikasi.',
                'Value_Type' => 'text',
                'Setting_Value' => '',
            ],
            [
                'Setting_ID' => 'SET_EMAIL_FROM_ADDRESS',
                'Category' => 'Email_Delivery',
                'Setting_Key' => 'EMAIL_FROM_ADDRESS',
                'Setting_Name' => 'Alamat Email Pengirim (From Address)',
                'Description' => 'Alamat email yang tampil sebagai pengirim pesan WMS.',
                'Value_Type' => 'text',
                'Setting_Value' => 'hr@wakamiya.ac.id',
            ],
            [
                'Setting_ID' => 'SET_EMAIL_FROM_NAME',
                'Category' => 'Email_Delivery',
                'Setting_Key' => 'EMAIL_FROM_NAME',
                'Setting_Name' => 'Nama Pengirim (From Name)',
                'Description' => 'Nama instansi/sistem yang tampil sebagai pengirim email.',
                'Value_Type' => 'text',
                'Setting_Value' => 'WAKAMIYA MANAGEMENT SYSTEM',
            ],
            [
                'Setting_ID' => 'SET_EMAIL_REPLY_TO',
                'Category' => 'Email_Delivery',
                'Setting_Key' => 'EMAIL_REPLY_TO',
                'Setting_Name' => 'Reply-To Address',
                'Description' => 'Alamat balasan email opsional.',
                'Value_Type' => 'text',
                'Setting_Value' => 'hr@wakamiya.ac.id',
            ],
            [
                'Setting_ID' => 'SET_EMAIL_CREDENTIAL_DATA',
                'Category' => 'Email_Delivery',
                'Setting_Key' => 'EMAIL_CREDENTIAL_DATA',
                'Setting_Name' => 'Kredensial Email Tersandi',
                'Description' => 'Payload token OAuth / kredensial SMTP tersandi (encrypted).',
                'Value_Type' => 'textarea',
                'Setting_Value' => '',
            ],

            // SECURITY
            [
                'Setting_ID' => 'SET_SEC_SESSION_TIMEOUT',
                'Category' => 'Security',
                'Setting_Key' => 'SESSION_TIMEOUT_MINUTES',
                'Setting_Name' => 'Session Timeout (Menit)',
                'Description' => 'Batas waktu inaktivitas pengguna sebelum otomatis dilogout.',
                'Value_Type' => 'number',
                'Setting_Value' => '120',
            ],
            [
                'Setting_ID' => 'SET_SEC_LOGIN_MAX',
                'Category' => 'Security',
                'Setting_Key' => 'LOGIN_MAX_ATTEMPTS',
                'Setting_Name' => 'Maksimal Percobaan Login',
                'Description' => 'Batas percobaan password salah sebelum akun terkunci sementara.',
                'Value_Type' => 'number',
                'Setting_Value' => '5',
            ],

            // WORKFLOW
            [
                'Setting_ID' => 'SET_WF_AUTO_LEAVE',
                'Category' => 'Workflow',
                'Setting_Key' => 'WORKFLOW_AUTO_APPROVE_LEAVE',
                'Setting_Name' => 'Persetujuan Cuti Otomatis',
                'Description' => 'Otomatis menyetujui pengajuan cuti < 2 hari.',
                'Value_Type' => 'boolean',
                'Setting_Value' => 'false',
            ],

            // SYSTEM
            [
                'Setting_ID' => 'SET_SYS_MAINTENANCE',
                'Category' => 'System',
                'Setting_Key' => 'MAINTENANCE_MODE',
                'Setting_Name' => 'Mode Pemeliharaan (Maintenance Mode)',
                'Description' => 'Mengunci sistem dari pengguna non-administrator saat perawatan.',
                'Value_Type' => 'boolean',
                'Setting_Value' => 'false',
            ],
        ];
    }

    public function getSettings()
    {
        return Cache::rememberForever('system_settings_all', function () {
            $fromRepo = collect($this->settingRepo->getAll());
            $defaults = collect($this->getDefaultSettings());

            $result = collect();

            foreach ($defaults as $defaultItem) {
                $found = $fromRepo->first(function ($item) use ($defaultItem) {
                    return ($item['Setting_ID'] ?? '') === $defaultItem['Setting_ID']
                        || (! empty($item['Setting_Key']) && ($item['Setting_Key'] ?? '') === $defaultItem['Setting_Key']);
                });

                if ($found) {
                    $filteredFound = array_filter($found, fn ($val) => $val !== null);
                    $merged = array_merge($defaultItem, $filteredFound);
                    $result->push($merged);
                } else {
                    $result->push($defaultItem);
                }
            }

            foreach ($fromRepo as $repoItem) {
                $alreadyAdded = $result->first(function ($item) use ($repoItem) {
                    return ($item['Setting_ID'] ?? '') === ($repoItem['Setting_ID'] ?? '')
                        || (! empty($item['Setting_Key']) && ($item['Setting_Key'] ?? '') === ($repoItem['Setting_Key'] ?? ''));
                });
                if (! $alreadyAdded) {
                    $result->push($repoItem);
                }
            }

            return $result;
        });
    }

    public function getParameters()
    {
        return Cache::rememberForever('system_parameters_all', function () {
            return $this->paramRepo->getAll();
        });
    }

    public function get($key, $default = null)
    {
        $setting = $this->getSettings()->first(function ($item) use ($key) {
            return ($item['Setting_Key'] ?? '') === $key || ($item['Setting_ID'] ?? '') === $key;
        });
        if ($setting && isset($setting['Setting_Value']) && $setting['Setting_Value'] !== '') {
            return $setting['Setting_Value'];
        }

        return $default;
    }

    public function parameter($module, $key, $default = null)
    {
        $param = $this->getParameters()->where('Module', $module)->firstWhere('Parameter_Key', $key);
        if ($param && isset($param['Parameter_Value']) && $param['Parameter_Value'] !== '') {
            return $param['Parameter_Value'];
        }

        return $default;
    }

    public function category($category)
    {
        return $this->getSettings()->where('Category', $category)->values();
    }

    public function prepareSettingsForUpdate(array $settingsData): array
    {
        $settings = $this->getSettings();
        $prepared = [];
        $errors = [];
        $submittedByKey = [];

        foreach ($settingsData as $id => $value) {
            $setting = $settings->firstWhere('Setting_ID', $id) ?: $settings->firstWhere('Setting_Key', $id);
            if (! $setting) {
                $errors[] = "Pengaturan {$id} tidak dikenal.";

                continue;
            }

            $value = is_array($value) ? '' : trim((string) $value);
            $key = $setting['Setting_Key'] ?? $id;
            $type = strtolower($setting['Value_Type'] ?? 'text');
            $label = $setting['Setting_Name'] ?? $key;

            if ($type === 'boolean') {
                $normalizedBoolean = $this->normalizeBoolean($value);
                if ($normalizedBoolean === null) {
                    $errors[] = "{$label} harus bernilai Aktif atau Nonaktif.";

                    continue;
                }
                $value = $normalizedBoolean;
            }

            if ($type === 'number' && ! is_numeric($value)) {
                $errors[] = "{$label} harus berupa angka.";

                continue;
            }

            if ($type === 'color' && ! preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
                $errors[] = "{$label} harus memakai format warna HEX, contoh #38BDF8.";

                continue;
            }

            $message = $this->validateSettingRule($key, $label, $value);
            if ($message !== null) {
                $errors[] = $message;

                continue;
            }

            if ($type === 'color') {
                $value = strtoupper($value);
            }

            $prepared[$id] = $value;
            $submittedByKey[$key] = $value;
        }

        $weightKeys = ['WEIGHT_EXAM_PERCENT', 'WEIGHT_ASSIGNMENT_PERCENT', 'WEIGHT_ATTENDANCE_PERCENT'];
        if (count(array_intersect($weightKeys, array_keys($submittedByKey))) === count($weightKeys)) {
            $totalWeight = array_sum(array_map('floatval', array_intersect_key($submittedByKey, array_flip($weightKeys))));
            if (abs($totalWeight - 100) > 0.001) {
                $errors[] = 'Total bobot penilaian ujian, tugas, dan kehadiran harus tepat 100%.';
            }
        }

        if (isset($submittedByKey['PASSING_GRADE_MINIMUM'], $submittedByKey['MAX_SCORE_SCALE'])
            && (float) $submittedByKey['PASSING_GRADE_MINIMUM'] > (float) $submittedByKey['MAX_SCORE_SCALE']) {
            $errors[] = 'Nilai KKM tidak boleh lebih besar dari skala maksimum penilaian.';
        }

        $attendanceKeys = ['WORK_START_TIME', 'WORK_END_TIME', 'LATE_TOLERANCE_MINUTES'];
        if (array_intersect($attendanceKeys, array_keys($submittedByKey))) {
            $effective = [];
            foreach ($attendanceKeys as $attendanceKey) {
                $stored = $settings->firstWhere('Setting_Key', $attendanceKey);
                $effective[$attendanceKey] = $submittedByKey[$attendanceKey]
                    ?? trim((string) ($stored['Setting_Value'] ?? ''));
            }

            $validStart = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $effective['WORK_START_TIME']);
            $validEnd = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $effective['WORK_END_TIME']);
            if ($validStart && $validEnd) {
                [$startHour, $startMinute] = array_map('intval', explode(':', $effective['WORK_START_TIME']));
                [$endHour, $endMinute] = array_map('intval', explode(':', $effective['WORK_END_TIME']));
                $windowMinutes = (($endHour * 60) + $endMinute) - (($startHour * 60) + $startMinute);

                if ($windowMinutes <= 0) {
                    $errors[] = 'Jam selesai absensi harus setelah jam mulai pada hari yang sama.';
                } elseif (preg_match('/^\d+$/', $effective['LATE_TOLERANCE_MINUTES'])
                    && (int) $effective['LATE_TOLERANCE_MINUTES'] > $windowMinutes) {
                    $errors[] = 'Toleransi keterlambatan tidak boleh melebihi rentang jam absensi.';
                }
            }
        }

        return [$prepared, $errors];
    }

    private function normalizeBoolean(string $value): ?string
    {
        $normalized = strtolower($value);
        if (in_array($normalized, ['true', '1', 'yes', 'on', 'aktif'], true)) {
            return 'true';
        }
        if (in_array($normalized, ['false', '0', 'no', 'off', 'nonaktif'], true)) {
            return 'false';
        }

        return null;
    }

    private function validateSettingRule(string $key, string $label, string $value): ?string
    {
        if (in_array($key, ['LPK_LATITUDE'], true) && CoordinateNormalizer::parse($value, -90, 90) === null) {
            return "{$label} harus di antara -90 dan 90.";
        }

        if (in_array($key, ['LPK_LONGITUDE'], true) && CoordinateNormalizer::parse($value, -180, 180) === null) {
            return "{$label} harus di antara -180 dan 180.";
        }

        if (in_array($key, ['LPK_ALLOWED_RADIUS_METERS', 'QR_TOKEN_TTL_SECONDS', 'MAX_SCORE_SCALE', 'INVOICE_DUE_DAYS'], true)
            && (float) $value <= 0) {
            return "{$label} harus lebih besar dari 0.";
        }

        if ($key === 'LATE_TOLERANCE_MINUTES'
            && (! preg_match('/^\d+$/', $value) || (int) $value > 1440)) {
            return "{$label} harus berupa menit bulat antara 0 dan 1440.";
        }

        if (in_array($key, ['WORK_START_TIME', 'WORK_END_TIME'], true) && ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
            return "{$label} harus memakai format HH:mm, contoh 08:00.";
        }

        if (in_array($key, ['PAYROLL_CUTOFF_DATE', 'PAYROLL_DISBURSEMENT_DATE'], true)
            && ((int) $value < 1 || (int) $value > 31)) {
            return "{$label} harus di antara tanggal 1 sampai 31.";
        }

        $percentageKeys = [
            'TAX_PERCENTAGE', 'BPJS_PERCENTAGE', 'MIN_ATTENDANCE_PERCENTAGE',
            'PASSING_GRADE_MINIMUM', 'WEIGHT_EXAM_PERCENT', 'WEIGHT_ASSIGNMENT_PERCENT',
            'WEIGHT_ATTENDANCE_PERCENT', 'LATE_FEE_PERCENTAGE',
        ];
        if (in_array($key, $percentageKeys, true) && ((float) $value < 0 || (float) $value > 100)) {
            return "{$label} harus di antara 0 sampai 100.";
        }

        $nonNegativeKeys = [
            'DEFAULT_BASIC_SALARY', 'SALARY_FINANCE', 'SALARY_TEACHER', 'SALARY_ACADEMIC',
            'SALARY_MARKETING', 'SALARY_HR', 'OVERTIME_RATE_HOUR', 'ABSENCE_DEDUCTION_PER_DAY',
            'DEFAULT_TUITION_FEE', 'MAX_STUDENTS_PER_CLASS',
        ];
        if (in_array($key, $nonNegativeKeys, true) && (float) $value < 0) {
            return "{$label} tidak boleh bernilai negatif.";
        }

        if (str_contains($key, 'EMAIL') && (str_contains($key, 'ADDRESS') || str_contains($key, 'REPLY_TO')) && $value !== '' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return "{$label} harus berupa alamat email yang valid.";
        }

        if ($key === 'INVOICE_CATEGORIES') {
            $categories = array_filter(array_map('trim', explode(',', $value)));
            if (empty($categories)) {
                return "{$label} minimal berisi satu kategori.";
            }
        }

        return null;
    }

    public function set($id, $value, $updaterEmail)
    {
        $all = $this->getSettings();
        $setting = $all->firstWhere('Setting_ID', $id) ?: $all->firstWhere('Setting_Key', $id);

        $settingId = $setting['Setting_ID'] ?? $id;
        $settingKey = $setting['Setting_Key'] ?? $id;

        $target = [
            'Setting_ID' => $settingId,
            'Category' => $setting['Category'] ?? 'Email_Delivery',
            'Setting_Key' => $settingKey,
            'Setting_Name' => $setting['Setting_Name'] ?? $id,
            'Description' => $setting['Description'] ?? '',
            'Value_Type' => $setting['Value_Type'] ?? 'text',
            'Setting_Value' => $value,
            'Updated_By' => $updaterEmail,
            'Updated_At' => now()->toDateTimeString(),
        ];

        $existing = $this->settingRepo->getById($settingId) ?: $this->settingRepo->getById($settingKey);

        if ($existing) {
            $actualId = $existing['Setting_ID'] ?? $settingId;
            $this->settingRepo->update($actualId, $target);
        } else {
            if (method_exists($this->settingRepo, 'append')) {
                $this->settingRepo->append($target);
            } elseif (method_exists($this->settingRepo, 'create')) {
                $this->settingRepo->create($target);
            } else {
                $this->settingRepo->update($settingId, $target);
            }
        }

        $this->reloadCache();

        return true;
    }

    public function updateParameter($id, $value)
    {
        $param = $this->paramRepo->getById($id);
        if ($param) {
            if (isset($param['Parameter_Value']) && (string) $param['Parameter_Value'] === (string) $value) {
                return true;
            }
            $param['Parameter_Value'] = $value;
            $param['Updated_At'] = now()->toDateTimeString();
            $this->paramRepo->update($id, $param);
            $this->reloadCache();

            return true;
        }

        return false;
    }

    public function clearCache()
    {
        Cache::forget('system_settings_all');
        Cache::forget('system_parameters_all');
        Cache::forget('system_company_profile_payload');
        Cache::forget('system_theme_tokens_payload');
        $this->settingRepo->clearCache();
        $this->paramRepo->clearCache();
    }

    public function reloadCache()
    {
        $this->clearCache();
        $this->getSettings();
        $this->getParameters();
    }

    public function getInvoiceCategories()
    {
        $categoryValue = $this->get('INVOICE_CATEGORIES', '');
        if (! empty($categoryValue)) {
            $categories = array_values(array_filter(array_map('trim', explode(',', $categoryValue))));

            return collect($categories)
                ->map(function ($category) {
                    $normalized = strtolower($category);
                    if ($normalized === 'pendidikan' || (str_contains($normalized, 'spp') && str_contains($normalized, 'pendidikan'))) {
                        return 'Biaya Pendidikan';
                    }

                    return $category;
                })
                ->unique()
                ->values()
                ->all();
        }

        return ['Biaya Pendidikan', 'Medical', 'JFT', 'JLPT', 'Dormitory', 'Air Ticket', 'Administration', 'SSW', 'Equipment', 'Other'];
    }

    public function getDefaultTuitionFee()
    {
        $fee = $this->get('DEFAULT_TUITION_FEE', null);
        if (is_numeric($fee)) {
            return (float) $fee;
        }

        return 7500000;
    }

    /**
     * Get centralized Company Profile configuration (H8.1–H8.3).
     * Reads from MASTER_SYSTEM_SETTING with safe fallback defaults.
     *
     * @return array{company: array, bank: array, document: array}
     */
    public function getCompanyProfile(): array
    {
        return Cache::rememberForever('system_company_profile_payload', function () {
            $name = $this->get('COMPANY_NAME')
                ?: ($this->get('SET_COMPANY_NAME')
                ?: ($this->get('SET_GENERAL_APP_NAME')
                ?: ($this->get('APP_NAME', 'WAKAMIYA MANAGEMENT SYSTEM'))));

            $tagline = $this->get('COMPANY_TAGLINE')
                ?: ($this->get('SET_COMPANY_TAGLINE')
                ?: ($this->get('SET_GENERAL_TAGLINE')
                ?: ($this->get('COMPANY_SLOGAN', 'Enterprise Human Resource Engine'))));

            $logo = $this->get('COMPANY_LOGO')
                ?: ($this->get('SET_COMPANY_LOGO')
                ?: ($this->get('BRAND_LOGO')
                ?: ($this->get('SET_BRAND_LOGO')
                ?: ($this->get('APP_LOGO', '')))));

            if (empty($logo) || $logo === 'img/logo.png.jpeg') {
                if (file_exists(storage_path('app/public/companies/logos/company_logo_1786935935.jpeg'))) {
                    $logo = 'storage/companies/logos/company_logo_1786935935.jpeg';
                } elseif (file_exists(storage_path('app/public/companies/logos/logo_1786932667.jpeg'))) {
                    $logo = 'storage/companies/logos/logo_1786932667.jpeg';
                }
            }

            $logoUrl = asset('img/logo.png.jpeg');
            if (! empty($logo)) {
                if (str_starts_with($logo, 'http')) {
                    $logoUrl = $logo;
                } elseif (file_exists(public_path($logo))) {
                    $logoUrl = asset($logo);
                } elseif (file_exists(public_path('storage/'.ltrim($logo, '/')))) {
                    $logoUrl = asset('storage/'.ltrim($logo, '/'));
                } elseif (file_exists(public_path(ltrim(str_replace('storage/', '', $logo), '/')))) {
                    $logoUrl = asset(ltrim(str_replace('storage/', '', $logo), '/'));
                } elseif (file_exists(storage_path('app/public/'.ltrim(str_replace('storage/', '', $logo), '/')))) {
                    $logoUrl = asset('storage/'.ltrim(str_replace('storage/', '', $logo), '/'));
                }
            }

            return [
                'company' => [
                    'name' => $name,
                    'tagline' => $tagline,
                    'logo' => $logo,
                    'logo_url' => $logoUrl,
                    'address' => $this->get('COMPANY_ADDRESS', 'Jl. Raya Wakamiya No. 88, Jakarta Selatan 12930'),
                    'phone' => $this->get('COMPANY_PHONE', '(021) 8000-9999'),
                    'whatsapp' => $this->get('COMPANY_WA', ''),
                    'email' => $this->get('COMPANY_EMAIL', 'hr@wakamiya.ac.id'),
                    'website' => $this->get('COMPANY_WEB', 'https://wakamiya.ac.id'),
                    'npwp' => $this->get('COMPANY_NPWP', ''),
                ],
                'bank' => [
                    'name' => $this->get('COMPANY_BANK_NAME', 'BANK BCA'),
                    'account_number' => $this->get('COMPANY_BANK_ACCOUNT', '888-999-777'),
                    'account_holder' => $this->get('COMPANY_BANK_HOLDER', 'PT WAKAMIYA INDONESIA'),
                    'branch' => $this->get('COMPANY_BANK_BRANCH', 'KCU Jakarta'),
                ],
                'document' => [
                    'signature_url' => $this->get('COMPANY_SIGNATURE_URL', ''),
                    'stamp_url' => $this->get('COMPANY_STAMP_URL', ''),
                    'signer_name' => $this->get('COMPANY_SIGNER_NAME', 'Dr. Reza Pekanbaru'),
                    'signer_title' => $this->get('COMPANY_SIGNER_TITLE', 'General Director & Founder'),
                    'prefix' => $this->get('DOCUMENT_PREFIX', $this->get('COMPANY_DOC_PREFIX', 'DOC/WMS/')),
                ],
            ];
        });
    }

    /**
     * Get dynamic Branding & Theme CSS Tokens.
     */
    public function getThemeTokens(): array
    {
        return Cache::rememberForever('system_theme_tokens_payload', function () {
            return [
                'primary' => $this->get('BRAND_PRIMARY_COLOR', '#38BDF8'),
                'secondary' => $this->get('BRAND_SECONDARY_COLOR', '#0F172A'),
                'accent' => $this->get('BRAND_ACCENT_COLOR', '#0EA5E9'),
                'sidebar_bg' => $this->get('BRAND_SIDEBAR_BG', '#111827'),
                'sidebar_text' => $this->get('BRAND_SIDEBAR_TEXT', '#94A3B8'),
                'sidebar_active_bg' => $this->get('BRAND_SIDEBAR_ACTIVE_BG', '#1E293B'),
                'sidebar_active' => $this->get('BRAND_SIDEBAR_ACTIVE_TEXT', '#38BDF8'),
                'topbar_bg' => ($this->get('BRAND_TOPBAR_BG', '#FFFFFF') === '#111827') ? '#FFFFFF' : $this->get('BRAND_TOPBAR_BG', '#FFFFFF'),
                'topbar_text' => $this->get('BRAND_TOPBAR_TEXT', '#0F172A'),
                'page_bg' => $this->get('BRAND_PAGE_BG', '#E2E8F0'),
                'card_bg' => $this->get('BRAND_CARD_BG', '#FFFFFF'),
                'theme_mode' => $this->get('BRAND_THEME_MODE', 'dark'),
            ];
        });
    }

    /**
     * Get active Email Delivery Connection status and config.
     * Credentials are decrypted securely for runtime use.
     */
    public function getEmailDeliveryConfig(): array
    {
        $company = $this->getCompanyProfile();

        $provider = strtolower($this->get('SET_EMAIL_PROVIDER', $this->get('EMAIL_PROVIDER', 'none')));
        $status = strtolower($this->get('SET_EMAIL_STATUS', $this->get('EMAIL_CONNECTION_STATUS', 'disconnected')));
        $connectedAt = $this->get('SET_EMAIL_CONNECTED_AT', $this->get('EMAIL_CONNECTED_AT', null));
        $connectedAccount = $this->get('SET_EMAIL_CONNECTED_ACCOUNT', $this->get('EMAIL_CONNECTED_ACCOUNT', null));

        $fromAddress = $this->get('SET_EMAIL_FROM_ADDRESS', $this->get('EMAIL_FROM_ADDRESS', $company['company']['email'] ?? 'hr@wakamiya.ac.id'));
        $fromName = $this->get('SET_EMAIL_FROM_NAME', $this->get('EMAIL_FROM_NAME', $company['company']['name'] ?? 'WAKAMIYA MANAGEMENT SYSTEM'));
        $replyTo = $this->get('SET_EMAIL_REPLY_TO', $this->get('EMAIL_REPLY_TO', $fromAddress));

        // Read encrypted credential payload if exists
        $encryptedPayload = $this->get('SET_EMAIL_CREDENTIAL_DATA', $this->get('EMAIL_CREDENTIAL_DATA', null));
        $credentials = null;
        if ($encryptedPayload) {
            try {
                $decrypted = Crypt::decryptString($encryptedPayload);
                $credentials = json_decode($decrypted, true);
            } catch (\Throwable $e) {
                $credentials = null;
            }
        }

        $isHealthy = ($status === 'connected' && (! empty($credentials) || in_array($provider, ['google', 'microsoft', 'smtp'])));

        // Sanitize credentials payload so tokens & cleartext passwords are NEVER exposed to frontend/Blade views
        $sanitizedCredentials = null;
        if (is_array($credentials)) {
            $sanitizedCredentials = array_diff_key($credentials, array_flip(['access_token', 'refresh_token', 'password', 'token']));
        }

        return [
            'provider' => $provider,
            'status' => $status,
            'connected_at' => $connectedAt,
            'connected_account' => $connectedAccount ?: $fromAddress,
            'from_address' => $fromAddress ?: 'hr@wakamiya.ac.id',
            'from_name' => $fromName ?: 'WAKAMIYA MANAGEMENT SYSTEM',
            'reply_to' => $replyTo ?: $fromAddress,
            'is_healthy' => $isHealthy,
            'has_credentials' => ! empty($credentials),
            'credentials' => $sanitizedCredentials,
        ];
    }
}
