@props(['userRole'])

@php
    $logoUrl = $companyProfile['company']['logo_url'] ?? asset('img/logo.png.jpeg');
    $companyName = $companyProfile['company']['name'] ?? 'WAKAMIYA';
    $tagline = $companyProfile['company']['tagline'] ?? 'SISTEM MANAJEMEN';
    
    // Centralized MASTER Menu Mapping (De-duplicated)
    $masterMenus = [
        ['group' => 'DASHBOARD', 'items' => [
            ['label' => 'Dasbor Utama', 'route' => 'dashboard.administrator', 'active_route' => 'dashboard.administrator', 'icon' => 'dashboard'],
            ['label' => 'Dasbor SDM', 'route' => 'dashboard.hr', 'active_route' => 'dashboard.hr', 'icon' => 'dashboard'],
            ['label' => 'Dasbor Akademik', 'route' => 'dashboard.academic', 'active_route' => 'dashboard.academic', 'icon' => 'dashboard'],
            ['label' => 'Dasbor Keuangan', 'route' => 'dashboard.finance', 'active_route' => 'dashboard.finance', 'icon' => 'dashboard'],
            ['label' => 'Dasbor Pemasaran', 'route' => 'dashboard.marketing', 'active_route' => 'dashboard.marketing', 'icon' => 'dashboard'],
        ]],
        ['group' => 'OPERASIONAL', 'items' => [
            ['label' => 'Kotak Persetujuan', 'route' => 'approvals.index', 'active_route' => 'approvals.*', 'icon' => 'inbox'],
            ['label' => 'Pegawai', 'route' => 'employees.index', 'active_route' => 'employees.*', 'icon' => 'identification'],
            ['label' => 'Monitoring Kehadiran', 'route' => 'hr.attendance.monitoring', 'active_route' => 'hr.attendance.*', 'icon' => 'clock'],
            ['label' => 'Alumni', 'route' => 'alumni.index', 'active_route' => 'alumni.*', 'icon' => 'academic-cap'],
        ]],
        ['group' => 'AKADEMIK', 'items' => [
            ['label' => 'Siswa', 'route' => 'students.index', 'active_route' => 'students.*', 'icon' => 'academic-cap'],
            ['label' => 'Pengajar / Guru', 'route' => 'teachers.index', 'active_route' => 'teachers.*', 'icon' => 'user-group'],
            ['label' => 'Program Studi', 'route' => 'programs.index', 'active_route' => 'programs.*', 'icon' => 'book-open'],
            ['label' => 'Batch', 'route' => 'batches.index', 'active_route' => 'batches.*', 'icon' => 'collection'],
            ['label' => 'Kelas', 'route' => 'classes.index', 'active_route' => 'classes.*', 'icon' => 'view-boards'],
            ['label' => 'Pengumuman', 'route' => 'announcements.index', 'active_route' => 'announcements.*', 'icon' => 'inbox'],
            ['label' => 'Mata Pelajaran', 'route' => 'subjects.index', 'active_route' => 'subjects.*', 'icon' => 'library'],
            ['label' => 'Jadwal Kelas', 'route' => 'schedules.index', 'active_route' => 'schedules.*', 'icon' => 'calendar'],
            ['label' => 'Presensi Akademik', 'route' => 'attendances.index', 'active_route' => 'attendances.*', 'icon' => 'clock'],
            ['label' => 'Laporan Absensi', 'route' => 'attendance.reports.index', 'active_route' => 'attendance.reports.*', 'icon' => 'chart-bar'],
            ['label' => 'Tinjau Pengajuan', 'route' => 'academic.attendance.requests.index', 'active_route' => 'academic.attendance.requests.*', 'icon' => 'clipboard-check'],
        ]],
        ['group' => 'FINANCE', 'items' => [
            ['label' => 'Pembayaran Pendidikan', 'route' => 'finance.education-payments.index', 'active_route' => 'finance.education-payments.*', 'icon' => 'cash'],
            ['label' => 'Master Akun', 'route' => 'accounts.index', 'active_route' => 'accounts.*', 'icon' => 'collection'],
            ['label' => 'Transaksi', 'route' => 'transactions.index', 'active_route' => 'transactions.*', 'icon' => 'switch-horizontal'],
            ['label' => 'Tagihan', 'route' => 'invoices.index', 'active_route' => 'invoices.*', 'icon' => 'document-duplicate'],
            ['label' => 'Pembayaran', 'route' => 'payments.index', 'active_route' => 'payments.*', 'icon' => 'credit-card'],
            ['label' => 'Penggajian & Gaji', 'route' => 'payrolls.index', 'active_route' => 'payrolls.*', 'icon' => 'cash'],
            ['label' => 'Laporan Keuangan', 'route' => 'reports.finance.index', 'active_route' => 'reports.finance.*', 'icon' => 'chart-bar'],
        ]],
        ['group' => 'MARKETING', 'items' => [
            ['label' => 'Perusahaan', 'route' => 'companies.index', 'active_route' => 'companies.*', 'icon' => 'office-building'],
        ]],
        ['group' => 'SYSTEM', 'items' => [
            ['label' => 'Pengaturan Sistem', 'route' => 'settings.index', 'active_route' => 'settings.*', 'icon' => 'cog'],
            ['label' => 'Pengaturan SDM', 'route' => 'hr.settings.index', 'active_route' => 'hr.settings.*', 'icon' => 'cog'],
            ['label' => 'Pengaturan Akademik', 'route' => 'academic.settings.index', 'active_route' => 'academic.settings.*', 'icon' => 'cog'],
            ['label' => 'Pengaturan Keuangan', 'route' => 'finance.settings.index', 'active_route' => 'finance.settings.*', 'icon' => 'cog'],
            ['label' => 'Pengguna', 'route' => 'users.index', 'active_route' => 'users.*', 'icon' => 'users'],
            ['label' => 'Jabatan', 'route' => 'positions.index', 'active_route' => 'positions.*', 'icon' => 'badge-check'],
            ['label' => 'Departemen', 'route' => 'departments.index', 'active_route' => 'departments.*', 'icon' => 'office-building'],
            ['label' => 'Modul', 'route' => 'modules.index', 'active_route' => 'modules.*', 'icon' => 'puzzle-piece'],
            ['label' => 'Jejak Audit', 'route' => 'audit.index', 'active_route' => 'audit.*', 'icon' => 'clipboard-list'],
        ]],
        ['group' => 'TOOLS', 'items' => [
            ['label' => 'Pembuat Dokumen', 'route' => 'finance.smart_generator.index', 'active_route' => 'finance.smart_generator.*', 'icon' => 'sparkles'],
            ['label' => 'QR Presensi', 'route' => 'attendance.qr.index', 'active_route' => 'attendance.qr.*', 'icon' => 'qrcode'],
            ['label' => 'Pindai QR Pegawai', 'route' => 'hr.attendance.qr.scanner', 'active_route' => 'hr.attendance.qr.*', 'icon' => 'camera'],
        ]],
    ];

    // Determine current active group to auto-expand it
    $activeGroup = '';
    foreach ($masterMenus as $group) {
        foreach ($group['items'] as $item) {
            $checkRoute = $item['active_route'] ?? $item['route'];
            if (request()->routeIs($checkRoute)) {
                $activeGroup = $group['group'];
                break 2;
            }
        }
    }
