@extends('layouts.app')

@section('header', 'Direktori Alumni')

@section('content')
@php
    $total = (int) ($analytics['total'] ?? 0);
    $tg = (int) ($analytics['tg'] ?? 0);
    $magang = (int) ($analytics['magang'] ?? 0);
    $tgPercentage = $total > 0 ? (int) round(($tg / $total) * 100) : 0;
    $magangPercentage = $total > 0 ? (int) round(($magang / $total) * 100) : 0;
    $classifiedTotal = $tg + $magang;
    $regionCount = count($analytics['region_chart']['labels'] ?? []);
    $regionChartHeight = max(280, ($regionCount * 52) + 54);
    $activeFilters = ['search', 'program', 'batch', 'class', 'source', 'pathway', 'visa_type', 'work_field', 'region', 'departure_year', 'status'];
    $hasActiveFilters = request()->anyFilled($activeFilters);
    $pageAlumniIds = collect($alumni->items())->reject(fn ($row) => !empty($row['Is_Legacy']))->pluck('Alumni_ID')->filter()->values()->all();
@endphp
<div class="mx-auto max-w-[1600px] space-y-6" x-data="alumniSelection({{ Illuminate\Support\Js::from($pageAlumniIds) }})">
    <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.2em] text-indigo-600">Alumni Management</p>
            <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">Direktori Alumni</h1>
            <p class="mt-1 text-sm text-slate-500">Data alumni berdasarkan jalur, wilayah kerja, dan status penempatan.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('alumni.export-csv', array_merge(request()->except('page'), ['status' => request('status', 'active')])) }}" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 shadow-sm transition hover:border-indigo-300 hover:text-indigo-700 sm:w-auto">
                Export CSV
            </a>
        </div>
    </div>

    @if($schemaError)
        <div class="border-l-4 border-amber-500 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
            <p class="font-black">Registry Alumni belum tersedia</p>
            <p class="mt-1">{{ $schemaError }}</p>
            <p class="mt-1 text-xs text-amber-800">Tidak ada perubahan schema produksi yang dilakukan oleh aplikasi.</p>
        </div>
    @endif

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan Alumni">
        @php
            $kpis = [
                ['label' => 'Total Alumni', 'value' => $total, 'support' => 'Seluruh alumni terdaftar', 'gradient' => 'from-indigo-600 to-violet-700', 'shadow' => 'shadow-indigo-200/60', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                ['label' => 'Tokutei Ginou', 'value' => $tg, 'support' => $tgPercentage.'% dari total Alumni', 'gradient' => 'from-sky-500 to-cyan-600', 'shadow' => 'shadow-sky-200/60', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
                ['label' => 'Magang', 'value' => $magang, 'support' => $magangPercentage.'% dari total Alumni', 'gradient' => 'from-amber-500 to-orange-600', 'shadow' => 'shadow-orange-200/60', 'icon' => 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
                ['label' => 'Wilayah Kerja', 'value' => $analytics['total_regions'], 'support' => 'Kota berbeda', 'gradient' => 'from-emerald-500 to-teal-600', 'shadow' => 'shadow-emerald-200/60', 'icon' => 'M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z'],
            ];
        @endphp
        @foreach($kpis as $kpi)
            <article class="relative overflow-hidden rounded-2xl bg-gradient-to-br {{ $kpi['gradient'] }} p-5 text-white shadow-lg {{ $kpi['shadow'] }}">
                <div class="absolute -right-5 -top-5 h-28 w-28 rounded-full bg-white/10"></div>
                <div class="relative flex items-start justify-between gap-4"><div><p class="text-[11px] font-black uppercase tracking-[0.18em] text-white/80">{{ $kpi['label'] }}</p><p class="mt-3 text-4xl font-black">{{ number_format($kpi['value']) }}</p><p class="mt-2 text-xs font-semibold text-white/80">{{ $kpi['support'] }}</p></div><span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/20"><svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $kpi['icon'] }}" /></svg></span></div>
            </article>
        @endforeach
    </section>

    <section class="grid grid-cols-1 gap-5 xl:grid-cols-5" aria-label="Analitik Alumni">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 xl:col-span-2">
            <div class="flex items-start justify-between gap-4">
                <div><p class="text-[11px] font-black uppercase tracking-[0.18em] text-indigo-600">Komposisi Jalur</p><h2 class="mt-1 text-lg font-black text-slate-950">TG vs Magang</h2><p class="mt-1 text-xs text-slate-500">Perbandingan jalur penempatan Alumni</p></div>
                @if($analytics['unknown_pathway'] > 0)<span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-600">{{ $analytics['unknown_pathway'] }} belum diketahui</span>@endif
            </div>
            @if($classifiedTotal > 0)
                <div class="mt-5 grid items-center gap-5 sm:grid-cols-[minmax(0,1fr)_minmax(150px,0.8fr)] xl:grid-cols-1 2xl:grid-cols-[minmax(0,1fr)_minmax(150px,0.8fr)]">
                    <div class="mx-auto h-52 w-full max-w-[230px]"><canvas id="pathwayChart" aria-label="Grafik perbandingan Tokutei Ginou dan Magang" role="img"></canvas></div>
                    <div class="space-y-3" aria-label="Legenda komposisi jalur">
                        <div class="rounded-xl border border-indigo-100 bg-indigo-50/70 p-3"><div class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-indigo-600"></span><p class="text-xs font-black text-slate-800">Tokutei Ginou (TG)</p></div><div class="mt-2 flex items-end justify-between"><span class="text-lg font-black text-slate-950">{{ $tg }} <small class="text-xs font-semibold text-slate-500">alumni</small></span><span class="text-sm font-black text-indigo-700">{{ $tgPercentage }}%</span></div></div>
                        <div class="rounded-xl border border-orange-100 bg-orange-50/70 p-3"><div class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-orange-500"></span><p class="text-xs font-black text-slate-800">Magang</p></div><div class="mt-2 flex items-end justify-between"><span class="text-lg font-black text-slate-950">{{ $magang }} <small class="text-xs font-semibold text-slate-500">alumni</small></span><span class="text-sm font-black text-orange-700">{{ $magangPercentage }}%</span></div></div>
                    </div>
                </div>
            @else
                <div class="mt-5 flex min-h-64 flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50 px-6 text-center"><p class="text-sm font-black text-slate-700">Belum ada komposisi jalur</p><p class="mt-1 text-xs text-slate-500">Grafik akan tampil setelah data TG atau Magang tersedia.</p></div>
            @endif
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 xl:col-span-3">
            <div class="flex items-start justify-between gap-4">
                <div><p class="text-[11px] font-black uppercase tracking-[0.18em] text-indigo-600">Sebaran Penempatan</p><h2 class="mt-1 text-lg font-black text-slate-950">Alumni berdasarkan wilayah kerja</h2><p class="mt-1 text-xs text-slate-500">Jumlah Alumni pada setiap kota penempatan</p></div>
                @if($analytics['unknown_region'] > 0)<span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-600">{{ $analytics['unknown_region'] }} tanpa wilayah</span>@endif
            </div>
            @if($regionCount > 0)
                <div class="mt-5 w-full" style="height: {{ $regionChartHeight }}px"><canvas id="regionChart" aria-label="Grafik Alumni berdasarkan wilayah kerja" role="img"></canvas></div>
            @else
                <div class="mt-5 flex min-h-64 flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50 px-6 text-center"><p class="text-sm font-black text-slate-700">Belum ada wilayah kerja</p><p class="mt-1 text-xs text-slate-500">Sebaran kota akan tampil saat data penempatan tersedia.</p></div>
            @endif
        </article>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="filter-title">
        <div class="flex flex-col gap-4 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div><h2 id="filter-title" class="text-base font-black text-slate-950">Filter Data Alumni</h2><p class="mt-1 text-xs text-slate-500">Gunakan filter berikut untuk mencari dan menampilkan data alumni</p></div>
            <div class="flex flex-wrap gap-2">
                @if($hasActiveFilters)<a href="{{ route('alumni.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50">Reset</a>@endif
                <a href="{{ route('alumni.create') }}" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-indigo-600 px-3.5 py-2 text-xs font-bold text-white shadow-sm hover:bg-indigo-700"><span aria-hidden="true">+</span> Tambah Alumni</a>
            </div>
        </div>
    <form method="GET" action="{{ route('alumni.index') }}" class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 sm:p-6 lg:grid-cols-3 xl:grid-cols-5">
        <label class="sm:col-span-2">
            <span class="mb-1.5 block text-xs font-bold text-slate-700">Cari Alumni</span>
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Nama, NIK, visa, kota, ID..." class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm focus:border-indigo-500 focus:bg-white focus:ring-indigo-500">
        </label>
        <label>
            <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Program</span>
            <select name="program" class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Semua program</option>
                @foreach($programs as $program)
                    <option value="{{ $program['Program_ID'] }}" @selected(request('program') === $program['Program_ID'])>{{ $program['Program_Name'] }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Batch</span>
            <select name="batch" class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Semua batch</option>
                @foreach($batches as $batch)
                    <option value="{{ $batch['Batch_ID'] }}" @selected(request('batch') === $batch['Batch_ID'])>{{ $batch['Batch_Name'] }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Kelas</span>
            <select name="class" class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Semua kelas</option>
                @foreach($classes as $class)
                    <option value="{{ $class['Class_ID'] }}" @selected(request('class') === $class['Class_ID'])>{{ $class['Class_Name'] }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Sumber</span>
            <select name="source" class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Semua sumber</option>
                <option value="WMS" @selected(request('source') === 'WMS')>Dari Siswa WMS</option>
                <option value="MANUAL" @selected(request('source') === 'MANUAL')>Input Manual</option>
            </select>
        </label>
        <label>
            <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Jalur</span>
            <select name="pathway" class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Semua jalur</option>
                <option value="TG" @selected(request('pathway') === 'TG')>Tokutei Ginou (TG)</option>
                <option value="MAGANG" @selected(request('pathway') === 'MAGANG')>Magang</option>
            </select>
        </label>
        <label>
            <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Jenis Visa</span>
            <select name="visa_type" class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Semua jenis</option>
                @foreach($visaTypes as $visaType)
                    <option value="{{ $visaType }}" @selected(request('visa_type') === $visaType)>{{ $visaType }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Bidang Pekerjaan</span>
            <select name="work_field" class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Semua bidang</option>
                @foreach($workFields as $workField)
                    <option value="{{ $workField }}" @selected(request('work_field') === $workField)>{{ $workField }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Wilayah</span>
            <select name="region" class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Semua wilayah</option>
                @foreach($regions as $region)
                    <option value="{{ $region }}" @selected(request('region') === $region)>{{ $region }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Tahun berangkat</span>
            <input type="number" min="2000" max="{{ now()->year }}" name="departure_year" value="{{ request('departure_year') }}" placeholder="2026" class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </label>
        <label>
            <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Status data</span>
            <select name="status" class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="active" @selected(request('status', 'active') === 'active')>Aktif</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
                <option value="all" @selected(request('status') === 'all')>Semua</option>
            </select>
        </label>
        <div class="flex items-end gap-2 border-t border-slate-100 pt-4 sm:col-span-2 lg:col-span-3 xl:col-span-5 xl:justify-end">
            <button type="submit" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-slate-700 sm:flex-none">
                Terapkan Filter
            </button>
        </div>
    </form>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm sm:px-5" aria-label="Pilihan Alumni">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-5">
                <p class="text-sm text-slate-600">Menampilkan <strong class="text-slate-900">{{ $alumni->firstItem() ?? 0 }}-{{ $alumni->lastItem() ?? 0 }}</strong> dari <strong class="text-slate-900">{{ $alumni->total() }}</strong> alumni</p>
                <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-bold text-slate-700"><input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" :checked="allSelected" :indeterminate.prop="partiallySelected" :disabled="pageIds.length === 0" @change="toggleAll($event.target.checked)" aria-label="Pilih semua Alumni pada halaman ini"><span>Pilih Semua</span></label>
                <span class="text-xs font-bold text-indigo-700" x-text="`${selected.length} alumni dipilih`">0 alumni dipilih</span>
            </div>
            <form method="POST" action="{{ route('alumni.export-selected-pdf') }}" class="w-full lg:w-auto" @submit="if (selected.length === 0) $event.preventDefault()">
                @csrf
                <template x-for="id in selected" :key="id"><input type="hidden" name="alumni_ids[]" :value="id"></template>
                <button type="submit" :disabled="selected.length === 0" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-500 disabled:shadow-none lg:w-auto">Download PDF</button>
            </form>
        </div>
    </section>

    @if($alumni->count() > 0)
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
            @foreach($alumni as $row)
                @php
                    $isLegacy = !empty($row['Is_Legacy']);
                    $isActive = \App\Helpers\SheetValue::isActive((array) $row);
                    $alumniId = trim((string) ($row['Alumni_ID'] ?? ''));
                    $name = filled($row['Full_Name'] ?? null) ? $row['Full_Name'] : 'Belum tersedia';
                    $initial = mb_strtoupper(mb_substr(trim((string) $name), 0, 1));
                @endphp
                <article class="group flex min-h-[360px] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md" :class="isSelected('{{ $alumniId }}') ? 'border-indigo-400 ring-2 ring-indigo-100 bg-indigo-50/20' : ''">
                    <div class="flex items-start gap-3 border-b border-slate-100 p-4 sm:p-5">
                        <label class="mt-1 inline-flex shrink-0 items-center">
                            <input type="checkbox" value="{{ $alumniId }}" x-model="selected" @disabled($isLegacy) aria-label="{{ $isLegacy ? 'Alumni legacy tidak tersedia untuk PDF' : 'Pilih '.$name }}" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-slate-100">
                        </label>
                        @if(!empty($row['Photo_URL']))
                            <img src="{{ $row['Photo_URL'] }}" alt="Foto {{ $name }}" class="h-14 w-14 shrink-0 rounded-xl border border-slate-200 object-cover shadow-sm">
                        @else
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-100 to-violet-100 text-lg font-black text-indigo-700 ring-1 ring-indigo-200">{{ $initial }}</div>
                        @endif
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="truncate text-base font-black text-slate-950 sm:text-lg">{{ $name }}</h2>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[9px] font-black uppercase tracking-wide text-slate-600">{{ filled($row['Source_Label'] ?? null) ? $row['Source_Label'] : 'Belum tersedia' }}</span>
                            </div>
                            <p class="mt-1 break-all font-mono text-xs text-slate-500">{{ $alumniId ?: 'Belum tersedia' }} @if(!empty($row['Student_ID'])) / {{ $row['Student_ID'] }} @endif</p>
                        </div>
                        <span class="rounded-full px-2 py-1 text-[10px] font-black {{ $isLegacy ? 'bg-amber-50 text-amber-700' : ($isActive ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500') }}">{{ $isLegacy ? 'Legacy' : ($isActive ? 'Aktif' : 'Nonaktif') }}</span>
                    </div>
                    <dl class="grid grid-cols-1 gap-3 p-4 text-sm sm:grid-cols-2 sm:p-5">
                        <div>
                            <dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Jalur</dt>
                            <dd class="mt-0.5 font-bold text-indigo-700">{{ filled($row['Pathway'] ?? null) ? ($row['Pathway_Label'] ?? 'Belum diketahui') : 'Belum tersedia' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Wilayah kerja</dt>
                            <dd class="mt-0.5 break-words font-bold uppercase text-cyan-700">{{ filled($row['Work_Region'] ?? null) ? $row['Work_Region'] : 'Belum tersedia' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Bidang Pekerjaan</dt>
                            <dd class="mt-0.5 break-words font-bold text-slate-800">{{ filled($row['Work_Field'] ?? null) ? $row['Work_Field'] : 'Belum tersedia' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Nomor Visa</dt>
                            <dd class="mt-0.5 break-all font-mono text-slate-700">{{ filled($row['Visa_Number'] ?? null) ? $row['Visa_Number'] : 'Belum tersedia' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Jenis Visa</dt>
                            <dd class="mt-0.5 truncate text-slate-700" title="{{ filled($row['Visa_Type'] ?? null) ? $row['Visa_Type'] : 'Belum tersedia' }}">{{ filled($row['Visa_Type'] ?? null) ? $row['Visa_Type'] : 'Belum tersedia' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">NIK</dt>
                            <dd class="mt-0.5 break-all font-mono text-slate-700">{{ filled($row['NIK'] ?? null) ? $row['NIK'] : 'Belum tersedia' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Keberangkatan</dt>
                            <dd class="mt-0.5 font-bold text-slate-800">{{ filled($row['Departure_Date'] ?? null) ? $row['Departure_Date'] : 'Belum tersedia' }}</dd>
                        </div>
                        @if(!empty($row['Program_Name']) && $row['Program_Name'] !== '-')
                            <div class="border-t border-slate-100 pt-3 sm:col-span-2">
                                <dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Snapshot pendidikan</dt>
                                <dd class="mt-0.5 text-xs text-slate-600">{{ $row['Program_Name'] ?? '-' }} · {{ $row['Batch_Name'] ?? '-' }} · {{ $row['Class_Name'] ?? '-' }}</dd>
                            </div>
                        @endif
                    </dl>
                    <div class="mt-auto flex items-center justify-between border-t border-slate-100 px-4 py-3">
                        <a href="{{ route('alumni.show', $alumniId) }}" class="text-xs font-black text-indigo-700 hover:text-indigo-900">Lihat detail</a>
                        <div class="flex items-center gap-3">
                            @if(!$isLegacy)
                                <a href="{{ route('alumni.edit', $alumniId) }}" class="text-xs font-bold text-slate-600 hover:text-slate-900">Edit</a>
                            @endif
                            @if($isActive && !$isLegacy)
                                <form method="POST" action="{{ route('alumni.destroy', $alumniId) }}" onsubmit="return confirm('Nonaktifkan data Alumni ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-bold text-rose-600 hover:text-rose-800">Nonaktifkan</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @else
        <section class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center shadow-sm">
            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-500"><svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg></span>
            <h2 class="mt-4 text-lg font-black text-slate-900">{{ $schemaError ? 'Data belum dapat ditampilkan' : ($hasActiveFilters ? 'Tidak ada alumni yang sesuai filter' : 'Belum ada data alumni') }}</h2>
            <p class="mx-auto mt-2 max-w-md text-sm text-slate-500">{{ $schemaError ? 'Pastikan schema MASTER_ALUMNI sudah tersedia.' : ($hasActiveFilters ? 'Ubah atau reset filter untuk melihat data lainnya.' : 'Data alumni yang ditambahkan akan muncul di direktori ini.') }}</p>
            @if(!$schemaError && !$hasActiveFilters)<a href="{{ route('alumni.create') }}" class="mt-5 inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700">Tambah Alumni</a>@endif
        </section>
    @endif

    @if($alumni->hasPages())
        <nav class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm" aria-label="Pagination Alumni">{{ $alumni->links() }}</nav>
    @endif
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
window.alumniSelection = function (pageIds) {
    return {
        pageIds: Array.from(new Set(pageIds)),
        selected: [],
        get allSelected() { return this.pageIds.length > 0 && this.pageIds.every(id => this.selected.includes(id)); },
        get partiallySelected() { return this.selected.length > 0 && !this.allSelected; },
        toggleAll(checked) { this.selected = checked ? [...this.pageIds] : []; },
        isSelected(id) { return this.selected.includes(id); }
    };
};

document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;

    const analytics = {{ Illuminate\Support\Js::from($analytics) }};
    const centerTextPlugin = {
        id: 'centerText',
        afterDraw(chart) {
            if (chart.config.type !== 'doughnut') return;
            const { ctx, chartArea } = chart;
            const x = (chartArea.left + chartArea.right) / 2;
            const y = (chartArea.top + chartArea.bottom) / 2;
            ctx.save();
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillStyle = '#0f172a';
            ctx.font = '800 25px sans-serif';
            ctx.fillText(String(analytics.total || 0), x, y - 7);
            ctx.fillStyle = '#64748b';
            ctx.font = '700 10px sans-serif';
            ctx.fillText('ALUMNI', x, y + 14);
            ctx.restore();
        }
    };
    const barValuePlugin = {
        id: 'barValueLabels',
        afterDatasetsDraw(chart) {
            if (chart.config.type !== 'bar') return;
            const { ctx } = chart;
            ctx.save();
            ctx.fillStyle = '#334155';
            ctx.font = '700 12px sans-serif';
            ctx.textBaseline = 'middle';
            chart.getDatasetMeta(0).data.forEach((bar, index) => ctx.fillText(String(chart.data.datasets[0].data[index]), bar.x + 8, bar.y));
            ctx.restore();
        }
    };
    const pathwayCanvas = document.getElementById('pathwayChart');
    if (pathwayCanvas) {
        new Chart(pathwayCanvas, {
            type: 'doughnut',
            plugins: [centerTextPlugin],
            data: {
                labels: analytics.pathway_chart.labels,
                datasets: [{ data: analytics.pathway_chart.values, backgroundColor: ['#4f46e5', '#f97316'], borderColor: '#ffffff', borderWidth: 4, hoverOffset: 5 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: context => ` ${context.label}: ${context.raw} alumni` } } }
            }
        });
    }

    const cityPalette = ['#2563eb', '#db2777', '#059669', '#ea580c', '#7c3aed', '#0891b2', '#e11d48', '#d97706', '#4f46e5', '#0d9488'];
    const cityHash = name => Array.from(String(name).trim().toLocaleUpperCase('id-ID')).reduce((hash, char) => ((hash * 31) + char.codePointAt(0)) >>> 0, 2166136261);
    const cityColors = labels => {
        const assignment = new Map();
        const used = new Set();
        [...labels].sort((a, b) => String(a).localeCompare(String(b), 'id')).forEach(label => {
            const start = cityHash(label) % cityPalette.length;
            let index = start;
            for (let offset = 0; offset < cityPalette.length; offset++) {
                const candidate = (start + offset) % cityPalette.length;
                if (!used.has(candidate)) { index = candidate; break; }
            }
            assignment.set(label, cityPalette[index]);
            used.add(index);
        });
        return labels.map(label => assignment.get(label));
    };
    const wrapCityLabel = value => {
        const limit = window.innerWidth < 640 ? 14 : 22;
        const words = String(value).toLocaleUpperCase('id-ID').split(/\s+/).flatMap(word => word.length > limit ? (word.match(new RegExp(`.{1,${limit}}`, 'g')) || [word]) : [word]);
        const lines = [];
        words.forEach(word => {
            const current = lines[lines.length - 1] || '';
            if (!current || `${current} ${word}`.length > limit) lines.push(word);
            else lines[lines.length - 1] = `${current} ${word}`;
        });
        return lines;
    };
    const regionCanvas = document.getElementById('regionChart');
    if (regionCanvas) {
        new Chart(regionCanvas, {
            type: 'bar',
            plugins: [barValuePlugin],
            data: {
                labels: analytics.region_chart.labels,
                datasets: [{ label: 'Alumni', data: analytics.region_chart.values, backgroundColor: cityColors(analytics.region_chart.labels), borderRadius: 7, borderSkipped: false, barThickness: 22 }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { right: 34 } },
                plugins: { legend: { display: false }, tooltip: { callbacks: { title: items => analytics.region_chart.labels[items[0].dataIndex], label: context => ` ${context.raw} alumni` } } },
                scales: {
                    x: { beginAtZero: true, grace: '18%', ticks: { precision: 0, color: '#64748b' }, grid: { color: '#e2e8f0' }, border: { display: false } },
                    y: { grid: { display: false }, border: { display: false }, ticks: { autoSkip: false, color: '#334155', font: { size: 12, weight: '600' }, callback: function(value) { return wrapCityLabel(this.getLabelForValue(value)); } } }
                }
            }
        });
    }
});
</script>
@endpush
