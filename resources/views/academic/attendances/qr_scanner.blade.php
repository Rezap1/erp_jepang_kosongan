@extends('layouts.app')
@section('header', 'Pindai QR')
@section('content')
@php
    $settingService = app(\App\Services\Core\SystemSettingService::class);
    $attendanceStart = $settingService->get(
        \App\Services\Attendance\AttendanceWindowService::START_SETTING_KEY,
        \App\Services\Attendance\AttendanceWindowService::DEFAULT_START
    );
    $attendanceEnd = $settingService->get(
        \App\Services\Attendance\AttendanceWindowService::END_SETTING_KEY,
        \App\Services\Attendance\AttendanceWindowService::DEFAULT_END
    );
    $attendanceWindowLabel = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) $attendanceStart)
        && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) $attendanceEnd)
        ? "{$attendanceStart}–{$attendanceEnd} WIB"
        : 'Konfigurasi jam belum valid';
@endphp

<div class="max-w-md mx-auto space-y-4 pb-20 select-none" x-data="studentQrScannerEngine()">

    <!-- ==================================================== -->
    <!-- STATE 1: PINDAI QR (ACTIVE CAMERA SCANNER VIEW)      -->
    <!-- ==================================================== -->
    <div x-show="viewState === 'SCANNING'" class="space-y-4">
        <!-- MOBILE HEADER WITH BACK LINK -->
        <div class="flex items-center justify-between pt-1 pb-1">
            <a href="{{ route('dashboard.student') }}" class="flex items-center gap-2 text-sm font-bold text-slate-800 hover:text-sky-600 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                <span>Pindai QR</span>
            </a>
        </div>

        <p class="text-xs text-center font-medium text-slate-400 -mt-2">Arahkan kamera ke QR Code</p>

        <!-- CAMERA VIEWPORT WITH WAKAMIYA SKY BLUE CORNER GUIDES & LASER SCAN -->
        <div class="relative overflow-hidden rounded-3xl border-2 border-slate-900 bg-black aspect-square flex items-center justify-center shadow-2xl">
            <div id="student-qr-reader" class="w-full h-full"></div>
            
            <!-- CORNER RETICLE GUIDES & LASER LINE -->
            <div class="absolute inset-0 pointer-events-none flex items-center justify-center">
                <div class="w-56 h-56 border-2 border-sky-400/80 rounded-3xl relative">
                    <!-- Laser Scan Line (Wakamiya Sky Blue #38BDF8 Glow) -->
                    <div class="absolute inset-x-2 h-0.5 bg-gradient-to-r from-transparent via-sky-400 to-transparent shadow-[0_0_15px_#38bdf8] animate-[pulse_1.5s_infinite]"></div>

                    <div class="absolute -top-1.5 -left-1.5 w-6 h-6 border-t-4 border-l-4 border-sky-400 rounded-tl-xl"></div>
                    <div class="absolute -top-1.5 -right-1.5 w-6 h-6 border-t-4 border-r-4 border-sky-400 rounded-tr-xl"></div>
                    <div class="absolute -bottom-1.5 -left-1.5 w-6 h-6 border-b-4 border-l-4 border-sky-400 rounded-bl-xl"></div>
                    <div class="absolute -bottom-1.5 -right-1.5 w-6 h-6 border-b-4 border-r-4 border-sky-400 rounded-br-xl"></div>
                </div>
            </div>

            <span class="absolute bottom-4 text-[11px] text-white/80 font-medium bg-black/40 backdrop-blur-md px-3 py-1 rounded-full">
                QR akan dipindai otomatis
            </span>
        </div>

        <!-- BOTTOM CARD INFO & BADGES -->
        <div class="bg-white rounded-3xl p-4 border border-slate-200 shadow-sm space-y-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-sky-100 text-sky-600 flex items-center justify-center text-lg shrink-0 border border-sky-200">
                    🎓
                </div>
                <div>
                    <h4 class="text-xs font-black text-slate-900">Presensi Kelas Siswa</h4>
                    <p class="text-[11px] text-slate-500 font-medium">Pindai QR Code Kelas / Sensei di dalam kelas</p>
                    <p class="text-[11px] text-sky-700 font-bold mt-1">Jam absensi: {{ $attendanceWindowLabel }}</p>
                    <p class="text-[10px] text-slate-500 font-medium mt-1">Absensi di luar waktu tersebut tidak dapat dilakukan.</p>
                </div>
            </div>

            <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px]">
                <span class="px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 font-extrabold flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                    Presensi Otomatis
                </span>
                <span class="px-2.5 py-1 rounded-full bg-sky-100 text-sky-800 font-bold flex items-center gap-1">
                    📱 QR Code Kelas
                </span>
            </div>
        </div>
    </div>

    <!-- ==================================================== -->
    <!-- STATE 2: PROCESSING SCAN                             -->
    <!-- ==================================================== -->
    <div x-show="viewState === 'PROCESSING'" class="bg-white rounded-3xl p-8 border border-slate-200 shadow-xl text-center py-16 space-y-4">
        <div class="w-16 h-16 border-4 border-sky-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
        <h3 class="text-lg font-black text-slate-900">Memverifikasi Presensi...</h3>
        <p class="text-xs text-slate-500 font-medium max-w-xs mx-auto">Sedang memeriksa enkripsi token QR, lokasi LPK, dan identitas siswa...</p>
    </div>

    <!-- ==================================================== -->
    <!-- STATE 3: PRESENSI BERHASIL! (SUCCESS SCREEN)         -->
    <!-- ==================================================== -->
    <div x-show="viewState === 'SUCCESS'" class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xl text-center space-y-5 py-8">
        <!-- Confetti / Rings surrounding green checkmark -->
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

        <!-- DETAIL PRESENSI CARD -->
        <div class="bg-slate-50 border border-slate-200/80 rounded-2xl p-4 text-xs space-y-2.5 text-left font-medium">
            <h4 class="text-[11px] font-black text-slate-800 border-b border-slate-200 pb-2">Detail Presensi</h4>
            <div class="flex justify-between border-b border-slate-200/60 pb-2">
                <span class="text-slate-500">Tanggal</span>
                <span class="font-bold text-slate-800" x-text="successData.date">—</span>
            </div>
            <div class="flex justify-between border-b border-slate-200/60 pb-2">
                <span class="text-slate-500">Waktu</span>
                <span class="font-bold text-slate-800" x-text="successData.time ? successData.time + ' WIB' : '—'">—</span>
            </div>
            <div class="flex justify-between border-b border-slate-200/60 pb-2">
                <span class="text-slate-500">Lokasi</span>
                <span class="font-bold text-slate-800">LPK Wakamiya</span>
            </div>
            <div class="flex justify-between border-b border-slate-200/60 pb-2">
                <span class="text-slate-500">Jarak</span>
                <span class="font-bold text-emerald-600" x-text="successData.distance ? successData.distance + ' meter' : '—'">—</span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-slate-500">Status</span>
                <span class="px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-extrabold text-[10px]" x-text="successData.statusLabel || '—'">—</span>
            </div>
        </div>

        <div class="space-y-2">
            <a href="{{ route('dashboard.student') }}" class="w-full py-3.5 bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-600 hover:to-blue-700 text-white font-extrabold text-xs rounded-2xl shadow-lg shadow-sky-500/30 flex items-center justify-center transition-all">
                Kembali ke Dashboard
            </a>
            <a href="{{ route('student.progress') }}" class="block text-xs font-bold text-sky-600 hover:underline">
                Lihat Riwayat
            </a>
        </div>
    </div>

    <!-- ==================================================== -->
    <!-- STATE 4: LOKASI TIDAK TERDETEKSI (GPS ERROR)         -->
    <!-- ==================================================== -->
    <div x-show="viewState === 'GEO_ERROR'" class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xl text-center space-y-5 py-8">
        <div class="w-16 h-16 bg-slate-100 text-slate-500 rounded-full flex items-center justify-center text-3xl mx-auto border border-slate-200">
            📍
        </div>
        
        <div class="space-y-1">
            <h3 class="text-lg font-black text-slate-900">Lokasi Tidak Terdeteksi</h3>
            <p class="text-xs text-slate-500 font-medium max-w-xs mx-auto">Kami tidak dapat mendeteksi lokasi perangkat Anda.</p>
        </div>

        <div class="bg-slate-50 border border-slate-200 p-4 rounded-2xl text-xs text-left space-y-2 text-slate-700 font-medium">
            <p class="flex items-center gap-2 text-emerald-700 font-bold">
                <span>✓</span> <span>Pastikan GPS aktif</span>
            </p>
            <p class="flex items-center gap-2 text-emerald-700 font-bold">
                <span>✓</span> <span>Berikan izin lokasi ke browser</span>
            </p>
            <p class="flex items-center gap-2 text-emerald-700 font-bold">
                <span>✓</span> <span>Anda berada di area terbuka</span>
            </p>
        </div>

        <div class="space-y-2">
            <button type="button" @click="requestGpsAndReset()" class="w-full py-3.5 bg-gradient-to-r from-sky-500 to-blue-600 text-white font-extrabold text-xs rounded-2xl shadow-lg shadow-sky-500/30">
                Coba Lagi
            </button>
            <a href="{{ route('dashboard.student') }}" class="block text-xs font-bold text-slate-500 hover:underline">
                Kembali
            </a>
        </div>
    </div>

    <!-- ==================================================== -->
    <!-- STATE 5: DI LUAR AREA LPK (OUTSIDE GEOFENCE)         -->
    <!-- ==================================================== -->
    <div x-show="viewState === 'OUTSIDE_GEOFENCE'" class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xl text-center space-y-5 py-8">
        <div class="w-16 h-16 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center text-3xl mx-auto border border-rose-200">
            📍
        </div>

        <div class="space-y-1">
            <h3 class="text-lg font-black text-slate-900">Di Luar Area LPK</h3>
            <p class="text-xs text-slate-500 font-medium max-w-xs mx-auto">Anda berada di luar area LPK</p>
        </div>

        <div class="bg-slate-50 border border-slate-200 p-4 rounded-2xl text-xs space-y-1">
            <p class="text-slate-500 font-medium">Jarak Anda</p>
            <p class="text-2xl font-black text-slate-900" x-text="coords.distanceText">—</p>
            <p class="text-[11px] font-bold text-sky-600 pt-1" x-text="maxRadius !== null ? 'Maksimal jarak: ' + maxRadius + ' meter' : 'Radius geofence belum dikonfigurasi'">Radius geofence belum dikonfigurasi</p>
        </div>

        <p class="text-xs text-slate-600 font-medium max-w-xs mx-auto">
            Silakan mendekati lokasi LPK untuk melakukan presensi.
        </p>

        <div class="space-y-2">
            <button type="button" @click="resetToScan()" class="w-full py-3.5 bg-gradient-to-r from-sky-500 to-blue-600 text-white font-extrabold text-xs rounded-2xl shadow-lg shadow-sky-500/30">
                Coba Lagi
            </button>
            <a href="{{ route('dashboard.student') }}" class="block text-xs font-bold text-slate-500 hover:underline">
                Kembali
            </a>
        </div>
    </div>

    <!-- ==================================================== -->
    <!-- STATE 6: KAMERA TIDAK DIIZINKAN (CAMERA ERROR)       -->
    <!-- ==================================================== -->
    <div x-show="viewState === 'CAMERA_ERROR'" class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xl text-center space-y-5 py-8">
        <div class="w-16 h-16 bg-slate-100 text-slate-500 rounded-full flex items-center justify-center text-3xl mx-auto border border-slate-200">
            📷
        </div>

        <div class="space-y-1">
            <h3 class="text-lg font-black text-slate-900">Kamera Tidak Diizinkan</h3>
            <p class="text-xs text-slate-500 font-medium">Untuk menggunakan kamera:</p>
        </div>

        <div class="bg-slate-50 border border-slate-200 p-4 rounded-2xl text-xs text-left space-y-2 text-slate-700 font-medium">
            <p>1. Buka pengaturan browser</p>
            <p>2. Izinkan akses kamera</p>
            <p>3. Kembali ke halaman ini</p>
        </div>

        <div class="space-y-2">
            <button type="button" @click="startScanner()" class="w-full py-3.5 bg-gradient-to-r from-sky-500 to-blue-600 text-white font-extrabold text-xs rounded-2xl shadow-lg shadow-sky-500/30">
                Coba Lagi
            </button>
            <a href="{{ route('dashboard.student') }}" class="block text-xs font-bold text-slate-500 hover:underline">
                Kembali
            </a>
        </div>
    </div>

    <!-- ==================================================== -->
    <!-- STATE 7: QR TIDAK SESUAI (CROSS QR ERROR)            -->
    <!-- ==================================================== -->
    <div x-show="viewState === 'CROSS_QR_ERROR'" class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xl text-center space-y-5 py-8">
        <div class="w-16 h-16 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center text-3xl mx-auto border border-rose-200">
            <svg class="w-8 h-8 text-rose-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V6a2 2 0 012-2h2M4 16v2a2 2 0 002 2h2M16 4h2a2 2 0 012 2v2M16 20h2a2 2 0 002-2v-2" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" stroke-width="2.5" />
            </svg>
        </div>

        <div class="space-y-1">
            <h3 class="text-lg font-black text-slate-900">QR Tidak Sesuai</h3>
            <p class="text-xs text-slate-500 font-medium max-w-xs mx-auto">
                QR Code ini tidak sesuai. Pastikan Anda menggunakan QR Code yang benar. QR ini bukan untuk presensi Anda.
            </p>
        </div>

        <button type="button" @click="resetToScan()" class="w-full py-3.5 bg-gradient-to-r from-sky-500 to-blue-600 text-white font-extrabold text-xs rounded-2xl shadow-lg shadow-sky-500/30">
            OK
        </button>
    </div>

    <!-- ==================================================== -->
    <!-- STATE 8: EXPIRED / DUPLICATE GENERAL ERROR STATE     -->
    <!-- ==================================================== -->
    <div x-show="viewState === 'GENERAL_ERROR'" class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xl text-center space-y-5 py-8">
        <div class="w-16 h-16 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center text-3xl mx-auto border border-amber-200">
            ⚠️
        </div>

        <div class="space-y-1">
            <h3 class="text-lg font-black text-slate-900">Presensi Ditolak</h3>
            <p class="text-xs text-rose-600 font-bold max-w-xs mx-auto" x-text="errorMessage"></p>
        </div>

        <div class="space-y-2">
            <button type="button" @click="resetToScan()" class="w-full py-3.5 bg-gradient-to-r from-sky-500 to-blue-600 text-white font-extrabold text-xs rounded-2xl shadow-lg shadow-sky-500/30">
                Coba Lagi
            </button>
            <a href="{{ route('dashboard.student') }}" class="block text-xs font-bold text-slate-500 hover:underline">
                Kembali
            </a>
        </div>
    </div>

