<?php

namespace App\Support\Presentation;

use App\Support\Academic\AssignmentStatus;
use Carbon\Carbon;
use DateTimeInterface;

final class IndonesianPresentation
{
    private const DAYS = [
        'monday' => 'Senin',
        'tuesday' => 'Selasa',
        'wednesday' => 'Rabu',
        'thursday' => 'Kamis',
        'friday' => 'Jumat',
        'saturday' => 'Sabtu',
        'sunday' => 'Minggu',
        'senin' => 'Senin',
        'selasa' => 'Selasa',
        'rabu' => 'Rabu',
        'kamis' => 'Kamis',
        'jumat' => 'Jumat',
        'sabtu' => 'Sabtu',
        'minggu' => 'Minggu',
    ];

    private const ROLES = [
        'student' => 'Siswa',
        'teacher' => 'Guru',
        'employee' => 'Karyawan',
        'academic' => 'Akademik',
        'finance' => 'Keuangan',
        'hr' => 'SDM',
        'marketing' => 'Pemasaran',
        'director' => 'Direktur',
        'administrator' => 'Administrator',
        'master' => 'Administrator Utama',
        'guest' => 'Tamu',
        'user' => 'Pengguna',
    ];

    private const LABELS = [
        'active' => 'Aktif',
        'inactive' => 'Tidak Aktif',
        'pending' => 'Menunggu',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'cancelled' => 'Dibatalkan',
        'canceled' => 'Dibatalkan',
        'draft' => 'Draf',
        'published' => 'Dipublikasikan',
        'expired' => 'Kedaluwarsa',
        'processing' => 'Diproses',
        'completed' => 'Selesai',
        'failed' => 'Gagal',
        'success' => 'Berhasil',
        'verified' => 'Terverifikasi',
        'waiting verification' => 'Menunggu Verifikasi',
        'need revision' => 'Perlu Revisi',
        'reversed' => 'Dikoreksi',
        'paid' => 'Lunas',
        'partial paid' => 'Dibayar Sebagian',
        'partially paid' => 'Dibayar Sebagian',
        'waiting payment' => 'Menunggu Pembayaran',
        'unpaid' => 'Belum Lunas',
        'overdue' => 'Terlambat',
        'waiting approval' => 'Menunggu Persetujuan',
        'closed' => 'Ditutup',
        'calculated' => 'Dihitung',
        'generated' => 'Dibuat',
        'submitted' => 'Diajukan',
        'present' => 'Hadir',
        'late' => 'Terlambat',
        'sick' => 'Sakit',
        'permitted' => 'Izin',
        'permission' => 'Izin',
        'leave' => 'Cuti',
        'absent' => 'Alpa',
        'scheduled' => 'Terjadwal',
        'ongoing' => 'Berlangsung',
        'open' => 'Dibuka',
        'archived' => 'Diarsipkan',
        'progress' => 'Berlangsung',
        'high' => 'Tinggi',
        'medium' => 'Sedang',
        'low' => 'Rendah',
        'urgent' => 'Mendesak',
        'all' => 'Semua',
        'all users' => 'Semua Pengguna',
        'true' => 'Aktif',
        'false' => 'Tidak Aktif',
        'yes' => 'Ya',
        'no' => 'Tidak',
        'male' => 'Laki-laki',
        'female' => 'Perempuan',
        'invoice' => 'Tagihan',
        'payment' => 'Pembayaran',
        'transaction' => 'Transaksi',
        'payroll' => 'Penggajian',
        'assignment' => 'Tugas',
        'assessment' => 'Penilaian',
        'attendance' => 'Kehadiran',
        'schedule' => 'Jadwal',
        'subject' => 'Mata Pelajaran',
        'class' => 'Kelas',
        'document' => 'Dokumen',
        'signed' => 'Ditandatangani',
        'unsigned' => 'Belum Ditandatangani',
        'permanent' => 'Tetap',
        'contract' => 'Kontrak',
        'probation' => 'Masa Percobaan',
        'resigned' => 'Mengundurkan Diri',
        'terminated' => 'Diberhentikan',
        'active employee' => 'Karyawan Aktif',
        'active teaching' => 'Aktif Mengajar',
        'inactive teaching' => 'Tidak Aktif Mengajar',
        'enrolled' => 'Terdaftar',
        'graduated' => 'Lulus',
        'drop out' => 'Putus Studi',
        'not graduated' => 'Belum Lulus',
    ];

