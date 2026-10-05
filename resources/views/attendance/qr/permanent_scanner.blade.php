@extends('layouts.app')
@section('header', 'Verifikasi Presensi')
@section('content')
@php
    $qrRadius = app(\App\Services\Core\SystemSettingService::class)
        ->get('LPK_ALLOWED_RADIUS_METERS', null);
    $qrRadiusLabel = is_numeric($qrRadius)
        ? rtrim(rtrim(number_format((float) $qrRadius, 2, '.', ''), '0'), '.') . ' meter'
        : 'belum dikonfigurasi';
    $attendanceStart = app(\App\Services\Core\SystemSettingService::class)->get(
        \App\Services\Attendance\AttendanceWindowService::START_SETTING_KEY,
        \App\Services\Attendance\AttendanceWindowService::DEFAULT_START
    );
    $attendanceEnd = app(\App\Services\Core\SystemSettingService::class)->get(
        \App\Services\Attendance\AttendanceWindowService::END_SETTING_KEY,
        \App\Services\Attendance\AttendanceWindowService::DEFAULT_END
    );
    $attendanceWindowLabel = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) $attendanceStart)
        && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) $attendanceEnd)
        ? "{$attendanceStart}–{$attendanceEnd} WIB"
        : 'Konfigurasi jam belum valid';
@endphp

<div class="max-w-md mx-auto space-y-4 pb-20 select-none" x-data="permanentScannerEngine()">

    @if($error)
        <!-- ==================================================== -->
        <!-- STATE: ERROR DARI SERVER (INACTIVE / INVALID QR)     -->
        <!-- ==================================================== -->
        <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xl text-center space-y-5 py-8">
            <div class="w-16 h-16 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center text-3xl mx-auto border border-rose-200">
                ⚠️
            </div>
            <div class="space-y-1">
                <h3 class="text-lg font-black text-slate-900">QR Tidak Sesuai</h3>
                <p class="text-xs text-slate-500 font-medium max-w-xs mx-auto">
                    {{ $error }}
                </p>
            </div>
            <a href="{{ route('dashboard') }}" class="block w-full py-3.5 bg-slate-800 text-white font-extrabold text-xs rounded-2xl shadow-lg">
                Kembali ke Beranda
            </a>
        </div>
    @else
        <!-- ==================================================== -->
        <!-- STATE 1: REQUESTING LOCATION                         -->
        <!-- ==================================================== -->
        <div x-show="viewState === 'REQUESTING_LOCATION'" class="bg-white rounded-3xl p-8 border border-slate-200 shadow-xl text-center py-16 space-y-6">
            <div class="w-20 h-20 bg-sky-100 text-sky-600 rounded-full flex items-center justify-center text-4xl mx-auto border-4 border-sky-200 animate-pulse">
                📍
            </div>
            <div class="space-y-2">
                <h3 class="text-xl font-black text-slate-900">Verifikasi Lokasi</h3>
                <p class="text-xs text-slate-500 font-medium max-w-xs mx-auto">
                    Mendeteksi lokasi Anda. Pastikan Anda berada di area LPK WAKAMIYA.
                </p>
                @if(($type ?? '') === 'STUDENT')
                    <p class="text-xs text-sky-700 font-bold">Jam absensi: {{ $attendanceWindowLabel }}</p>
                    <p class="text-[11px] text-slate-500 font-medium">Absensi siswa di luar waktu tersebut tidak dapat dilakukan.</p>
                @endif
            </div>
            
            <button type="button" @click="requestLocation()" x-show="!isRequesting" class="w-full py-3.5 bg-gradient-to-r from-sky-500 to-blue-600 text-white font-extrabold text-xs rounded-2xl shadow-lg hover:shadow-sky-500/30 transition-shadow">
                Verifikasi Lokasi Sekarang
            </button>
            <div x-show="isRequesting" class="text-xs font-bold text-sky-600 animate-pulse">
                Sedang memuat GPS...
            </div>
        </div>

        <!-- ==================================================== -->
        <!-- STATE 2: PROCESSING (VERIFYING DISTANCE & TOKEN)     -->
        <!-- ==================================================== -->
        <div x-show="viewState === 'PROCESSING'" class="bg-white rounded-3xl p-8 border border-slate-200 shadow-xl text-center py-16 space-y-4">
            <div class="w-16 h-16 border-4 border-sky-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
            <h3 class="text-lg font-black text-slate-900">Memverifikasi Presensi...</h3>
            <p class="text-xs text-slate-500 font-medium max-w-xs mx-auto">Sedang memeriksa lokasi dan identitas presensi...</p>
        </div>

        <!-- ==================================================== -->
        <!-- STATE 3: PRESENSI BERHASIL!                          -->
        <!-- ==================================================== -->
        <div x-show="viewState === 'SUCCESS'" class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xl text-center space-y-5 py-8">
            <div class="relative w-24 h-24 mx-auto flex items-center justify-center">
                <div class="absolute inset-0 rounded-full border-4 border-emerald-100 animate-ping opacity-75"></div>
                <div class="w-20 h-20 bg-emerald-500 text-white rounded-full flex items-center justify-center text-4xl font-black shadow-xl shadow-emerald-500/30">
                    ✓
                </div>
            </div>
            
            <div class="space-y-1">
                <h2 class="text-2xl font-black text-slate-900 tracking-tight">Presensi Berhasil!</h2>
                <p class="text-xs text-slate-500 font-medium">Anda telah berhasil melakukan presensi</p>
            </div>

            <div class="bg-slate-50 border border-slate-200/80 rounded-2xl p-4 text-xs space-y-2.5 text-left font-medium">
                <h4 class="text-[11px] font-black text-slate-800 border-b border-slate-200 pb-2">Detail Presensi</h4>
                <div class="flex justify-between border-b border-slate-200/60 pb-2">
                    <span class="text-slate-500">Lokasi</span>
                    <span class="font-bold text-slate-800">LPK WAKAMIYA</span>
                </div>
                <div class="flex justify-between border-b border-slate-200/60 pb-2">
                    <span class="text-slate-500">Jarak Lokasi</span>
                    <span class="font-bold text-slate-800" x-text="successData.distanceText"></span>
                </div>
                <div class="flex justify-between border-b border-slate-200/60 pb-2">
                    <span class="text-slate-500">Pesan</span>
                    <span class="font-bold text-emerald-600" x-text="successData.message"></span>
                </div>
            </div>

            <a href="{{ route('dashboard') }}" class="block w-full py-3.5 bg-slate-800 text-white font-extrabold text-xs rounded-2xl shadow-lg">
                Kembali ke Beranda
            </a>
        </div>

        <!-- ==================================================== -->
        <!-- STATE 4: ERROR / OUTSIDE GEOFENCE / DLL              -->
        <!-- ==================================================== -->
        <div x-show="viewState === 'ERROR'" class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xl text-center space-y-5 py-8">
            <div class="w-16 h-16 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center text-3xl mx-auto border border-rose-200">
                ❌
            </div>
            <div class="space-y-1">
                <h3 class="text-lg font-black text-slate-900">Presensi Gagal</h3>
                <p class="text-xs text-slate-500 font-medium max-w-xs mx-auto" x-text="errorMessage">
                    Terjadi kesalahan.
                </p>
            </div>
            
            <div x-show="errorDistance" class="bg-slate-50 border border-slate-200 p-4 rounded-2xl text-xs space-y-1 my-4">
                <p class="text-slate-500 font-medium">Jarak Anda Saat Ini</p>
                <p class="text-2xl font-black text-slate-900" x-text="errorDistance"></p>
                <p class="text-[11px] font-bold text-sky-600 pt-1">Batas maksimal geofence: {{ $qrRadiusLabel }}</p>
            </div>

            <button type="button" @click="reset()" class="w-full py-3.5 bg-gradient-to-r from-sky-500 to-blue-600 text-white font-extrabold text-xs rounded-2xl shadow-lg">
                Coba Lagi
            </button>
        </div>