</div>

<!-- HTML5 QR CODE SCANNER LIBRARY -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<style>
    #student-qr-reader {
        width: 100% !important;
        height: 100% !important;
        border: none !important;
        background: #000 !important;
        overflow: hidden !important;
    }
    #student-qr-reader video {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
        border-radius: 1.5rem !important;
    }
    #student-qr-reader canvas {
        display: none !important;
    }
    #student-qr-reader__scan_region {
        background: transparent !important;
    }
    #student-qr-reader__dashboard {
        display: none !important;
    }
</style>

@php
    $lpkLat = \App\Support\CoordinateNormalizer::parse($settingService->get('LPK_LATITUDE', null), -90, 90);
    $lpkLon = \App\Support\CoordinateNormalizer::parse($settingService->get('LPK_LONGITUDE', null), -180, 180);
    $maxRadius = $settingService->get('LPK_ALLOWED_RADIUS_METERS', null);
@endphp

<script>
    function studentQrScannerEngine() {
        return {
            viewState: 'SCANNING', // SCANNING, PROCESSING, SUCCESS, GEO_ERROR, OUTSIDE_GEOFENCE, CAMERA_ERROR, CROSS_QR_ERROR, GENERAL_ERROR
            errorMessage: '',
            coords: { lat: null, lon: null, distanceText: 'Menunggu GPS...' },
            successData: { date: '', time: '', distance: '', statusLabel: '', message: '' },
            html5QrCode: null,
            lastScannedToken: null,
            lpkLat: @json($lpkLat),
            lpkLon: @json($lpkLon),
            maxRadius: @json(is_numeric($maxRadius) ? (float) $maxRadius : null),

            init() {
                this.requestGpsAndReset();
                this.startScanner();
            },

            requestGpsAndReset() {
                if ("geolocation" in navigator) {
                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            this.coords.lat = position.coords.latitude;
                            this.coords.lon = position.coords.longitude;
                            if (this.lpkLat === null || this.lpkLon === null) {
                                this.coords.distanceText = 'Lokasi belum dikonfigurasi';
                                return;
                            }
                            const d = this.haversine(this.coords.lat, this.coords.lon, this.lpkLat, this.lpkLon);
                            this.coords.distanceText = d.toFixed(1).replace('.', ',') + ' meter';
                            if (this.viewState === 'GEO_ERROR') {
                                this.viewState = 'SCANNING';
                            }
                        },
                        (error) => {
                            this.coords.distanceText = 'Menunggu GPS...';
                            if (error.code === error.PERMISSION_DENIED) {
                                this.viewState = 'GEO_ERROR';
                            }
                        },
                        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                    );
                } else {
                    this.viewState = 'GEO_ERROR';
                }
            },

            async startScanner() {
                this.$nextTick(async () => {
                    if (typeof Html5Qrcode === 'undefined') {
                        setTimeout(() => this.startScanner(), 300);
                        return;
                    }

                    if (this.html5QrCode) {
                        try { await this.html5QrCode.stop(); } catch (e) {}
                    }

                    this.html5QrCode = new Html5Qrcode("student-qr-reader");
                    const config = {
                        fps: 15,
                        qrbox: (viewfinderWidth, viewfinderHeight) => {
                            const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
                            const qrboxSize = Math.floor(minEdge * 0.75);
                            return { width: qrboxSize, height: qrboxSize };
                        },
                        aspectRatio: 1.0
                    };

                    const onScanSuccess = (decodedText) => this.onQrScanned(decodedText);
                    const onScanError = () => {};

                    const fixVideoIOS = () => {
                        setTimeout(() => {
                            const videoElem = document.querySelector('#student-qr-reader video');
                            if (videoElem) {
                                videoElem.setAttribute('playsinline', 'true');
                                videoElem.setAttribute('webkit-playsinline', 'true');
                                videoElem.setAttribute('muted', 'true');
                                videoElem.style.objectFit = 'cover';
                                videoElem.style.width = '100%';
                                videoElem.style.height = '100%';
                                videoElem.play().catch(() => {});
                            }
                        }, 200);
                    };

                    try {
                        await this.html5QrCode.start({ facingMode: "environment" }, config, onScanSuccess, onScanError);
                        fixVideoIOS();
                        this.viewState = 'SCANNING';
                    } catch (err1) {
                        try {
                            const devices = await Html5Qrcode.getCameras();
                            if (devices && devices.length > 0) {
                                const backCam = devices.find(d => {
                                    const l = (d.label || '').toLowerCase();
                                    return l.includes('back') || l.includes('rear') || l.includes('environment') || l.includes('belakang');
                                });
                                const camId = backCam ? backCam.id : devices[0].id;
                                await this.html5QrCode.start(camId, config, onScanSuccess, onScanError);
                                fixVideoIOS();
                                this.viewState = 'SCANNING';
                            } else {
                                throw new Error('Kamera tidak ditemukan.');
                            }
                        } catch (err2) {
                            try {
                                await this.html5QrCode.start({ facingMode: "user" }, config, onScanSuccess, onScanError);
                                fixVideoIOS();
                                this.viewState = 'SCANNING';
                            } catch (err3) {
                                this.viewState = 'CAMERA_ERROR';
                            }
                        }
                    }
                });
            },

            onQrScanned(token) {
                if (this.viewState === 'PROCESSING' || this.lastScannedToken === token) return;
                
                if (this.coords.lat == null || this.coords.lon == null) {
                    this.requestGpsAndReset();
                    this.viewState = 'GEO_ERROR';
                    this.lastScannedToken = null;
                    if (this.html5QrCode) {
                        this.html5QrCode.resume();
                    }
                    return;
                }

                this.lastScannedToken = token;
                this.viewState = 'PROCESSING';

                if (this.html5QrCode) {
                    this.html5QrCode.pause(true);
                }

                fetch("{{ route('attendances.student.scan') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        token: token,
                        latitude: this.coords.lat,
                        longitude: this.coords.lon,
                        device_info: navigator.userAgent
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const now = new Date();
                        const isCheckOut = data.data.action === 'CHECK_OUT';
                        this.successData = {
                            date: now.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }),
                            time: (isCheckOut ? data.data.check_out_time : data.data.check_in_time) || now.toLocaleTimeString('id-ID'),
                            distance: (data.data.distance_meters || this.haversine(this.coords.lat, this.coords.lon, this.lpkLat, this.lpkLon)).toFixed(1).replace('.', ','),
                            statusLabel: isCheckOut ? 'KELUAR' : 'HADIR',
                            message: data.message
                        };
                        this.viewState = 'SUCCESS';
                    } else {
                        const msg = data.message || 'Gagal memproses presensi.';
                        this.errorMessage = msg;
                        if (msg.includes('bukan QR Absensi Siswa') || msg.includes('khusus untuk Presensi Pegawai')) {
                            this.viewState = 'CROSS_QR_ERROR';
                        } else if (msg.includes('di luar area LPK')) {
                            this.viewState = 'OUTSIDE_GEOFENCE';
                        } else {
                            this.viewState = 'GENERAL_ERROR';
                        }
                    }
                })
                .catch(() => {
                    this.errorMessage = 'Terjadi kesalahan koneksi server.';
                    this.viewState = 'GENERAL_ERROR';
                });
            },

            resetToScan() {
                this.viewState = 'SCANNING';
                this.lastScannedToken = null;
                if (this.html5QrCode) {
                    this.html5QrCode.resume();
                }
            },

            haversine(lat1, lon1, lat2, lon2) {
                const R = 6371000;
                const dLat = (lat2 - lat1) * Math.PI / 180;
                const dLon = (lon2 - lon1) * Math.PI / 180;
                const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                          Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                          Math.sin(dLon/2) * Math.sin(dLon/2);
                const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
                return R * c;
            }
        }
    }
</script>
@endsection