    private const ACTIVITY_ACTIONS = [
        'create' => 'Membuat Data',
        'update' => 'Memperbarui Data',
        'delete' => 'Menghapus Data',
        'generate invoice' => 'Membuat Tagihan',
        'pay' => 'Pembayaran',
        'verify' => 'Verifikasi',
        'publish' => 'Menerbitkan',
        'login' => 'Masuk',
        'logout' => 'Keluar',
        'approve' => 'Menyetujui',
        'reject' => 'Menolak',
    ];

    private const ACTIVITY_MODULES = [
        'finance' => 'Keuangan',
        'finance transaction' => 'Transaksi Keuangan',
        'invoice' => 'Tagihan',
        'payment' => 'Pembayaran',
        'account' => 'Akun',
        'hr' => 'SDM',
        'academic' => 'Akademik',
        'marketing' => 'Pemasaran',
        'student' => 'Siswa',
        'teacher' => 'Guru',
        'employee' => 'Pegawai',
        'document' => 'Dokumen',
        'system' => 'Sistem',
        'user' => 'Pengguna',
        'activity log' => 'Log Aktivitas',
    ];

    private const FINANCE_REMINDER_TITLES = [
        'payment verification needed' => 'Verifikasi Pembayaran Diperlukan',
        'invoice overdue' => 'Tagihan Jatuh Tempo',
    ];