@endphp

<!-- Sidebar State via AlpineJS -->
<aside id="sidebar" 
    x-data="sidebarEngine('{{ $activeGroup }}')"
    :class="{ 'w-64': expanded, 'w-20': !expanded, 'translate-x-0': mobileOpen, '-translate-x-full': !mobileOpen }"
    x-bind:aria-hidden="(!mobileOpen && window.innerWidth < 1024).toString()"
    role="navigation"
    aria-label="Navigasi lengkap {{ $userRole }}"
    class="shadow-xl flex-shrink-0 z-50 flex flex-col transition-all duration-300 ease-in-out fixed inset-y-0 left-0 lg:relative lg:translate-x-0 border-r border-slate-800 w-64 -translate-x-full"
    style="background-color: var(--color-sidebar-bg, #111827); color: var(--color-sidebar-text, #94A3B8);">
    
    <!-- Mobile Close Overlay & Button (Hidden on Desktop) -->
    <div x-show="mobileOpen" @click="mobileOpen = false" class="sr-only lg:hidden" style="display: none;"></div>
    
    <button @click="mobileOpen = false" aria-label="Tutup navigasi" class="lg:hidden absolute top-4 right-[-48px] min-h-[44px] min-w-[44px] p-2 bg-slate-800 text-white rounded-r-md shadow-lg" style="display: none;" x-show="mobileOpen">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
    </button>

    <!-- Brand / Logo Area -->
    <div class="pt-8 pb-6 flex flex-col items-center justify-center border-b border-slate-800 relative shrink-0 px-3 cursor-pointer" @click="toggleExpanded()">
        <div :class="{ 'w-[84px] h-[84px]': expanded, 'w-[48px] h-[48px]': !expanded }" class="rounded-full flex items-center justify-center border-[3px] p-1 mb-2 shadow-lg transition-all duration-300" style="background-color: var(--color-sidebar-bg, #111827); border-color: var(--color-primary, #38BDF8);">
            <img src="{{ $logoUrl }}" alt="{{ $companyName }}" class="w-full h-full object-cover bg-white rounded-full p-0.5" onerror="this.src='{{ asset('img/logo.png.jpeg') }}'">
        </div>
        <div x-show="expanded" x-transition class="flex flex-col items-center">
            <span class="text-[14px] font-black text-white tracking-wider uppercase mt-1 text-center px-1 leading-snug break-words max-w-full block">{{ $companyName }}</span>
            <span class="text-[9px] font-bold tracking-[0.15em] mt-1 text-center px-1 text-wrap leading-tight break-words max-w-full block" style="color: var(--color-primary, #38BDF8);">{{ $tagline }}</span>
            <span class="text-[10px] text-slate-500 mt-1 tracking-widest font-semibold">v1.0</span>
        </div>
        
        <!-- Toggle Button Desktop -->
        <button @click.stop="toggleExpanded()" class="hidden lg:flex absolute -right-3 top-1/2 -translate-y-1/2 w-6 h-6 bg-slate-800 rounded-full border border-slate-700 text-slate-400 items-center justify-center hover:text-white hover:bg-slate-700 transition-colors z-10 shadow-lg">
            <svg x-show="expanded" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            <svg x-show="!expanded" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
        </button>
    </div>
    
    <!-- Menu Container -->
    <div class="flex-1 overflow-y-auto py-4 dark-scrollbar flex flex-col gap-1 pr-2 pl-2">
        
        @if($userRole === 'MASTER')
            <!-- MASTER UX -->
            @foreach($masterMenus as $group)
                <div class="mb-2">
                    <button @click="toggleGroup('{{ $group['group'] }}'); if(!expanded) expanded = true;" class="w-full flex items-center justify-between px-3 py-2 text-xs font-bold text-slate-400 hover:text-white uppercase tracking-wider group transition-colors">
                        <span x-show="expanded" x-transition>{{ ['DASHBOARD' => 'DASBOR', 'FINANCE' => 'KEUANGAN', 'MARKETING' => 'PEMASARAN', 'SYSTEM' => 'SISTEM', 'TOOLS' => 'ALAT'][$group['group']] ?? $group['group'] }}</span>
                        <div x-show="!expanded" class="w-full text-center" title="{{ $group['group'] }}">
                            <div class="h-0.5 w-4 bg-slate-600 rounded mx-auto group-hover:bg-slate-400"></div>
                        </div>
                        <svg x-show="expanded" :class="{'rotate-180': isGroupOpen('{{ $group['group'] }}')}" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    <div x-show="isGroupOpen('{{ $group['group'] }}') || !expanded" x-collapse :class="{'hidden': !expanded && !isGroupOpen('{{ $group['group'] }}')}" class="space-y-1 mt-1">
                        @foreach($group['items'] as $item)
                            @php
                                $checkRoute = $item['active_route'] ?? $item['route'];
                                $isActive = request()->routeIs($checkRoute);
                            @endphp
                            <a href="{{ route($item['route']) }}" title="{{ $item['label'] }}" @click="if(window.innerWidth < 1024) mobileOpen = false"
                               class="{{ $isActive ? 'bg-sky-500/10 text-white shadow-sm ring-1 ring-sky-500/50' : 'text-slate-400 hover:bg-white/5 hover:text-white' }} flex items-center py-2.5 px-3 rounded-lg transition-all relative group">
                                
                                @if($isActive)
                                    <div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-sky-400 rounded-r-md shadow-[0_0_8px_rgba(56,189,248,0.6)]"></div>
                                @endif
                                
                                <div class="shrink-0 flex items-center justify-center" :class="{ 'w-full': !expanded, 'mr-3': expanded }">
                                    <x-sidebar.icon name="{{ $item['icon'] }}" class="w-5 h-5 {{ $isActive ? 'text-sky-400 drop-shadow-[0_0_3px_rgba(56,189,248,0.5)]' : 'group-hover:text-slate-300' }}" />
                                </div>
                                
                                <span x-show="expanded" class="text-[13px] font-semibold truncate leading-tight transition-opacity duration-200">{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @else
            <!-- NON-MASTER ROLES (Original Structure with Alpine visibility wrappers) -->
            <div class="space-y-1">
                <x-sidebar.nav-link href="{{ route('announcement-feed.index') }}" active="{{ request()->routeIs('announcement-feed.*') }}" icon="inbox">Kotak Pengumuman</x-sidebar.nav-link>
                @if($userRole === 'ADMINISTRATOR')
                    <x-sidebar.nav-link href="{{ route('dashboard.administrator') }}" active="{{ request()->routeIs('dashboard.*') }}" icon="dashboard">Dasbor</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('finance.education-payments.index') }}" active="{{ request()->routeIs('finance.education-payments.*') }}" icon="cash">Pembayaran Pendidikan</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('approvals.index') }}" active="{{ request()->routeIs('approvals.*') }}" icon="inbox">Kotak Persetujuan</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('alumni.index') }}" active="{{ request()->routeIs('alumni.*') }}" icon="academic-cap">Alumni</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('audit.index') }}" active="{{ request()->routeIs('audit.*') }}" icon="clipboard-list">Jejak Audit</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('hr.attendance.monitoring') }}" active="{{ request()->routeIs('hr.attendance.*') }}" icon="clock">Monitoring Pegawai</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('attendance.reports.index') }}" active="{{ request()->routeIs('attendance.reports.*') }}" icon="chart-bar">Laporan Absensi</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('finance.smart_generator.index') }}" active="{{ request()->routeIs('finance.smart_generator.*') }}" icon="sparkles">Pembuat Dokumen</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('attendance.qr.index') }}" active="{{ request()->routeIs('attendance.qr.*') }}" icon="qrcode">QR Presensi</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('hr.attendance.qr.scanner') }}" active="{{ request()->routeIs('hr.attendance.qr.*') }}" icon="camera">Pindai QR Pegawai</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('settings.index') }}" active="{{ request()->routeIs('settings.*') }}" icon="cog">Pengaturan Sistem</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('admin.stickers.index') }}" active="{{ request()->routeIs('admin.stickers.*') }}" icon="sparkles">Library Stiker</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('users.index') }}" active="{{ request()->routeIs('users.*') }}" icon="users">Pengguna</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('modules.index') }}" active="{{ request()->routeIs('modules.*') }}" icon="puzzle-piece">Modul</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('announcements.index') }}" active="{{ request()->routeIs('announcements.*') }}" icon="inbox">Pengumuman</x-sidebar.nav-link>
                @elseif($userRole === 'HR')
                    <x-sidebar.nav-link href="{{ route('dashboard.hr') }}" active="{{ request()->routeIs('dashboard.*') }}" icon="dashboard">Dasbor</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('employees.index') }}" active="{{ request()->routeIs('employees.*') }}" icon="identification">Pegawai</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('hr.attendance.monitoring') }}" active="{{ request()->routeIs('hr.attendance.*') }}" icon="clock">Monitoring Kehadiran</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('attendance.qr.index') }}" active="{{ request()->routeIs('attendance.qr.*') }}" icon="qrcode">QR Presensi</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('hr.attendance.qr.scanner') }}" active="{{ request()->routeIs('hr.attendance.qr.*') }}" icon="camera">Pindai QR Pegawai</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('payrolls.index') }}" active="{{ request()->routeIs('payrolls.*') }}" icon="cash">Penggajian & Gaji</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('departments.index') }}" active="{{ request()->routeIs('departments.*') }}" icon="office-building">Departemen</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('positions.index') }}" active="{{ request()->routeIs('positions.*') }}" icon="badge-check">Jabatan</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('hr.settings.index') }}" active="{{ request()->routeIs('hr.settings.*') }}" icon="cog">Pengaturan SDM</x-sidebar.nav-link>
                @elseif($userRole === 'ACADEMIC')
                    <x-sidebar.nav-link href="{{ route('dashboard.academic') }}" active="{{ request()->routeIs('dashboard.*') }}" icon="dashboard">Dasbor</x-sidebar.nav-link>
                    <div class="px-3 pt-4 pb-1"><span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Data Akademik</span></div>
                    <x-sidebar.nav-link href="{{ route('students.index') }}" active="{{ request()->routeIs('students.*') }}" icon="academic-cap">Siswa</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('teachers.index') }}" active="{{ request()->routeIs('teachers.*') }}" icon="user-group">Pengajar</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('programs.index') }}" active="{{ request()->routeIs('programs.*') }}" icon="book-open">Program</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('batches.index') }}" active="{{ request()->routeIs('batches.*') }}" icon="collection">Batch</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('classes.index') }}" active="{{ request()->routeIs('classes.*') }}" icon="view-boards">Kelas</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('announcements.index') }}" active="{{ request()->routeIs('announcements.*') }}" icon="inbox">Pengumuman</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('subjects.index') }}" active="{{ request()->routeIs('subjects.*') }}" icon="library">Materi</x-sidebar.nav-link>
                    <div class="px-3 pt-4 pb-1"><span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Operasional Akademik</span></div>
                    <x-sidebar.nav-link href="{{ route('schedules.index') }}" active="{{ request()->routeIs('schedules.*') }}" icon="calendar">Jadwal Kelas</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('attendances.index') }}" active="{{ request()->routeIs('attendances.*') }}" icon="clock">Presensi</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('attendance.qr.index') }}" active="{{ request()->routeIs('attendance.qr.*') }}" icon="qrcode">QR Presensi</x-sidebar.nav-link>
                    <div class="px-3 pt-4 pb-1"><span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Tinjauan</span></div>
                    <x-sidebar.nav-link href="{{ route('academic.attendance.requests.index') }}" active="{{ request()->routeIs('academic.attendance.requests.*') }}" icon="clipboard-check">Tinjau Pengajuan</x-sidebar.nav-link>
                    <div class="px-3 pt-4 pb-1"><span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Lainnya</span></div>
                    <x-sidebar.nav-link href="{{ route('alumni.index') }}" active="{{ request()->routeIs('alumni.*') }}" icon="academic-cap">Alumni</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('academic.settings.index') }}" active="{{ request()->routeIs('academic.settings.*') }}" icon="cog">Pengaturan Akademik</x-sidebar.nav-link>
                @elseif($userRole === 'MARKETING')
                    <x-sidebar.nav-link href="{{ route('dashboard.marketing') }}" active="{{ request()->routeIs('dashboard.*') }}" icon="dashboard">Dasbor</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('hr.attendance.qr.scanner') }}" active="{{ request()->routeIs('hr.attendance.qr.*') }}" icon="camera">Pindai QR Pegawai</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('companies.index') }}" active="{{ request()->routeIs('companies.*') }}" icon="office-building">Perusahaan</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('notifications.index') }}" active="{{ request()->routeIs('notifications.*') }}" icon="inbox">Notifikasi</x-sidebar.nav-link>
                @elseif($userRole === 'FINANCE')
                    <x-sidebar.nav-link href="{{ route('dashboard.finance') }}" active="{{ request()->routeIs('dashboard.*') }}" icon="dashboard">Dasbor</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('hr.attendance.qr.scanner') }}" active="{{ request()->routeIs('hr.attendance.qr.*') }}" icon="camera">Pindai QR Pegawai</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('accounts.index') }}" active="{{ request()->routeIs('accounts.*') }}" icon="collection">Master Akun</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('transactions.index') }}" active="{{ request()->routeIs('transactions.*') }}" icon="switch-horizontal">Transaksi</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('invoices.index') }}" active="{{ request()->routeIs('invoices.*') }}" icon="document-duplicate">Tagihan</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('finance.smart_generator.index') }}" active="{{ request()->routeIs('finance.smart_generator.*') }}" icon="sparkles">Pembuat Dokumen</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('payments.index') }}" active="{{ request()->routeIs('payments.*') }}" icon="credit-card">Pembayaran</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('reports.finance.index') }}" active="{{ request()->routeIs('reports.finance.*') }}" icon="chart-bar">Laporan Keuangan</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('finance.settings.index') }}" active="{{ request()->routeIs('finance.settings.*') }}" icon="cog">Pengaturan Keuangan</x-sidebar.nav-link>
                @elseif($userRole === 'DIRECTOR')
                    <x-sidebar.nav-link href="{{ route('dashboard.director') }}" active="{{ request()->routeIs('dashboard.*') }}" icon="dashboard">Dasbor</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('hr.attendance.qr.scanner') }}" active="{{ request()->routeIs('hr.attendance.qr.*') }}" icon="camera">Pindai QR Pegawai</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('approvals.index') }}" active="{{ request()->routeIs('approvals.*') }}" icon="inbox">Kotak Persetujuan</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('transactions.index') }}" active="{{ request()->routeIs('transactions.*') }}" icon="switch-horizontal">Transaksi</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('reports.finance.index') }}" active="{{ request()->routeIs('reports.finance.*') }}" icon="chart-bar">Laporan Keuangan</x-sidebar.nav-link>
                @elseif($userRole === 'TEACHER')
                    <x-sidebar.nav-link href="{{ route('dashboard.teacher') }}" active="{{ request()->routeIs('dashboard.*') }}" icon="dashboard">Dasbor</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('hr.attendance.qr.scanner') }}" active="{{ request()->routeIs('hr.attendance.qr.*') }}" icon="camera">Pindai QR Pegawai</x-sidebar.nav-link>
                    <div class="px-4 mt-6 mb-2 text-xs font-bold text-slate-400 uppercase tracking-wider">Pengajaran</div>
                    <x-sidebar.nav-link href="{{ route('teacher.workspace.schedule') }}" active="{{ request()->routeIs('teacher.workspace.schedule') }}" icon="calendar">Jadwal Mengajar</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('teacher.workspace.classes') }}" active="{{ request()->routeIs('teacher.workspace.classes') || request()->routeIs('teacher.workspace.classes.*') }}" icon="view-boards">Kelas Saya</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('teacher.workspace.students') }}" active="{{ request()->routeIs('teacher.workspace.students') }}" icon="user-group">Daftar Siswa</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('announcements.index') }}" active="{{ request()->routeIs('announcements.*') }}" icon="inbox">Pengumuman</x-sidebar.nav-link>
                    <div class="px-4 mt-6 mb-2 text-xs font-bold text-slate-400 uppercase tracking-wider">Kehadiran</div>
                    <x-sidebar.nav-link href="{{ route('teacher.workspace.attendances') }}" active="{{ request()->routeIs('teacher.workspace.attendances') }}" icon="clock">Kehadiran Siswa</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('attendance.reports.index') }}" active="{{ request()->routeIs('attendance.reports.*') }}" icon="chart-bar">Laporan Absensi</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('teacher.workspace.attendance-requests') }}" active="{{ request()->routeIs('teacher.workspace.attendance-requests*') }}" icon="document-text">Pengajuan Izin/Sakit</x-sidebar.nav-link>
                    <div class="px-4 mt-6 mb-2 text-xs font-bold text-slate-400 uppercase tracking-wider">Akademik</div>
                    <x-sidebar.nav-link href="{{ route('teacher.workspace.scores') }}" active="{{ request()->routeIs('teacher.workspace.scores*') }}" icon="chart-bar">Penilaian</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('teacher.workspace.assignments') }}" active="{{ request()->routeIs('teacher.workspace.assignments*') }}" icon="clipboard-list">Tugas Harian</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('teacher.quizzes.index') }}" active="{{ request()->routeIs('teacher.quizzes.*') }}" icon="academic-cap">Kuis</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('teacher.workspace.calendar') }}" active="{{ request()->routeIs('teacher.workspace.calendar') }}" icon="calendar">Kalender Akademik</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('teacher.workspace.reports') }}" active="{{ request()->routeIs('teacher.workspace.reports') }}" icon="chart-bar">Laporan Pengajaran</x-sidebar.nav-link>
                @elseif($userRole === 'STUDENT')
                    <x-sidebar.nav-link href="{{ route('dashboard.student') }}" active="{{ request()->routeIs('dashboard.*') }}" icon="dashboard">Dasbor</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('student.portal.announcements') }}" active="{{ request()->routeIs('student.portal.announcements*') }}" icon="inbox">Pengumuman</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('attendances.student.scanner') }}" active="{{ request()->routeIs('attendances.student.*') }}" icon="qrcode">Presensi QR</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('attendances.my-history') }}" active="{{ request()->routeIs('attendances.my-history') }}" icon="clock">Riwayat Absensi</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('student.schedule') }}" active="{{ request()->routeIs('student.schedule') }}" icon="calendar">Jadwal</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('student.calendar') }}" active="{{ request()->routeIs('student.calendar') }}" icon="calendar">Kalender</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('student.subjects') }}" active="{{ request()->routeIs('student.subjects') }}" icon="book-open">Materi Saya</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('student.portal.assignments') }}" active="{{ request()->routeIs('student.portal.assignments*') }}" icon="document-text">Tugas</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('student.quizzes.index') }}" active="{{ request()->routeIs('student.quizzes.*') }}" icon="academic-cap">Kuis</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('student.progress') }}" active="{{ request()->routeIs('student.progress') }}" icon="clipboard-check">Nilai</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('student.portal.materials') }}" active="{{ request()->routeIs('student.portal.materials*') }}" icon="book-open">Materi</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('student.billing.index') }}" active="{{ request()->routeIs('student.billing.*') }}" icon="cash">Tagihan Saya</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('student.attendance.requests.index') }}" active="{{ request()->routeIs('student.attendance.requests.*') }}" icon="document-text">Pengajuan Izin/Sakit</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('notifications.index') }}" active="{{ request()->routeIs('notifications.*') }}" icon="inbox">Notifikasi</x-sidebar.nav-link>
                @elseif($userRole === 'EMPLOYEE')
                    <x-sidebar.nav-link href="{{ route('dashboard') }}" active="{{ request()->routeIs('dashboard') }}" icon="dashboard">Dasbor</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('hr.attendance.qr.scanner') }}" active="{{ request()->routeIs('hr.attendance.qr.*') }}" icon="camera">Pindai QR Pegawai</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('dashboard.personal-payroll') }}" active="{{ request()->routeIs('dashboard.personal-payroll*') }}" icon="cash">Slip Gaji Saya</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('notifications.index') }}" active="{{ request()->routeIs('notifications.*') }}" icon="inbox">Notifikasi</x-sidebar.nav-link>
                    <x-sidebar.nav-link href="{{ route('profile.index') }}" active="{{ request()->routeIs('profile.*') }}" icon="users">Profil</x-sidebar.nav-link>
                @endif
            </div>
        @endif

    </div>

    <!-- User Profile & Logout Footer -->
    <div class="p-3 border-t border-slate-800 shrink-0 mt-auto transition-all" style="background-color: var(--color-sidebar-bg, #111827);">
        <div class="flex items-center gap-2" :class="{ 'justify-between': expanded, 'justify-center flex-col': !expanded }">
            <a href="{{ route('profile.index') }}"
               class="flex items-center gap-2.5 min-w-0 rounded-xl p-1 -m-1 transition-colors hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400"
               :class="{ 'flex-col text-center': !expanded }"
               @click="if(window.innerWidth < 1024) window.dispatchEvent(new CustomEvent('close-sidebar-mobile'))">
                <x-user-avatar class="w-9 h-9 shrink-0" text-size="text-xs" />
                <div x-show="expanded" class="min-w-0 flex-1">
                    <p class="text-xs font-extrabold text-white truncate">{{ auth()->user()->Username ?? auth()->user()->Name ?? 'Pengguna' }}</p>
                    <p class="text-[10px] font-bold uppercase tracking-wider truncate" style="color: var(--color-primary, #38BDF8);">{{ \App\Support\Presentation\IndonesianPresentation::role(auth()->user()->Role ?? $userRole) }}</p>
                </div>
            </a>
            <form action="{{ route('logout') }}" method="POST" class="shrink-0">
                @csrf
                <button type="submit" class="p-2 text-rose-400 hover:text-white hover:bg-rose-600/30 rounded-xl transition-all flex items-center justify-center min-h-[36px] min-w-[36px]" title="Keluar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                </button>
            </form>
        </div>
    </div>
