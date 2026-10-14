@extends('layouts.app')
@section('header', 'Pengaturan Sistem')
@section('content')
<div class="space-y-6 max-w-7xl mx-auto" x-data="systemSettingsHub()">
    <x-page-header title="Pengaturan Sistem" description="Pusat konfigurasi master dan control center untuk {{ $brand['application']['name'] }}." :breadcrumbs="['Dasbor' => route('dashboard.administrator'), 'Pengaturan' => '#']" />

    @if(session('success'))
        <div class="bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-xl p-4 text-sm font-bold">
            {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="bg-rose-50 text-rose-700 border border-rose-200 rounded-xl p-4 text-sm font-bold">
            {{ $errors->first() }}
        </div>
    @endif

    <!-- Action Bar -->
    <div class="bg-slate-900 text-white rounded-2xl p-4 md:p-5 border border-slate-800 shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-sky-500/20 text-sky-400 border border-sky-500/30 flex items-center justify-center font-black text-lg shrink-0">⚙️</div>
            <div>
                <h3 class="font-bold text-sm text-white">{{ $brand['application']['name'] }} Control Center</h3>
                <p class="text-xs text-slate-400">Konfigurasi tingkat sistem. Pengaturan operasional tersedia di modul masing-masing.</p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
            <form action="{{ route('settings.clear_cache') }}" method="POST" onsubmit="return confirm('Bersihkan cache pengaturan sistem?')">
                @csrf
                <button type="submit" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs rounded-xl border border-slate-700 transition-all flex items-center gap-1.5 min-h-[44px]">
                    ⚡ <span>Clear Cache</span>
                </button>
            </form>
            @if($activeTab === 'Branding')
                <form action="{{ route('settings.reset_branding') }}" method="POST" onsubmit="return confirm('Atur ulang warna ke palet default instance?')">
                    @csrf
                    <button type="submit" class="px-4 py-2.5 bg-sky-900/50 hover:bg-sky-800/80 text-sky-300 font-bold text-xs rounded-xl border border-sky-700/50 transition-all flex items-center gap-1.5 min-h-[44px]">
                        🔄 <span>Atur Ulang Merek</span>
                    </button>
                </form>
            @endif
        </div>
    </div>

    <div class="flex flex-col lg:flex-row gap-6">
        <!-- Desktop Sidebar / Mobile Category Cards -->
        <div class="w-full lg:w-64 shrink-0">
            <!-- Mobile: Category Cards -->
            <div class="grid grid-cols-3 gap-2 lg:hidden">
                @php
                    $categoryLabels = [
                        'General' => 'Umum',
                        'Branding' => 'Warna & Tampilan',
                        'Company' => 'Perusahaan',
                        'Company_Document' => 'Dokumen & TTD',
                        'Notification' => 'Notifikasi',
                        'Email_Delivery' => 'Email',
                        'Security' => 'Keamanan',
                        'Workflow' => 'Alur Kerja',
                        'System' => 'Sistem',
                    ];
                    $categoryIcons = [
                        'General' => '⚙️', 'Branding' => '🎨', 'Company' => '🏢',
                        'Company_Document' => '✒️', 'Notification' => '🔔',
                        'Email_Delivery' => '✉️', 'Security' => '🛡️',
                        'Workflow' => '🔄', 'System' => '💻',
                    ];
                @endphp
                @foreach($categories as $cat)
                    <a href="{{ route('settings.index', ['tab' => $cat]) }}"
                       class="p-3 rounded-xl text-center transition-all min-h-[44px] {{ $activeTab == $cat ? 'bg-slate-900 text-sky-400 border-2 border-sky-500' : 'bg-white text-slate-600 border border-slate-200 hover:border-sky-300' }}">
                        <span class="text-xl block">{{ $categoryIcons[$cat] ?? '📁' }}</span>
                        <span class="text-[10px] font-bold block mt-1 leading-tight">{{ $categoryLabels[$cat] ?? $cat }}</span>
                    </a>
                @endforeach
            </div>

            <!-- Desktop: Sticky Sidebar -->
            <div class="hidden lg:block bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden sticky top-6">
                <div class="p-4 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="font-bold text-slate-800 text-xs tracking-wider uppercase">Kategori Sistem</h3>
                    <span class="px-2 py-0.5 bg-sky-100 text-sky-700 font-bold text-[10px] rounded-full">{{ count($categories) }}</span>
                </div>
                <div class="flex flex-col p-2 space-y-1">
                    @foreach($categories as $cat)
                        <a href="{{ route('settings.index', ['tab' => $cat]) }}"
                           class="px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center justify-between {{ $activeTab == $cat ? 'bg-slate-900 text-sky-400 shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                            <div class="flex items-center gap-2.5">
                                <span class="text-sm">{{ $categoryIcons[$cat] ?? '📁' }}</span>
                                <span>{{ $categoryLabels[$cat] ?? $cat }}</span>
                            </div>
                            @if($activeTab == $cat)
                                <span class="w-1.5 h-1.5 rounded-full bg-sky-400 animate-pulse"></span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Configuration Form Body -->
        <div class="flex-grow min-w-0">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-4 md:p-6 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">{{ $categoryIcons[$activeTab] ?? '⚙️' }}</span>
                        <h2 class="text-lg md:text-xl font-bold text-slate-800">Konfigurasi {{ $categoryLabels[$activeTab] ?? $activeTab }}</h2>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">Kelola parameter yang mengatur {{ strtolower($categoryLabels[$activeTab] ?? $activeTab) }}.</p>
                </div>

                @if($activeTab === 'Email_Delivery')
                    @php
                        $emailConfig = $emailConfig ?? app(\App\Services\Core\SystemSettingService::class)->getEmailDeliveryConfig();
                        $isConnected = ($emailConfig['status'] === 'connected');
                        $providerUpper = strtoupper($emailConfig['provider'] ?? 'NONE');
                    @endphp

                    <div class="p-4 md:p-6 space-y-6" x-data="{ openSmtpModal: false, openSenderModal: false, openGoogleChooserModal: false, testing: false, testMsg: '', testSuccess: true }">

                        <!-- 6-STEP CONNECTION PROGRESS WIZARD INDICATOR -->
                        <div class="p-4 bg-slate-900 text-white rounded-2xl border border-slate-800 shadow-md">
                            <div class="flex items-center justify-between overflow-x-auto text-[11px] font-bold text-slate-400 min-w-[600px] gap-2 pb-1">
                                <span class="flex items-center gap-1.5 {{ $isConnected ? 'text-emerald-400' : 'text-sky-400' }}">
                                    <span class="w-5 h-5 rounded-full {{ $isConnected ? 'bg-emerald-500' : 'bg-sky-500' }} text-slate-950 flex items-center justify-center font-black text-[10px]">1</span> Provider
                                </span>
                                <span class="text-slate-600">→</span>
                                <span class="flex items-center gap-1.5 {{ $isConnected ? 'text-emerald-400' : 'text-slate-300' }}">
                                    <span class="w-5 h-5 rounded-full {{ $isConnected ? 'bg-emerald-500 text-slate-950' : 'bg-slate-800 text-slate-400' }} flex items-center justify-center font-black text-[10px]">2</span> Authorization
                                </span>
                                <span class="text-slate-600">→</span>
                                <span class="flex items-center gap-1.5 {{ $isConnected ? 'text-emerald-400' : 'text-slate-300' }}">
                                    <span class="w-5 h-5 rounded-full {{ $isConnected ? 'bg-emerald-500 text-slate-950' : 'bg-slate-800 text-slate-400' }} flex items-center justify-center font-black text-[10px]">3</span> Verification
                                </span>
                                <span class="text-slate-600">→</span>
                                <span class="flex items-center gap-1.5 {{ $isConnected ? 'text-emerald-400' : 'text-slate-300' }}">
                                    <span class="w-5 h-5 rounded-full {{ $isConnected ? 'bg-emerald-500 text-slate-950' : 'bg-slate-800 text-slate-400' }} flex items-center justify-center font-black text-[10px]">4</span> Sender
                                </span>
                                <span class="text-slate-600">→</span>
                                <span class="flex items-center gap-1.5 {{ $isConnected ? 'text-emerald-400' : 'text-slate-300' }}">
                                    <span class="w-5 h-5 rounded-full {{ $isConnected ? 'bg-emerald-500 text-slate-950' : 'bg-slate-800 text-slate-400' }} flex items-center justify-center font-black text-[10px]">5</span> Test
                                </span>
                                <span class="text-slate-600">→</span>
                                <span class="flex items-center gap-1.5 {{ $isConnected ? 'text-emerald-400' : 'text-slate-300' }}">
                                    <span class="w-5 h-5 rounded-full {{ $isConnected ? 'bg-emerald-500 text-slate-950' : 'bg-slate-800 text-slate-400' }} flex items-center justify-center font-black text-[10px]">6</span> Connected
                                </span>
                            </div>
                        </div>

                        @if(Session::has('oauth_pending_preview'))
                            @php $pending = Session::get('oauth_pending_preview'); @endphp
                            <!-- ACCOUNT PREVIEW VERIFICATION CARD (EPS REV 4.1) -->
                            <div class="bg-slate-900 border-2 border-sky-500/60 rounded-3xl p-6 shadow-2xl max-w-md mx-auto text-white space-y-5">
                                <div class="text-center space-y-3">
                                    <div class="w-16 h-16 rounded-full bg-rose-500/10 border-2 border-rose-500/30 text-rose-500 flex items-center justify-center text-3xl font-black mx-auto shadow-inner">
                                        {{ strtolower($pending['provider'] ?? '') === 'google' ? 'G' : '⊞' }}
                                    </div>
                                    <div>
                                        <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-sky-500/20 text-sky-400 border border-sky-500/30">
                                            {{ $pending['provider_name'] ?? 'Google Workspace' }}
                                        </span>
                                        <h3 class="text-lg font-black mt-2 text-white">{{ $brand['application']['name'] }}</h3>
                                        <p class="text-sm font-mono font-bold text-sky-400 mt-1">{{ $pending['account'] }}</p>
                                    </div>
                                    <div class="p-3 bg-emerald-950/70 border border-emerald-500/50 rounded-xl text-emerald-400 text-xs font-bold flex items-center justify-center gap-2">
                                        <span>✓</span> <span>Akun berhasil diverifikasi</span>
                                    </div>
                                </div>

                                <form action="{{ route('settings.email.confirm') }}" method="POST" class="space-y-2.5">
                                    @csrf
                                    <button type="submit" class="w-full py-3.5 px-4 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black rounded-xl text-xs shadow-lg transition-all flex items-center justify-center gap-2 min-h-[46px] cursor-pointer">
                                        <span>Lanjutkan</span> →
                                    </button>

                                    <div class="flex items-center justify-between text-xs pt-1">
                                        <button type="button" @click="openGoogleChooserModal = true" class="font-extrabold text-sky-400 hover:text-sky-300 hover:underline bg-transparent border-none p-0 cursor-pointer">
                                            🔄 Hubungkan Akun Lain
                                        </button>
                                        <a href="{{ route('settings.email.cancel') }}" class="font-semibold text-slate-400 hover:text-white">
                                            ✕ Batal
                                        </a>
                                    </div>
                                </form>
                            </div>
                        @elseif($emailConfig['status'] === 'reauth_required')
                            <!-- RE-AUTHENTICATION REQUIRED STATE -->
                            <div class="bg-amber-950/90 text-amber-100 border-2 border-amber-500/40 rounded-2xl p-5 md:p-6 shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-5">
                                <div class="flex items-center gap-4">
                                    <div class="w-14 h-14 rounded-2xl bg-amber-500/20 border border-amber-400/30 text-amber-400 flex items-center justify-center text-3xl font-black shrink-0">
                                        🟡
                                    </div>
                                    <div class="space-y-1">
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-500 text-slate-950">🟡 KONEKSI PERLU OTORISASI ULANG</span>
                                        <h3 class="text-lg font-black text-white">Google Workspace ({{ $emailConfig['connected_account'] }})</h3>
                                        <p class="text-xs text-amber-300">Sesi otorisasi telah berakhir atau token memerlukan pembaruan.</p>
                                    </div>
                                </div>
                                <form action="{{ route('settings.email.reconnect') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="px-5 py-3 bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs rounded-xl shadow-lg transition-all min-h-[44px]">
                                        <span>Hubungkan Ulang</span>
                                    </button>
                                </form>
                            </div>
                        @elseif($isConnected)
                            <!-- STATE 1: CONNECTED DASHBOARD STATE -->
                            <div class="bg-emerald-950/90 text-emerald-100 border-2 border-emerald-500/40 rounded-2xl p-5 md:p-6 shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-5">
                                <div class="flex items-center gap-4">
                                    <div class="w-14 h-14 rounded-2xl bg-emerald-500/20 border border-emerald-400/30 text-emerald-400 flex items-center justify-center text-3xl font-black shrink-0 shadow-inner">
                                        🟢
                                    </div>
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-500 text-slate-950 shadow-xs">🟢 EMAIL TERHUBUNG</span>
                                            <span class="text-xs font-bold text-emerald-400">🟢 Connection Healthy</span>
                                        </div>
                                        <h3 class="text-lg md:text-xl font-black text-white">
                                            {{ $providerUpper === 'GOOGLE' ? 'Google Workspace' : ($providerUpper === 'MICROSOFT' ? 'Microsoft 365 / Outlook' : 'Email Perusahaan (SMTP Custom)') }}
                                        </h3>
                                        <p class="text-xs text-emerald-300/90 font-mono font-semibold">{{ $emailConfig['connected_account'] }}</p>
                                        <p class="text-[11px] text-emerald-400/70 font-medium">Terhubung sejak: {{ $emailConfig['connected_at'] ?? '17 Agustus 2026' }} • Status: Beroperasi</p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 flex-wrap w-full md:w-auto">
                                    <button type="button" @click="openSenderModal = true" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs rounded-xl border border-slate-700 transition-all flex items-center gap-2 min-h-[44px]">
                                        ⚙️ <span>Ubah Pengaturan Pengirim</span>
                                    </button>

                                    @if($providerUpper === 'GOOGLE')
                                        <button type="button" @click="openGoogleChooserModal = true" class="px-4 py-2.5 bg-sky-500/20 hover:bg-sky-500 text-sky-300 hover:text-slate-950 font-bold text-xs rounded-xl border border-sky-500/30 transition-all flex items-center gap-2 min-h-[44px]">
                                            🔄 <span>Reconnect / Ganti Akun</span>
                                        </button>
                                    @else
                                        <form action="{{ route('settings.email.reconnect') }}" method="POST">
                                            @csrf
                                            <button type="submit" class="px-4 py-2.5 bg-sky-500/20 hover:bg-sky-500 text-sky-300 hover:text-slate-950 font-bold text-xs rounded-xl border border-sky-500/30 transition-all flex items-center gap-2 min-h-[44px]">
                                                🔄 <span>Reconnect</span>
                                            </button>
                                        </form>
                                    @endif

                                    <form action="{{ route('settings.email.disconnect') }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin memutuskan koneksi email ini? Seluruh pengiriman otomatis akan beralih ke mode bawaan.')">
                                        @csrf
                                        <button type="submit" class="px-4 py-2.5 bg-rose-500/20 hover:bg-rose-500 text-rose-300 hover:text-white font-bold text-xs rounded-xl border border-rose-500/30 transition-all flex items-center gap-2 min-h-[44px]">
                                            🔌 <span>Putuskan Koneksi</span>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <!-- CONNECTED SUB-CARDS -->
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                                <!-- SENDER CONFIGURATION & PREVIEW CARD -->
                                <div class="bg-slate-900 text-white rounded-2xl p-5 md:p-6 border border-slate-800 space-y-4 shadow-md">
                                    <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                                        <h4 class="text-xs font-black uppercase tracking-widest text-sky-400">📧 Sender Configuration</h4>
                                        <button type="button" @click="openSenderModal = true" class="text-xs font-bold text-sky-400 hover:underline">Edit</button>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                                        <div class="bg-slate-950 p-3 rounded-xl border border-slate-800">
                                            <span class="text-slate-500 block font-semibold text-[10px] uppercase">From Name</span>
                                            <span class="text-white font-extrabold text-sm block mt-0.5">{{ $emailConfig['from_name'] }}</span>
                                        </div>
                                        <div class="bg-slate-950 p-3 rounded-xl border border-slate-800">
                                            <span class="text-slate-500 block font-semibold text-[10px] uppercase">From Email</span>
                                            <span class="text-sky-400 font-mono font-bold block mt-0.5 truncate">{{ $emailConfig['from_address'] }}</span>
                                        </div>
                                    </div>

                                    <!-- Email Header Live Preview Box -->
                                    <div class="p-4 rounded-xl bg-slate-950 border border-slate-800 space-y-2">
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Pratinjau Header Email Penerima</p>
                                        <div class="p-3.5 bg-slate-900 rounded-xl border border-slate-800 text-xs font-mono space-y-1">
                                            <p class="text-white font-extrabold">{{ $emailConfig['from_name'] }}</p>
                                            <p class="text-slate-400">From: {{ $emailConfig['from_address'] }}</p>
                                            <p class="text-slate-400">Reply-To: {{ $emailConfig['reply_to'] }}</p>
                                            <hr class="my-2 border-slate-800">
                                            <p class="text-emerald-400 font-sans font-semibold text-[11px] leading-relaxed">
                                                ✓ Sistem email WMS terhubung dan aktif untuk seluruh pengiriman dokumen Invoice, Kwitansi, Laporan Akademik, Notifikasi Presensi H8.22, dan Payroll.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- TEST EMAIL CARD -->
                                <div class="bg-slate-900 text-white rounded-2xl p-5 md:p-6 border border-slate-800 space-y-4 shadow-md">
                                    <div class="border-b border-slate-800 pb-3">
                                        <h4 class="text-xs font-black uppercase tracking-widest text-sky-400">✈️ Tes Pengiriman Email Percobaan</h4>
                                        <p class="text-xs text-slate-400 mt-0.5">Kirim email percobaan untuk memastikan pesan sampai di kotak masuk.</p>
                                    </div>

                                    <form action="{{ route('settings.email.test') }}" method="POST" @submit.prevent="
                                        testing = true;
                                        testMsg = '';
                                        fetch('{{ route('settings.email.test') }}', {
                                            method: 'POST',
                                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                                            body: JSON.stringify({ recipient_email: $refs.testRecipient.value })
                                        })
                                        .then(r => r.json())
                                        .then(d => {
                                            testing = false;
                                            testSuccess = d.success;
                                            testMsg = d.message;
                                        })
                                        .catch(e => {
                                            testing = false;
                                            testSuccess = false;
                                            testMsg = 'Gagal melakukan tes pengiriman email.';
                                        })
                                    " class="space-y-4">
                                        @csrf
                                        <div>
                                            <label class="block text-xs font-bold text-slate-300 mb-1.5">Alamat Email Tujuan</label>
                                            <input type="email" x-ref="testRecipient" required value="{{ auth()->user()->email ?? $emailConfig['from_address'] }}" placeholder="contoh@gmail.com" class="w-full text-xs bg-slate-950 border-2 border-slate-700 focus:border-sky-400 rounded-xl text-white p-3 font-semibold min-h-[46px] outline-none">
                                        </div>

                                        <button type="submit" :disabled="testing" class="w-full py-3 px-4 bg-sky-500 hover:bg-sky-400 text-slate-950 font-black rounded-xl text-xs shadow-lg transition-all flex items-center justify-center gap-2 min-h-[46px] disabled:opacity-50 cursor-pointer">
                                            <span x-show="!testing">✈️ Kirim Email Percobaan</span>
                                            <span x-show="testing" x-cloak class="flex items-center gap-2">
                                                <svg class="animate-spin h-4 w-4 text-slate-950" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                Sending...
                                            </span>
                                        </button>

                                        <div x-show="testMsg" x-cloak :class="testSuccess ? 'bg-emerald-950/90 border-emerald-600 text-emerald-300' : 'bg-rose-950/90 border-rose-600 text-rose-300'" class="p-3.5 rounded-xl border text-xs font-bold transition-all">
                                            <span x-text="testMsg"></span>
                                        </div>
                                    </form>
                                </div>

                            </div>
                        @else
                            <!-- STATE 2: CONNECTION CENTER (SELECT PROVIDER) -->
                            <div class="space-y-4">
                                <div>
                                    <h3 class="text-base md:text-lg font-black text-slate-900">Hubungkan Akun Email Perusahaan</h3>
                                    <p class="text-xs text-slate-500 mt-0.5">Pilih provider email untuk mengautentikasi pengiriman otomatis WMS tanpa menyunting file konfigurasi teknis.</p>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                                    <!-- 1. GOOGLE WORKSPACE / GMAIL -->
                                    <div class="bg-slate-900 border-2 border-slate-800 hover:border-rose-500/50 rounded-2xl p-6 transition-all flex flex-col justify-between space-y-6 shadow-lg group">
                                        <div class="space-y-4">
                                            <div class="w-14 h-14 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-500 flex items-center justify-center text-3xl font-black group-hover:scale-105 transition-transform">
                                                G
                                            </div>
                                            <div>
                                                <h4 class="text-lg font-black text-white">Google Workspace</h4>
                                                <p class="text-xs text-slate-400 mt-1 leading-relaxed">Gmail / Google Workspace resmi perusahaan. Autentikasi OAuth 2.0 tanpa memerlukan password akun.</p>
                                            </div>
                                        </div>
                                        <a href="{{ route('settings.email.connect', ['provider' => 'google']) }}" class="w-full py-3 px-4 bg-white hover:bg-slate-100 text-slate-950 font-extrabold rounded-xl text-xs flex items-center justify-center gap-2.5 shadow-md transition-all min-h-[46px] cursor-pointer">
                                            <span class="text-base font-black text-rose-600">G</span> <span>Hubungkan dengan Google</span>
                                        </a>
                                    </div>

                                    <!-- 2. MICROSOFT 365 / OUTLOOK -->
                                    <div class="bg-slate-900 border-2 border-slate-800 hover:border-blue-500/50 rounded-2xl p-6 transition-all flex flex-col justify-between space-y-6 shadow-lg group">
                                        <div class="space-y-4">
                                            <div class="w-14 h-14 rounded-2xl bg-blue-500/10 border border-blue-500/30 text-blue-400 flex items-center justify-center text-3xl font-black group-hover:scale-105 transition-transform">
                                                ⊞
                                            </div>
                                            <div>
                                                <h4 class="text-lg font-black text-white">Microsoft 365</h4>
                                                <p class="text-xs text-slate-400 mt-1 leading-relaxed">Outlook / Microsoft 365 Enterprise. Autentikasi OAuth Microsoft 365 yang aman & langsung terintegrasi.</p>
                                            </div>
                                        </div>
                                        <a href="{{ route('settings.email.connect', ['provider' => 'microsoft']) }}" class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-500 text-white font-extrabold rounded-xl text-xs flex items-center justify-center gap-2.5 shadow-md transition-all min-h-[46px]">
                                            <span class="text-base font-black">⊞</span> <span>Hubungkan dengan Microsoft</span>
                                        </a>
                                    </div>

                                    <!-- 3. CUSTOM SMTP SERVER -->
                                    <div class="bg-slate-900 border-2 border-slate-800 hover:border-emerald-500/50 rounded-2xl p-6 transition-all flex flex-col justify-between space-y-6 shadow-lg group">
                                        <div class="space-y-4">
                                            <div class="w-14 h-14 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 flex items-center justify-center text-3xl font-black group-hover:scale-105 transition-transform">
                                                ✉
                                            </div>
                                            <div>
                                                <h4 class="text-lg font-black text-white">Email Perusahaan</h4>
                                                <p class="text-xs text-slate-400 mt-1 leading-relaxed">Server SMTP Custom (cPanel, Postfix, Amazon SES, SendGrid, Mailgun). Password tersimpan tersandi aman.</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="openSmtpModal = true" class="w-full py-3 px-4 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold rounded-xl text-xs flex items-center justify-center gap-2.5 shadow-md transition-all min-h-[46px] cursor-pointer">
                                            <span class="text-base font-black">✉</span> <span>Hubungkan SMTP</span>
                                        </button>
                                    </div>

                                </div>
                            </div>
                        @endif

                        <!-- GOOGLE WORKSPACE ACCOUNT SELECTION MODAL (EPS REV 4.1) -->
                        <div x-show="openGoogleChooserModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
                            <div @click.away="openGoogleChooserModal = false" class="bg-slate-900 border-2 border-slate-700 rounded-3xl p-6 max-w-md w-full text-white shadow-2xl space-y-5">
                                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-rose-500/20 text-rose-500 border border-rose-500/30 flex items-center justify-center font-black text-xl">
                                            G
                                        </div>
                                        <div>
                                            <h3 class="text-base font-black">Hubungkan Akun Google</h3>
                                            <p class="text-[11px] text-slate-400">Pemilihan akun dan persetujuan akses dilakukan langsung di Google.</p>
                                        </div>
                                    </div>
                                    <button type="button" @click="openGoogleChooserModal = false" class="text-slate-400 hover:text-white font-black text-lg">✕</button>
                                </div>

                                <form action="{{ route('settings.email.connect', ['provider' => 'google']) }}" method="GET" class="space-y-4 text-xs">
                                    <div class="space-y-2 pt-1">
                                        <button type="submit" class="w-full py-3.5 px-4 bg-rose-600 hover:bg-rose-500 text-white font-black rounded-xl text-xs shadow-lg transition-all flex items-center justify-center gap-2 min-h-[46px] cursor-pointer">
                                            <span class="text-base font-black">G</span> <span>Lanjutkan ke Google</span>
                                        </button>
                                        <button type="button" @click="openGoogleChooserModal = false" class="w-full py-2 text-center text-xs font-semibold text-slate-400 hover:text-white cursor-pointer">
                                            Batal
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- CUSTOM SMTP MODAL WIZARD -->
                        <div x-show="openSmtpModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
                            <div @click.away="openSmtpModal = false" class="bg-slate-900 border-2 border-slate-700 rounded-3xl p-6 max-w-lg w-full text-white shadow-2xl space-y-5">
                                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                                    <div class="flex items-center gap-2.5">
                                        <span class="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 flex items-center justify-center font-black text-base">✉</span>
                                        <h3 class="text-base font-black">Hubungkan Server SMTP Custom</h3>
                                    </div>
                                    <button type="button" @click="openSmtpModal = false" class="text-slate-400 hover:text-white font-black text-lg">✕</button>
                                </div>

                                <form action="{{ route('settings.email.smtp_connect') }}" method="POST" class="space-y-4 text-xs">
                                    @csrf
                                    <div>
                                        <label class="block font-bold text-slate-300 mb-1">SMTP Host</label>
                                        <input type="text" name="host" required placeholder="smtp.domain.com" class="w-full bg-slate-950 border-2 border-slate-700 focus:border-emerald-400 rounded-xl text-white p-3 font-mono font-semibold min-h-[46px] outline-none">
                                    </div>

                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block font-bold text-slate-300 mb-1">SMTP Port</label>
                                            <input type="number" name="port" required value="587" class="w-full bg-slate-950 border-2 border-slate-700 focus:border-emerald-400 rounded-xl text-white p-3 font-mono font-semibold min-h-[46px] outline-none">
                                        </div>
                                        <div>
                                            <label class="block font-bold text-slate-300 mb-1">Enkripsi</label>
                                            <select name="encryption" class="w-full bg-slate-950 border-2 border-slate-700 focus:border-emerald-400 rounded-xl text-white p-3 font-semibold min-h-[46px] outline-none cursor-pointer">
                                                <option value="tls">TLS (Default)</option>
                                                <option value="starttls">STARTTLS</option>
                                                <option value="ssl">SSL (Port 465)</option>
                                                <option value="none">Tanpa Enkripsi</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block font-bold text-slate-300 mb-1">Username / Email Pengirim</label>
                                        <input type="text" name="username" required placeholder="admin@domain.com" class="w-full bg-slate-950 border-2 border-slate-700 focus:border-emerald-400 rounded-xl text-white p-3 font-semibold min-h-[46px] outline-none">
                                    </div>

                                    <div>
                                        <label class="block font-bold text-slate-300 mb-1">Password / App Password (Tersandi Aman)</label>
                                        <input type="password" name="password" required placeholder="••••••••••••" class="w-full bg-slate-950 border-2 border-slate-700 focus:border-emerald-400 rounded-xl text-white p-3 font-semibold min-h-[46px] outline-none">
                                    </div>

                                    <div>
                                        <label class="block font-bold text-slate-300 mb-1">Nama Pengirim (From Name)</label>
                                        <input type="text" name="sender_name" required value="{{ $brand['application']['name'] }}" class="w-full bg-slate-950 border-2 border-slate-700 focus:border-emerald-400 rounded-xl text-white p-3 font-semibold min-h-[46px] outline-none">
                                    </div>

                                    <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-800">
                                        <button type="button" @click="openSmtpModal = false" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold rounded-xl text-xs min-h-[44px]">Batal</button>
                                        <button type="submit" class="px-5 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black rounded-xl text-xs shadow-lg transition-all min-h-[44px] cursor-pointer">
                                            ✉ <span>Test & Hubungkan SMTP</span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- EDIT SENDER CONFIGURATION MODAL -->
                        <div x-show="openSenderModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
                            <div @click.away="openSenderModal = false" class="bg-slate-900 border-2 border-slate-700 rounded-3xl p-6 max-w-md w-full text-white shadow-2xl space-y-5">
                                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                                    <h3 class="text-base font-black text-sky-400">⚙️ Ubah Pengaturan Pengirim</h3>
                                    <button type="button" @click="openSenderModal = false" class="text-slate-400 hover:text-white font-black text-lg">✕</button>
                                </div>

                                <form action="{{ route('settings.email.sender') }}" method="POST" class="space-y-4 text-xs">
                                    @csrf
                                    <div>
                                        <label class="block font-bold text-slate-300 mb-1">Nama Pengirim (From Name)</label>
                                        <input type="text" name="from_name" required value="{{ $emailConfig['from_name'] }}" class="w-full bg-slate-950 border-2 border-slate-700 focus:border-sky-400 rounded-xl text-white p-3 font-semibold min-h-[46px] outline-none">
                                    </div>

                                    <div>
                                        <label class="block font-bold text-slate-300 mb-1">Alamat Email Pengirim (From Address)</label>
                                        <input type="email" name="from_address" required value="{{ $emailConfig['from_address'] }}" class="w-full bg-slate-950 border-2 border-slate-700 focus:border-sky-400 rounded-xl text-white p-3 font-semibold min-h-[46px] outline-none">
                                    </div>

                                    <div>
                                        <label class="block font-bold text-slate-300 mb-1">Reply-To Address (Opsional)</label>
                                        <input type="email" name="reply_to" value="{{ $emailConfig['reply_to'] }}" class="w-full bg-slate-950 border-2 border-slate-700 focus:border-sky-400 rounded-xl text-white p-3 font-semibold min-h-[46px] outline-none">
                                    </div>

                                    <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-800">
                                        <button type="button" @click="openSenderModal = false" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold rounded-xl text-xs min-h-[44px]">Batal</button>
                                        <button type="submit" class="px-5 py-2.5 bg-sky-500 hover:bg-sky-400 text-slate-950 font-black rounded-xl text-xs shadow-lg transition-all min-h-[44px] cursor-pointer">
                                            💾 <span>Simpan Pengaturan</span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                    </div>
                @else

                <form action="{{ route('settings.update') }}" method="POST" enctype="multipart/form-data" class="p-4 md:p-6" x-data="{ hasChanges: false }" @change="hasChanges = true">
                    @csrf
                    <input type="hidden" name="active_tab" value="{{ $activeTab }}">

                    @if($activeTab === 'Branding')
                        <!-- Enhanced Live Preview Panel -->
                        <div class="mb-8 p-4 md:p-5 bg-slate-900 text-white rounded-2xl border border-slate-800 space-y-4">
                            <div class="border-b border-slate-800 pb-3">
                                <h3 class="text-xs font-bold text-sky-400 uppercase tracking-widest">🎨 Pratinjau Langsung — Tampilan {{ $brand['application']['short_name'] }}</h3>
                                <p class="text-xs text-slate-400">Ubah warna di bawah → pratinjau langsung berubah secara real-time.</p>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 text-xs">
                                <!-- 1. Sidebar Preview -->
                                <div class="p-4 rounded-xl border border-slate-800 bg-slate-950 space-y-2">
                                    <p class="font-bold text-slate-400 uppercase text-[10px]">1. Sidebar</p>
                                    <div class="rounded-xl border overflow-hidden" :style="{ borderColor: previewSidebarBg + '80' }">
                                        <div class="p-3 flex items-center gap-2" :style="{ backgroundColor: previewSidebarBg }">
                                            <div class="w-6 h-6 rounded-lg flex items-center justify-center font-black text-[10px]" :style="{ backgroundColor: previewPrimary, color: '#0F172A' }">W</div>
                                            <span class="font-bold text-[11px]" :style="{ color: previewSidebarText }">{{ $brand['application']['short_name'] }}</span>
                                        </div>
                                        <div class="px-3 py-1.5" :style="{ backgroundColor: previewSidebarBg }">
                                            <span class="text-[10px] block py-1" :style="{ color: previewSidebarText }">📊 Dasbor</span>
                                            <span class="text-[10px] block py-1 px-2 rounded" :style="{ backgroundColor: previewActiveBg, color: previewActiveText }">⚙️ Pengaturan</span>
                                            <span class="text-[10px] block py-1" :style="{ color: previewSidebarText }">👥 Pengguna</span>
                                        </div>
                                    </div>
                                </div>
                                <!-- 2. Topbar Preview -->
                                <div class="p-4 rounded-xl border border-slate-800 bg-slate-950 space-y-2">
                                    <p class="font-bold text-slate-400 uppercase text-[10px]">2. Topbar</p>
                                    <div class="p-3 rounded-xl flex items-center justify-between" :style="{ backgroundColor: previewTopbarBg }">
                                        <span class="font-bold text-[11px]" :style="{ color: previewPrimary }">Pengaturan</span>
                                        <div class="flex items-center gap-2">
                                            <span class="w-5 h-5 rounded-full bg-slate-600 flex items-center justify-center text-[8px] text-white">🔔</span>
                                            <span class="w-6 h-6 rounded-full flex items-center justify-center text-[9px] font-bold" :style="{ backgroundColor: previewPrimary, color: '#0F172A' }">A</span>
                                        </div>
                                    </div>
                                </div>
                                <!-- 3. Buttons Preview -->
                                <div class="p-4 rounded-xl border border-slate-800 bg-slate-950 space-y-2">
                                    <p class="font-bold text-slate-400 uppercase text-[10px]">3. Primary & Secondary Button</p>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <button type="button" class="px-3 py-1.5 rounded-lg font-bold text-[10px] shadow text-slate-950 min-h-[32px]" :style="{ backgroundColor: previewPrimary }">Primary</button>
                                        <button type="button" class="px-3 py-1.5 rounded-lg font-bold text-[10px] border min-h-[32px]" :style="{ borderColor: previewPrimary, color: previewPrimary }">Secondary</button>
                                    </div>
                                </div>
                                <!-- 4. Card Preview -->
                                <div class="p-4 rounded-xl border border-slate-800 bg-slate-950 space-y-2">
                                    <p class="font-bold text-slate-400 uppercase text-[10px]">4. Card</p>
                                    <div class="p-3 rounded-xl border border-slate-200 shadow-sm" :style="{ backgroundColor: previewCardBg }">
                                        <p class="text-[11px] font-bold text-slate-700">Kartu Dasbor</p>
                                        <p class="text-[10px] text-slate-400">Pratinjau konten</p>
                                        <div class="mt-2 h-1 rounded-full" :style="{ backgroundColor: previewPrimary }"></div>
                                    </div>
                                </div>
                                <!-- 5. Badge Preview -->
                                <div class="p-4 rounded-xl border border-slate-800 bg-slate-950 space-y-2">
                                    <p class="font-bold text-slate-400 uppercase text-[10px]">5. Badge</p>
                                    <div class="flex flex-wrap gap-2">
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold text-slate-950" :style="{ backgroundColor: previewPrimary }">Aktif</span>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border" :style="{ borderColor: previewPrimary, color: previewPrimary }">Menunggu</span>
                                    </div>
                                </div>
                                <!-- 6. Mobile Bottom Nav Preview -->
                                <div class="p-4 rounded-xl border border-slate-800 bg-slate-950 space-y-2">
                                    <p class="font-bold text-slate-400 uppercase text-[10px]">6. Mobile Bottom Nav</p>
                                    <div class="flex items-center justify-around p-2 rounded-xl border border-slate-700" :style="{ backgroundColor: previewSidebarBg }">
                                        <div class="text-center"><span class="text-[14px] block">🏠</span><span class="text-[8px] block" :style="{ color: previewSidebarText }">Home</span></div>
                                        <div class="text-center"><span class="text-[14px] block">📱</span><span class="text-[8px] block font-bold" :style="{ color: previewActiveText }">QR</span></div>
                                        <div class="text-center"><span class="text-[14px] block">👤</span><span class="text-[8px] block" :style="{ color: previewSidebarText }">Profil</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if($settings->count() > 0)
                    <div class="space-y-4 mb-8">
                        <h3 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-4 border-b border-slate-100 pb-2">Pengaturan {{ $categoryLabels[$activeTab] ?? $activeTab }}</h3>
                        @foreach($settings as $s)
                            <div class="flex flex-col md:flex-row md:items-start justify-between gap-3 md:gap-4 py-4 border-b border-slate-100 last:border-0 hover:bg-slate-50/60 px-3 rounded-2xl transition-colors">
                                <div class="w-full md:w-5/12">
                                    <label class="font-extrabold text-slate-800 text-sm block tracking-tight">{{ $s['Setting_Name'] }}</label>
                                    <span class="text-xs text-slate-500 font-medium block mt-0.5 leading-relaxed">{{ $s['Description'] ?? '' }}</span>
                                    @if(!empty($s['Setting_Key']))
                                        <span class="text-[10px] font-mono text-slate-400 block mt-1.5 bg-slate-100 px-2 py-0.5 rounded-md w-fit border border-slate-200 font-semibold">{{ $s['Setting_Key'] }}</span>
                                    @endif
                                </div>
                                <div class="w-full md:w-7/12">
                                    @if(($s['Value_Type'] ?? 'text') == 'boolean')
                                        <select name="settings[{{ $s['Setting_ID'] }}]" class="w-full rounded-xl border-2 border-slate-300 hover:border-slate-400 focus:border-sky-500 focus:ring-4 focus:ring-sky-500/10 bg-white text-slate-900 text-xs font-bold p-3 min-h-[46px] shadow-sm transition-all outline-none cursor-pointer">
                                            <option value="true" {{ $s['Setting_Value'] == 'true' ? 'selected' : '' }}>Aktif (Enabled)</option>
                                            <option value="false" {{ $s['Setting_Value'] == 'false' ? 'selected' : '' }}>Nonaktif (Disabled)</option>
                                        </select>
                                    @elseif(($s['Value_Type'] ?? 'text') == 'textarea')
                                        <textarea name="settings[{{ $s['Setting_ID'] }}]" rows="3" class="w-full rounded-xl border-2 border-slate-300 hover:border-slate-400 focus:border-sky-500 focus:ring-4 focus:ring-sky-500/10 bg-white text-slate-900 text-xs font-medium p-3 shadow-sm transition-all outline-none placeholder:text-slate-400">{{ $s['Setting_Value'] }}</textarea>
                                    @elseif(($s['Value_Type'] ?? 'text') == 'color' || str_starts_with($s['Setting_Key'] ?? '', 'BRAND_'))
                                        <div class="flex items-center gap-3" x-data="{ colorVal: '{{ $s['Setting_Value'] ?? '#38BDF8' }}' }">
                                            <div class="relative shrink-0">
                                                <input type="color"
                                                       x-model="colorVal"
                                                       @input="
                                                           @if(($s['Setting_Key'] ?? '') === 'BRAND_PRIMARY_COLOR') previewPrimary = colorVal @endif
                                                           @if(($s['Setting_Key'] ?? '') === 'BRAND_SIDEBAR_BG') previewSidebarBg = colorVal @endif
                                                           @if(($s['Setting_Key'] ?? '') === 'BRAND_SIDEBAR_TEXT') previewSidebarText = colorVal @endif
                                                           @if(($s['Setting_Key'] ?? '') === 'BRAND_SIDEBAR_ACTIVE_BG') previewActiveBg = colorVal @endif
                                                           @if(($s['Setting_Key'] ?? '') === 'BRAND_SIDEBAR_ACTIVE_TEXT') previewActiveText = colorVal @endif
                                                           @if(($s['Setting_Key'] ?? '') === 'BRAND_TOPBAR_BG') previewTopbarBg = colorVal @endif
                                                           @if(($s['Setting_Key'] ?? '') === 'BRAND_CARD_BG') previewCardBg = colorVal @endif
                                                           @if(($s['Setting_Key'] ?? '') === 'BRAND_PAGE_BG') previewPageBg = colorVal @endif
                                                       "
                                                       class="w-12 h-12 rounded-xl border-2 border-slate-300 hover:border-slate-400 cursor-pointer shrink-0 shadow-sm p-1 bg-white">
                                            </div>
                                            <input type="text"
                                                   name="settings[{{ $s['Setting_ID'] }}]"
                                                   x-model="colorVal"
                                                   @input="
                                                       @if(($s['Setting_Key'] ?? '') === 'BRAND_PRIMARY_COLOR') previewPrimary = colorVal @endif
                                                       @if(($s['Setting_Key'] ?? '') === 'BRAND_SIDEBAR_BG') previewSidebarBg = colorVal @endif
                                                       @if(($s['Setting_Key'] ?? '') === 'BRAND_SIDEBAR_TEXT') previewSidebarText = colorVal @endif
                                                       @if(($s['Setting_Key'] ?? '') === 'BRAND_SIDEBAR_ACTIVE_BG') previewActiveBg = colorVal @endif
                                                       @if(($s['Setting_Key'] ?? '') === 'BRAND_SIDEBAR_ACTIVE_TEXT') previewActiveText = colorVal @endif
                                                       @if(($s['Setting_Key'] ?? '') === 'BRAND_TOPBAR_BG') previewTopbarBg = colorVal @endif
                                                       @if(($s['Setting_Key'] ?? '') === 'BRAND_CARD_BG') previewCardBg = colorVal @endif
                                                       @if(($s['Setting_Key'] ?? '') === 'BRAND_PAGE_BG') previewPageBg = colorVal @endif
                                                   "
                                                   class="w-full rounded-xl border-2 border-slate-300 hover:border-slate-400 focus:border-sky-500 focus:ring-4 focus:ring-sky-500/10 bg-white text-slate-900 text-xs font-mono font-bold p-3 min-h-[46px] shadow-sm transition-all outline-none">
                                        </div>
                                    @elseif(($s['Value_Type'] ?? 'text') == 'file')
                                        <div class="space-y-3">
                                            @if(!empty($s['Setting_Value']))
                                                <div class="flex items-center gap-3 p-3 bg-slate-100/90 rounded-xl border-2 border-slate-200 shadow-inner">
                                                    <img src="{{ asset($s['Setting_Value']) }}" alt="{{ $s['Setting_Name'] }}" class="w-14 h-14 object-contain rounded-lg border border-slate-300 bg-white p-1 shadow-sm" onerror="this.src='https://ui-avatars.com/api/?name=W&background=1e293b&color=38bdf8&bold=true&size=48'">
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-xs font-bold text-slate-800">File Terpasang</p>
                                                        <p class="text-xs font-mono text-slate-500 truncate">{{ basename($s['Setting_Value']) }}</p>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="flex items-center gap-2 p-3 bg-amber-50 rounded-xl border-2 border-amber-200">
                                                    <span class="text-amber-500 text-base">⚠️</span>
                                                    <span class="text-xs text-amber-800 font-bold">Belum ada file terpasang.</span>
                                                </div>
                                            @endif
                                            <input type="file" name="setting_files[{{ $s['Setting_ID'] }}]" accept="image/jpeg,image/png,image/jpg,image/webp" class="block w-full text-xs text-slate-600 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-extrabold file:bg-sky-500 file:text-slate-950 hover:file:bg-sky-400 transition-all cursor-pointer min-h-[46px] border-2 border-dashed border-slate-300 rounded-xl p-2 bg-slate-50 hover:bg-white">
                                            <p class="text-[11px] text-slate-500 font-medium">Format: JPG, PNG, WEBP. Maksimal file: 5MB.</p>
                                        </div>
                                    @else
                                        <input type="{{ ($s['Value_Type'] ?? 'text') == 'number' ? 'number' : 'text' }}" name="settings[{{ $s['Setting_ID'] }}]" value="{{ $s['Setting_Value'] }}" class="w-full rounded-xl border-2 border-slate-300 hover:border-slate-400 focus:border-sky-500 focus:ring-4 focus:ring-sky-500/10 bg-white text-slate-900 text-xs font-semibold p-3 min-h-[46px] shadow-sm transition-all outline-none placeholder:text-slate-400">
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @endif

                    @if($parameters->count() > 0)
                    <div class="space-y-4">
                        <h3 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-4 border-b border-slate-100 pb-2">Parameter Modul</h3>
                        @foreach($parameters as $p)
                            <div class="flex flex-col md:flex-row md:items-start justify-between gap-3 md:gap-4 py-3 border-b border-slate-100 last:border-0 hover:bg-slate-50/60 px-3 rounded-2xl transition-colors">
                                <div class="w-full md:w-5/12">
                                    <label class="font-extrabold text-slate-800 text-sm block tracking-tight">{{ str_replace('_', ' ', $p['Parameter_Key']) }}</label>
                                    <span class="text-xs text-slate-500 font-medium block mt-0.5 leading-relaxed">{{ $p['Description'] ?? '' }}</span>
                                </div>
                                <div class="w-full md:w-7/12">
                                    <input type="text" name="parameters[{{ $p['Parameter_ID'] }}]" value="{{ $p['Parameter_Value'] }}" class="w-full rounded-xl border-2 border-slate-300 hover:border-slate-400 focus:border-sky-500 focus:ring-4 focus:ring-sky-500/10 bg-white text-sky-950 text-xs font-mono font-bold p-3 min-h-[46px] shadow-sm transition-all outline-none">
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @endif

                    @if($settings->count() == 0 && $parameters->count() == 0)
                    <div class="text-center py-12">
                        <div class="w-16 h-16 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center text-2xl mx-auto mb-3">⚙️</div>
                        <h3 class="text-slate-600 font-bold text-sm">Tidak Ada Pengaturan di Kategori Ini</h3>
                        <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">Konfigurasi default {{ $categoryLabels[$activeTab] ?? $activeTab }} berjalan otomatis melalui SystemSettingService.</p>
                    </div>
                    @else
                    <!-- Sticky Save Button -->
                    <div class="mt-8 pt-6 border-t border-slate-100 flex items-center justify-between sticky bottom-0 bg-white pb-2">
                        <div>
                            <span x-show="hasChanges" x-cloak class="text-xs font-bold text-amber-600 flex items-center gap-1">
                                ⚠️ Ada perubahan yang belum disimpan
                            </span>
                        </div>
                        <button type="submit" class="px-6 py-2.5 bg-slate-900 hover:bg-slate-800 text-sky-400 font-bold rounded-xl shadow-md transition-all flex items-center gap-2 min-h-[44px]">
                            💾 <span>Simpan Perubahan</span>
                        </button>
                    </div>
                    @endif
                </form>
                @endif
            </div>
        </div>
    </div>

    <!-- TEST EMAIL MODAL -->
    <div x-show="openTestModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" x-cloak>
        <div class="bg-slate-900 border border-slate-800 text-slate-200 rounded-2xl p-6 w-full max-w-md space-y-4 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 class="text-sm font-bold text-white">🚀 Kirim Test Email</h3>
                <button @click="openTestModal = false" class="text-slate-400 hover:text-white text-lg">✕</button>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-400 mb-1">Email Penerima</label>
                <input type="email" x-model="testRecipient" placeholder="email@example.com" class="w-full text-xs bg-slate-800 border-slate-700 rounded-xl text-white p-2.5 min-h-[44px]">
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button @click="openTestModal = false" class="px-4 py-2.5 bg-slate-800 text-slate-300 text-xs font-bold rounded-xl min-h-[44px]">Batal</button>
                <button @click="runTestEmail()" class="px-4 py-2.5 bg-sky-500 hover:bg-sky-400 text-slate-950 text-xs font-extrabold rounded-xl shadow-lg min-h-[44px]">Kirim</button>
            </div>
        </div>
    </div>
</div>

<script>
function systemSettingsHub() {
    return {
        openTestModal: false,
        testRecipient: '',
        previewPrimary: '{{ $themeTokens['primary'] ?? '#38BDF8' }}',
        previewSidebarBg: '{{ $themeTokens['sidebar_bg'] ?? '#111827' }}',
        previewSidebarText: '{{ $themeTokens['sidebar_text'] ?? '#94A3B8' }}',
        previewActiveBg: '{{ $themeTokens['sidebar_active_bg'] ?? '#1E293B' }}',
        previewActiveText: '{{ $themeTokens['sidebar_active'] ?? '#38BDF8' }}',
        previewTopbarBg: '{{ $themeTokens['topbar_bg'] ?? '#111827' }}',
        previewPageBg: '{{ $themeTokens['page_bg'] ?? '#E2E8F0' }}',
        previewCardBg: '{{ $themeTokens['card_bg'] ?? '#FFFFFF' }}',

        runTestEmail() {
            if (!this.testRecipient) { alert('Masukkan email penerima!'); return; }
            fetch("{{ route('settings.test_email') }}", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ recipient: this.testRecipient })
            })
            .then(res => res.json())
            .then(data => { alert(data.message); if (data.success) this.openTestModal = false; })
            .catch(err => alert('Gagal: ' + err.message));
        }
    }
}
</script>
@endsection
