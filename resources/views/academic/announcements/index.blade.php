@extends('layouts.app')

@section('header', 'Pusat Pengumuman')

@section('content')
<div class="space-y-6">
    <x-page-header 
        title="Pusat Pengumuman"
        description="Kelola dan sebarkan informasi penting ke seluruh atau sebagian warga sekolah."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Academic' => '#', 'Announcement' => route('announcements.index')]"
    >
        <x-slot:actions>
            <x-universal.multi-export route-prefix="announcements" />
            <x-button as="a" href="{{ route('announcements.index') }}" variant="secondary" title="Refresh Data">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            </x-button>
            @if(in_array($userRole ?? 'ADMINISTRATOR', ['ADMINISTRATOR', 'ACADEMIC', 'MASTER', 'TEACHER']))
                <x-button as="a" href="{{ route('announcements.create') }}" variant="primary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    Buat Pengumuman Baru
                </x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 bg-white p-4 rounded-2xl shadow-sm border border-slate-200">
        <div>
            <x-input id="searchInput" placeholder="Cari Judul atau Konten Pengumuman..." icon="<svg class='w-4 h-4' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'></path></svg>" />
        </div>
        <div>
            <x-select id="targetFilter">
                <option value="ALL">Semua Target</option>
                <option value="ALL_ROLES">Semua Role</option>
                <option value="ROLE">Role Tertentu</option>
            </x-select>
        </div>
    </div>

    @if(count($announcements) === 0)
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-12 text-center">
            <div class="w-16 h-16 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
            </div>
            <h3 class="text-lg font-bold text-slate-800 mb-1">Belum Ada Pengumuman</h3>
            <p class="text-slate-500 text-sm">Tidak ada data pengumuman yang dapat ditampilkan saat ini.</p>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            @foreach($announcements as $ann)
                @php
                    $target = $ann['Audience_Label'] ?? ((($ann['Target_Role'] ?? 'ALL') === 'CLASS') ? 'Kelas Tertentu' : 'Semua Siswa');
                    $targetColor = match($target) {
                        'Kelas Tertentu' => 'blue',
                        'Semua Siswa' => 'gray',
                        default => 'cyan'
                    };
                    
                    $priority = $ann['Priority_Label'] ?? 'Informasi';
                    $priorityColor = $priority === 'Mendesak' ? 'red' : ($priority === 'Penting' ? 'amber' : 'blue');
                @endphp
                
                <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm hover:shadow-md hover:border-slate-200 transition-all duration-300 filter-item group relative"
                     data-search="{{ strtolower(($ann['Title'] ?? '').($ann['Message'] ?? $ann['Content'] ?? '')) }}"
                     data-target="{{ $ann['Audience_Type'] ?? 'ROLE' }}">
                     
                    @if($ann['Can_Manage'] ?? false)
                        <div class="absolute top-4 right-4 flex gap-2 opacity-100 transition-opacity sm:opacity-0 sm:group-hover:opacity-100">
                            <a href="{{ route('announcements.show', $ann['Announcement_ID']) }}" class="p-2 bg-white text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg shadow-sm border border-slate-200 block transition-colors" title="Lihat Pengumuman">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            </a>
                            <form action="{{ route('announcements.deactivate', $ann['Announcement_ID']) }}" method="POST" class="inline-block" onsubmit="return confirm('Nonaktifkan pengumuman ini? Riwayat tetap disimpan.');">
                                @csrf
                                <button type="submit" class="p-2 bg-white text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg shadow-sm border border-slate-200 block transition-colors" title="Nonaktifkan">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </form>
                        </div>
                    @endif
                     
                    <div class="flex items-center gap-2 mb-3">
                        <x-badge color="{{ $priorityColor }}" class="uppercase text-[10px] font-bold">{{ $priority }}</x-badge>
                        <x-badge color="{{ $targetColor }}" class="text-[10px]"><span class="font-normal text-slate-500 mr-1">Target:</span>{{ $target }}</x-badge>
                        <x-badge color="{{ ($ann['Status_Label'] ?? '') === 'Aktif' ? 'green' : 'gray' }}" class="text-[10px]">{{ $ann['Status_Label'] ?? 'Aktif' }}</x-badge>
                    </div>
                    
                    <h3 class="font-bold text-lg text-slate-800 mb-2 leading-tight group-hover:text-primary-600 transition-colors">{{ $ann['Title'] ?? 'No Title' }}</h3>
                    
                    <div class="text-[11px] font-medium text-slate-500 flex items-center mb-4">
                        <svg class="w-3.5 h-3.5 mr-1 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        Mulai: {{ $ann['Start_Label'] ?? $ann['Publish_Date'] ?? '-' }} · Berakhir: {{ $ann['Expiry_Label'] ?? $ann['Expired_Date'] ?? '-' }}
                    </div>
                    
                    <p class="text-sm text-slate-600 line-clamp-3 leading-relaxed">
                        {{ $ann['Message'] ?? $ann['Content'] ?? '' }}
                    </p>
                    <div class="mt-5 flex items-center justify-between border-t border-slate-100 pt-4 text-xs font-bold text-slate-500"><span>Pembuat: {{ $ann['Created_By'] ?? 'WMS' }}</span><span>💬 {{ $ann['Comment_Count'] ?? 0 }} Komentar</span></div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const targetFilter = document.getElementById('targetFilter');
        const items = document.querySelectorAll('.filter-item');

        function filterAnnouncements() {
            if(!searchInput || !targetFilter) return;
            
            const searchTerm = searchInput.value.toLowerCase();
            const targetValue = targetFilter.value;

            items.forEach(item => {
                const searchString = item.getAttribute('data-search');
                const itemTarget = item.getAttribute('data-target');
                
                const matchesSearch = searchString.includes(searchTerm);
                
                // Logic for ALL/ALL_USERS cross-matching
                let matchesTarget = false;
                if (targetValue === 'ALL') {
                    matchesTarget = true;
                } else {
                    matchesTarget = targetValue === itemTarget;
                }
                
                if (matchesSearch && matchesTarget) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        if (searchInput) searchInput.addEventListener('input', filterAnnouncements);
        if (targetFilter) targetFilter.addEventListener('change', filterAnnouncements);
    });
</script>
@endsection