</aside>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('sidebarEngine', (initialActiveGroup) => {
            let initialGroups = [];
            try {
                const saved = localStorage.getItem('wms_sidebar_groups');
                initialGroups = saved ? JSON.parse(saved) : [];
                if (!Array.isArray(initialGroups)) initialGroups = [];
            } catch(e) {
                initialGroups = [];
            }

            return {
                expanded: localStorage.getItem('wms_sidebar_collapsed') !== 'true',
                mobileOpen: false,
                openGroups: initialGroups,
                
                init() {
                    if (initialActiveGroup && !this.openGroups.includes(initialActiveGroup)) {
                        this.openGroups.push(initialActiveGroup);
                        this.saveGroups();
                    }

                    const publishState = (open) => {
                        const locked = Boolean(open) && window.innerWidth < 1024;
                        document.body.classList.toggle('wms-mobile-drawer-open', locked);
                        document.dispatchEvent(new CustomEvent('wms-sidebar-mobile-state', { detail: { open: Boolean(open) } }));
                    };

                    this.$watch('mobileOpen', (open) => {
                        if (open && window.innerWidth < 1024) {
                            this.expanded = true;
                            this.$nextTick(() => {
                                const firstLink = document.querySelector('#sidebar a[href]');
                                if (firstLink) firstLink.focus({ preventScroll: true });
                            });
                        }
                        publishState(open);
                    });

                    window.addEventListener('toggle-sidebar-mobile', () => {
                        this.mobileOpen = !this.mobileOpen;
                    });
                    window.addEventListener('close-sidebar-mobile', () => {
                        this.mobileOpen = false;
                    });
                    window.addEventListener('keydown', (event) => {
                        if (event.key === 'Escape') {
                            this.mobileOpen = false;
                        }
                    });
                    window.addEventListener('resize', () => {
                        if (window.innerWidth >= 1024) {
                            this.mobileOpen = false;
                        }
                    });

                    publishState(this.mobileOpen);
                },
            
            toggleExpanded() {
                this.expanded = !this.expanded;
                localStorage.setItem('wms_sidebar_collapsed', (!this.expanded).toString());
            },
            
            toggleGroup(groupName) {
                if (this.openGroups.includes(groupName)) {
                    this.openGroups = this.openGroups.filter(g => g !== groupName);
                } else {
                    this.openGroups.push(groupName);
                }
                this.saveGroups();
                },
                
                isGroupOpen(groupName) {
                    return this.openGroups.includes(groupName);
                },
                
                saveGroups() {
                    try {
                        localStorage.setItem('wms_sidebar_groups', JSON.stringify(this.openGroups));
                    } catch(e) {
                        // ignore
                    }
                }
            };
        });
    });
</script>