@endif
</div>

@if(!$error)
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('permanentScannerEngine', () => ({
            viewState: 'REQUESTING_LOCATION', // REQUESTING_LOCATION, PROCESSING, SUCCESS, ERROR
            isRequesting: false,
            errorMessage: '',
            errorDistance: null,
            successData: {
                message: '',
                distanceText: ''
            },
            
            reset() {
                this.viewState = 'REQUESTING_LOCATION';
                this.isRequesting = false;
                this.errorMessage = '';
                this.errorDistance = null;
            },

            requestLocation() {
                this.isRequesting = true;
                if (!navigator.geolocation) {
                    this.showError("Geolocation tidak didukung oleh browser Anda.");
                    return;
                }

                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        this.verifyAttendance(position.coords.latitude, position.coords.longitude);
                    },
                    (error) => {
                        let msg = "Terjadi kesalahan saat mengambil lokasi GPS.";
                        if (error.code === error.PERMISSION_DENIED) {
                            msg = "Izin lokasi (GPS) ditolak. Mohon izinkan akses lokasi pada browser Anda.";
                        }
                        this.showError(msg);
                    },
                    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
                );
            },

            verifyAttendance(lat, lon) {
                this.viewState = 'PROCESSING';
                
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const verifyUrl = "{{ route('attendance.scan.verify', ['type' => strtolower($type), 'identifier' => $identifier]) }}";

                fetch(verifyUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        lat: lat,
                        lon: lon
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        this.successData.message = data.message;
                        this.successData.distanceText = data.distance ? (data.distance + " meter") : "Di dalam area";
                        this.viewState = 'SUCCESS';
                    } else {
                        // Extract distance from message if available (e.g., from existing H8.22 logic)
                        if (data.distance) {
                            this.errorDistance = data.distance + " meter";
                        }
                        this.showError(data.message || "Presensi gagal diproses.");
                    }
                })
                .catch(err => {
                    console.error(err);
                    this.showError("Terjadi kesalahan jaringan atau server. Silakan coba lagi.");
                });
            },

            showError(message) {
                this.errorMessage = message;
                this.viewState = 'ERROR';
                this.isRequesting = false;
            },

            init() {
                // Auto request location on load if user wants a smooth experience.
                // But typically, requiring a user click is better for browser permission prompts.
            }
        }));
    });
</script>
@endif

@endsection
