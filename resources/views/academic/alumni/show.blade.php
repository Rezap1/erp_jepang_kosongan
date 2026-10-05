@extends('layouts.app')

@section('header', 'Detail Alumni')

@section('content')
@php
    $isActive = \App\Helpers\SheetValue::isActive((array) $student);
    $isLegacy = !empty($student['Is_Legacy']);
    $isWms = strtoupper((string) ($student['Source_Type'] ?? '')) === 'WMS';
    $initial = strtoupper(substr(trim((string) ($student['Full_Name'] ?? 'A')), 0, 1));
@endphp
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 md:flex-row md:items-start md:justify-between">
        <div class="flex items-start gap-4">
            <a href="{{ route('alumni.index') }}" class="mt-1 text-sm font-black text-indigo-700 hover:text-indigo-900">&larr;</a>
            @if(!empty($student['Photo_URL']))
                <img src="{{ $student['Photo_URL'] }}" alt="Foto {{ $student['Full_Name'] ?? 'Alumni' }}" class="h-20 w-20 rounded-lg border border-slate-200 object-cover">
            @else
                <div class="flex h-20 w-20 items-center justify-center rounded-lg bg-indigo-50 text-2xl font-black text-indigo-700">{{ $initial }}</div>
            @endif
            <div>
                <p class="text-xs font-black uppercase tracking-[0.18em] text-indigo-600">Profil Alumni</p>
                <h1 class="mt-1 text-2xl font-black text-slate-900">{{ $student['Full_Name'] ?? '-' }}</h1>
                <p class="mt-1 font-mono text-xs text-slate-500">{{ $student['Alumni_ID'] ?? '-' }} @if(!empty($student['Student_ID'])) · Student {{ $student['Student_ID'] }} @endif</p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-full px-3 py-1.5 text-xs font-black {{ $isLegacy ? 'bg-amber-50 text-amber-700' : ($isActive ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600') }}">{{ $isLegacy ? 'Legacy' : ($isActive ? 'Aktif' : 'Nonaktif') }}</span>
            <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-black text-slate-700">{{ $isLegacy ? 'Legacy WMS' : ($isWms ? 'Dari Siswa WMS' : 'Input Manual') }}</span>
            @if(!$isLegacy)
                <a href="{{ route('alumni.edit', $student['Alumni_ID']) }}" class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50">Edit</a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <section class="space-y-6 lg:col-span-2">
            <div class="border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="border-b border-slate-100 pb-3 text-sm font-black text-slate-900">Data identitas</h2>
                <dl class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">NIK</dt><dd class="mt-1 font-mono text-slate-800">{{ filled($student['NIK'] ?? $student['National_ID'] ?? null) ? ($student['NIK'] ?? $student['National_ID']) : 'Belum diisi' }}</dd></div>
                    <div><dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Nama orang tua</dt><dd class="mt-1 font-semibold text-slate-800">{{ filled($student['Parent_Name'] ?? null) ? $student['Parent_Name'] : 'Belum diisi' }}</dd></div>
                    <div><dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Nomor Visa</dt><dd class="mt-1 font-mono text-slate-800">{{ filled($student['Visa_Number'] ?? null) ? $student['Visa_Number'] : 'Belum diisi' }}</dd></div>
                    <div><dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Jenis Visa</dt><dd class="mt-1 font-semibold text-slate-800">{{ filled($student['Visa_Type'] ?? null) ? $student['Visa_Type'] : 'Belum tersedia' }}</dd></div>
                    @php
                        $pathwayClass = match ($student['Pathway'] ?? null) {
                            'TG' => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
                            'MAGANG' => 'bg-amber-50 text-amber-700 ring-amber-200',
                            'ENGINEER' => 'bg-cyan-50 text-cyan-700 ring-cyan-200',
                            default => 'bg-slate-100 text-slate-600 ring-slate-200',
                        };
                    @endphp
                    <div><dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Jalur</dt><dd class="mt-1"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-black ring-1 {{ $pathwayClass }}">{{ $student['Pathway_Label'] ?? 'Belum diketahui' }}</span></dd></div>
                    <div><dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Bidang Pekerjaan</dt><dd class="mt-1 font-semibold text-slate-800">{{ filled($student['Work_Field'] ?? null) ? $student['Work_Field'] : 'Belum tersedia' }}</dd></div>
                    <div><dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Wilayah Kerja</dt><dd class="mt-1 font-semibold text-slate-800">{{ filled($student['Work_Region'] ?? null) ? $student['Work_Region'] : 'Belum tersedia' }}</dd></div>
                    <div><dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Tanggal keberangkatan</dt><dd class="mt-1 font-semibold text-slate-800">{{ filled($student['Departure_Date'] ?? null) ? $student['Departure_Date'] : 'Belum diisi' }}</dd></div>
                    <div><dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Email</dt><dd class="mt-1 break-all text-slate-800">{{ $student['Email'] ?? '-' }}</dd></div>
                    <div><dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Nomor telepon</dt><dd class="mt-1 text-slate-800">{{ $student['Phone_Number'] ?? '-' }}</dd></div>
                    <div><dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Status Student</dt><dd class="mt-1 text-slate-800">{{ $student['Enrollment_Status'] ?? 'Tidak terhubung' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Alamat lengkap di Indonesia</dt><dd class="mt-1 whitespace-pre-line text-slate-800">{{ filled($student['Indonesia_Address'] ?? $student['Address'] ?? null) ? ($student['Indonesia_Address'] ?? $student['Address']) : 'Belum diisi' }}</dd></div>
                </dl>
            </div>

            <div class="border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="border-b border-slate-100 pb-3 text-sm font-black text-slate-900">Snapshot pendidikan</h2>
                <dl class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-3">
                    <div><dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Program</dt><dd class="mt-1 font-bold text-indigo-700">{{ $student['Program_Name'] ?? '-' }}</dd></div>
                    <div><dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Angkatan</dt><dd class="mt-1 font-semibold text-slate-800">{{ $student['Batch_Name'] ?? '-' }}</dd></div>
                    <div><dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Kelas terakhir</dt><dd class="mt-1 font-semibold text-slate-800">{{ $student['Class_Name'] ?? '-' }}</dd></div>
                    <div><dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Program_ID</dt><dd class="mt-1 font-mono text-xs text-slate-600">{{ $student['Program_ID'] ?? '-' }}</dd></div>
                    <div><dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Batch_ID</dt><dd class="mt-1 font-mono text-xs text-slate-600">{{ $student['Batch_ID'] ?? '-' }}</dd></div>
                    <div><dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Class_ID</dt><dd class="mt-1 font-mono text-xs text-slate-600">{{ $student['Class_ID'] ?? '-' }}</dd></div>
                </dl>
                @if($isLegacy)
                    <p class="mt-4 border-t border-slate-100 pt-3 text-xs text-amber-700">Data legacy bersifat read-only. Lengkapi Parent Name, alamat Indonesia, visa, kota Jepang, dan tanggal keberangkatan setelah registry MASTER_ALUMNI tersedia.</p>
                @elseif(!$isWms)
                    <p class="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-500">Data ini berasal dari Input Manual dan tidak membuat Student atau User baru.</p>
                @endif
            </div>
        </section>

        <aside class="space-y-6">
            <section class="border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="border-b border-slate-100 pb-3 text-sm font-black text-slate-900">Dokumen terkait</h2>
                @if(!empty($documents))
                    <div class="mt-4 space-y-2">
                        @foreach($documents as $document)
                            <div class="flex items-center justify-between gap-3 border border-slate-100 bg-slate-50 p-3">
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-bold text-slate-800">{{ $document['Document_Type'] ?? 'Dokumen' }}</p>
                                    <p class="mt-0.5 text-[10px] text-slate-400">{{ $document['Created_At'] ?? '' }}</p>
                                </div>
                                @if(!empty($document['File_URL']))
                                    <a href="{{ asset($document['File_URL']) }}" target="_blank" rel="noopener" class="shrink-0 text-xs font-bold text-indigo-700 hover:underline">Buka</a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="mt-4 border border-dashed border-slate-200 bg-slate-50 p-4 text-center text-xs text-slate-500">{{ $isWms ? 'Belum ada dokumen terkait Student.' : 'Input Manual tidak memiliki riwayat dokumen Student.' }}</p>
                @endif
            </section>

            <section class="border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="border-b border-slate-100 pb-3 text-sm font-black text-slate-900">Riwayat nilai</h2>
                @if(!empty($scores))
                    <div class="mt-4 max-h-64 space-y-2 overflow-y-auto">
                        @foreach($scores as $score)
                            <div class="flex items-center justify-between gap-3 border border-slate-100 bg-slate-50 p-2.5 text-xs">
                                <span class="truncate font-semibold text-slate-700">{{ $score['Subject_Name'] ?? $score['Subject_ID'] ?? 'Mata pelajaran' }}</span>
                                <span class="font-black text-indigo-700">{{ $score['Score'] ?? $score['Grade'] ?? '-' }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="mt-4 border border-dashed border-slate-200 bg-slate-50 p-4 text-center text-xs text-slate-500">{{ $isWms ? 'Belum ada nilai yang dapat ditampilkan.' : 'Input Manual tidak memiliki riwayat nilai Student.' }}</p>
                @endif
            </section>
        </aside>
    </div>
</div>
@endsection
