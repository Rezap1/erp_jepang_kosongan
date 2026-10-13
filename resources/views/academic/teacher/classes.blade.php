@extends('layouts.app')
@section('header', 'Kelas Saya')

@section('content')
<div class="space-y-6">
    <x-page-header 
        title="Kelas Saya" 
        description="Daftar kelas wali aktif sesuai penugasan di Master Kelas."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Kelas Saya' => '#']"
    />

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($classes as $c)
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden hover:shadow-md transition-shadow">
                <div class="p-5 border-b border-slate-100 bg-slate-50 flex justify-between items-start">
                    <div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 uppercase">
                            {{ $c['Class_Code'] ?? 'Kode kelas belum tersedia' }}
                        </span>
                        <h3 class="mt-2 text-lg font-extrabold text-slate-900">{{ $c['Class_Name'] ?? 'Tanpa Nama' }}</h3>
                    </div>
                </div>
                <div class="p-5 space-y-3">
                    <div class="flex items-start text-sm">
                        <svg class="w-5 h-5 text-slate-400 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        <div>
                            <span class="block text-slate-500 text-xs font-medium">Batch</span>
                            <span class="font-bold text-slate-800">{{ $c['Batch_Name'] ?? 'Batch tidak ditemukan' }}</span>
                        </div>
                    </div>
                    <div class="flex items-start text-sm">
                        <svg class="w-5 h-5 text-slate-400 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <div>
                            <span class="block text-slate-500 text-xs font-medium">Status</span>
                            <span class="font-bold {{ strtoupper($c['Is_Active'] ?? '') === 'TRUE' ? 'text-emerald-600' : 'text-rose-600' }}">
                                {{ strtoupper($c['Is_Active'] ?? '') === 'TRUE' ? 'Aktif' : 'Tidak Aktif' }}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 flex justify-between items-center gap-2">
                    <a href="{{ route('teacher.workspace.classes.students', $c['Class_ID']) }}" class="flex-1 text-center text-sm py-2 bg-white border border-slate-200 rounded-lg font-bold text-slate-700 hover:bg-slate-50 hover:text-blue-600 transition-colors">Daftar Siswa</a>
                    <a href="{{ route('teacher.workspace.classes.attendance', $c['Class_ID']) }}" class="flex-1 text-center text-sm py-2 bg-blue-50 border border-blue-100 rounded-lg font-bold text-blue-700 hover:bg-blue-100 transition-colors">Kehadiran</a>
                </div>
            </div>
        @empty
            <div class="col-span-full">
                <x-empty-state 
                    icon="view-boards" 
                    title="Belum ada kelas yang ditugaskan" 
                    message="Saat ini belum ada kelas wali aktif yang ditugaskan kepada Anda di Master Kelas."
                />
            </div>
        @endforelse
    </div>
</div>
@endsection