    public static function day(mixed $value, string $fallback = '-'): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return $fallback;
        }

        return self::DAYS[self::key($raw)] ?? $raw;
    }

    public static function paymentMethod(mixed $value): string
    {
        return match (self::key((string) $value)) {
            'cash', 'tunai' => 'Tunai',
            'transfer', 'bank transfer' => 'Transfer Bank',
            'qris' => 'QRIS',
            default => 'Metode pembayaran belum teridentifikasi',
        };
    }

    public static function paymentType(mixed $value): string
    {
        return match (self::key((string) $value)) {
            'student self service' => 'Pembayaran Mandiri Siswa',
            'student' => 'Pembayaran Siswa',
            'company' => 'Pembayaran Perusahaan',
            default => 'Pembayaran',
        };
    }

    public static function paymentStatus(mixed $value): string
    {
        return match (self::key((string) $value)) {
            'pending', 'waiting verification' => 'Menunggu Verifikasi',
            'verified' => 'Terverifikasi',
            'need revision' => 'Perlu Revisi',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
            'reversed' => 'Dikoreksi',
            default => 'Status pembayaran perlu diperiksa',
        };
    }

    public static function role(mixed $value, string $fallback = 'Pengguna'): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return $fallback;
        }

        return self::ROLES[self::key($raw)] ?? $raw;
    }

    public static function status(mixed $value, string $fallback = '-'): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return $fallback;
        }

        return self::knownLabel($raw) ?? $raw;
    }

    public static function assignmentStatus(mixed $value, string $fallback = '-'): string
    {
        return AssignmentStatus::label($value, $fallback);
    }

    public static function boolean(mixed $value, string $fallback = '-'): string
    {
        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return $fallback;
        }

        return match (self::key($raw)) {
            'true', 'yes', 'y', '1', 'active' => 'Ya',
            'false', 'no', 'n', '0', 'inactive' => 'Tidak',
            default => $raw,
        };
    }

    public static function gender(mixed $value, string $fallback = 'Belum diisi'): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return $fallback;
        }

        return match (self::key($raw)) {
            'l', 'male', 'pria', 'laki laki' => 'Laki-laki',
            'p', 'female', 'wanita', 'perempuan' => 'Perempuan',
            default => $raw,
        };
    }

    public static function age(mixed $birthDate): ?int
    {
        $raw = trim((string) $birthDate);
        if ($raw === '') {
            return null;
        }

        try {
            $date = Carbon::parse(str_replace('/', '-', $raw), 'Asia/Jakarta')->startOfDay();
            $today = Carbon::now('Asia/Jakarta')->startOfDay();

            return $date->isFuture() ? null : $date->age;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function enum(mixed $value, string $fallback = '-'): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return $fallback;
        }

        return self::knownLabel($raw) ?? $raw;
    }

    public static function knownLabel(mixed $value): ?string
    {
        $key = self::key((string) $value);

        return self::DAYS[$key]
            ?? self::ROLES[$key]
            ?? self::LABELS[$key]
            ?? null;
    }

    public static function financeReminderTitle(mixed $value, string $fallback = '-'): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return $fallback;
        }

        return self::FINANCE_REMINDER_TITLES[self::key($raw)] ?? $raw;
    }

    public static function activityAction(mixed $value, string $fallback = 'Aktivitas'): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return $fallback;
        }

        return self::ACTIVITY_ACTIONS[self::key($raw)] ?? str_replace('_', ' ', $raw);
    }

    public static function activityModule(mixed $value, string $fallback = 'Aktivitas'): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return $fallback;
        }

        return self::ACTIVITY_MODULES[self::key($raw)] ?? str_replace('_', ' ', $raw);
    }

    public static function activityDescription(mixed $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '-';
        }

        $normalized = str_replace('â€”', '—', $raw);
        $parts = preg_split('/\s*—\s*/u', $normalized, 2);
        if (! is_array($parts) || count($parts) < 2) {
            $parts = preg_split('/\s*-\s*/u', $normalized, 2) ?: [$normalized];
        }

        $module = trim((string) ($parts[0] ?? ''));
        $detail = trim((string) ($parts[1] ?? ''));

        return self::activityModule($module).' — '.self::activityDetail($detail);
    }

    private static function activityDetail(string $detail): string
    {
        $detail = trim($detail);
        if ($detail === '') {
            return '-';
        }

        $key = self::key($detail);
        if ($key === 'generated' || $key === 'aktivitas generate invoice') {
            return 'Tagihan berhasil dibuat otomatis';
        }

        if (preg_match('/^Aktivitas\s+([A-Za-z_]+)\s+pada\s+(.+)$/u', $detail, $matches) === 1) {
            $action = self::activityAction($matches[1]);

            return $action.' pada '.trim($matches[2]);
        }

        if (preg_match('/^([A-Za-z_]+)\s+pada\s+(.+)$/u', $detail, $matches) === 1) {
            $action = self::activityAction($matches[1]);

            return $action.' pada '.trim($matches[2]);
        }

        return $detail;
    }

    public static function date(mixed $value, string $format = 'j F Y', string $fallback = '-'): string
    {
        if ($value === null || (! $value instanceof DateTimeInterface && trim((string) $value) === '')) {
            return $fallback;
        }

        try {
            $date = $value instanceof DateTimeInterface
                ? Carbon::instance($value)
                : Carbon::parse(
                    str_replace('/', '-', trim((string) $value)),
                    'Asia/Jakarta'
                );

            $format = str_replace(['d F', 'd M'], ['j F', 'j M'], $format);

            try {
                return $date->locale('id')->translatedFormat($format);
            } catch (\Throwable) {
                // Keep presentation deterministic even when PHP intl/locale
                // data is unavailable on the host.
                $english = $date->format($format);

                return strtr($english, [
                    'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
                    'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu',
                    'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret', 'April' => 'April',
                    'May' => 'Mei', 'June' => 'Juni', 'July' => 'Juli', 'August' => 'Agustus',
                    'September' => 'September', 'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember',
                ]);
            }
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private static function key(string $value): string
    {
        $value = preg_replace('/[_-]+/', ' ', trim($value)) ?? trim($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return strtolower($value);
    }
}
