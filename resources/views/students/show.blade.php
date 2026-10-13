@extends('layouts.app')
@section('header', 'Profil Siswa')
@section('content')

@php
    $isActive = ($student['Is_Active'] ?? 'TRUE') === 'TRUE';
    $statusColor = $isActive ? 'green' : 'red';
    $statusText = $isActive ? 'Aktif' : 'Tidak Aktif';
    $emptyLabel = 'Belum diisi';
    $display = static fn ($value) => trim((string) $value) !== '' ? $value : $emptyLabel;
    $birthDate = $student['Birth_Date'] ?? null;
    $age = \App\Support\Presentation\IndonesianPresentation::age($birthDate);
@endphp

<div class="max-w-6xl mx-auto">
    <x-universal.detail-layout 
        title="{{ $student['Full_Name'] }}" 
        description="NIS: {{ $student['Student_Number'] }}"
        status="{{ $statusText }}"
        badgeColor="{{ $statusColor }}"
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Master' => '#', 'Siswa' => route('students.index'), 'Profil' => '#']"
    >
        <x-slot:actions>
            @php
                $gradStatus = strtolower(trim($student['Graduation_Status'] ?? ''));
                $isGraduated = in_array($gradStatus, ['lulus', 'graduated', 'completed']);
            @endphp
            @if(!empty($alumni['Alumni_ID'] ?? null))
                <a href="{{ route('alumni.show', $alumni['Alumni_ID']) }}" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-xs rounded-lg transition-colors shadow-sm flex items-center gap-1.5">
                    Profil Alumni
                </a>
            @elseif($isActive)
                <button type="button" onclick="if(confirm('Buka form registry untuk mencatat keberangkatan siswa ini?')) { document.getElementById('graduationForm').submit(); }" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-xs rounded-lg transition-colors shadow-sm flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0112 20.055a11.952 11.952 0 01-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                    </svg>
                    Catat sebagai Alumni
                </button>
                <form id="graduationForm" action="{{ route('students.graduate', $student['Student_ID']) }}" method="POST" class="hidden">
                    @csrf
                </form>
            @else
                <span class="px-3 py-1.5 bg-slate-100 text-slate-500 font-medium text-xs rounded-lg">
                    Student nonaktif
                </span>
            @endif
            <x-universal.action-button action="edit" url="{{ route('students.edit', $student['Student_ID']) }}" />
            @if($isActive)
                <x-universal.action-button action="delete" url="{{ route('students.destroy', $student['Student_ID']) }}" />
            @endif
        </x-slot:actions>

        <x-slot:information>
            <div class="space-y-8">
                <div>
                    <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Identitas & Penempatan Akademik</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">ID Siswa / NIS</p>
                            <p class="text-sm font-bold text-slate-800 mt-1 break-words">{{ $display($student['Student_Number'] ?? null) }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Program</p>
                            <p class="text-sm font-bold text-slate-800 mt-1">{{ $display($student['Program_Name'] ?? null) }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Angkatan / Batch</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $display($student['Batch_Name'] ?? null) }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Kelas</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $display($student['Class_Name'] ?? null) }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Tanggal Pendaftaran</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ \App\Support\Presentation\IndonesianPresentation::date($student['Registration_Date'] ?? null, 'j F Y', $emptyLabel) }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Status Pendidikan</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ \App\Support\Presentation\IndonesianPresentation::status($student['Enrollment_Status'] ?? null, $emptyLabel) }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Status Kelulusan</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ \App\Support\Presentation\IndonesianPresentation::status($student['Graduation_Status'] ?? null, $emptyLabel) }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Status Sistem</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $statusText }}</p>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Data Kependudukan</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">NIK / No. KTP</p>
                            <p class="text-sm font-medium text-slate-800 mt-1 break-words">{{ $display($student['National_ID'] ?? null) }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Jenis Kelamin</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ \App\Support\Presentation\IndonesianPresentation::gender($student['Gender'] ?? null, $emptyLabel) }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Tempat, Tanggal Lahir</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">
                                {{ $display($student['Birth_Place'] ?? null) }}<br>
                                {{ \App\Support\Presentation\IndonesianPresentation::date($birthDate, 'j F Y', $emptyLabel) }}
                                @if($age !== null)
                                    <span class="text-slate-500">({{ $age }} tahun)</span>
                                @endif
                            </p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Pendidikan Terakhir</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $display($student['Education'] ?? null) }}</p>
                        </div>
                    </div>
                </div>
                
                <div>
                    <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Kontak & Alamat</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Nomor Telepon</p>
                            <p class="text-sm font-medium text-slate-800 mt-1 break-words">{{ $display($student['Phone_Number'] ?? null) }}</p>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Email</p>
                            <p class="text-sm font-medium text-slate-800 mt-1 break-all">{{ $display($student['Email'] ?? null) }}</p>
                        </div>
                        <div class="sm:col-span-2 bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-400 uppercase">Alamat Tinggal</p>
                            <p class="text-sm font-medium text-slate-800 mt-1 whitespace-pre-line">{{ $display($student['Address'] ?? null) }}</p>
                        </div>
                    </div>
                </div>

                @if(!empty($student['Notes']))
                <div>
                    <h3 class="text-sm font-bold text-slate-700 mb-4 pb-2 border-b border-slate-100">Catatan Khusus</h3>
                    <p class="text-sm text-slate-800 bg-slate-50 p-4 rounded-xl border border-slate-200">{{ $student['Notes'] }}</p>
                </div>
                @endif
            </div>
        </x-slot:information>

        <x-slot:audit>
            <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Data Dibuat</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ \App\Support\Presentation\IndonesianPresentation::date($student['Created_At'] ?? null, 'j M Y, H:i', $emptyLabel) }}</p>
                        <p class="text-xs text-slate-500 mt-1">Oleh: {{ \App\Helpers\UserResolverHelper::getName($student['Created_By'] ?? '') }}</p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs font-bold text-slate-400 uppercase">Terakhir Diperbarui</p>
                        <p class="text-sm font-medium text-slate-800 mt-1">{{ \App\Support\Presentation\IndonesianPresentation::date($student['Updated_At'] ?? null, 'j M Y, H:i', $emptyLabel) }}</p>
                        <p class="text-xs text-slate-500 mt-1">Oleh: {{ $student['Updated_By'] ?? 'Sistem' }}</p>
                    </div>
                </div>
            </div>
        </x-slot:audit>

    </x-universal.detail-layout>
</div>
@endsection
