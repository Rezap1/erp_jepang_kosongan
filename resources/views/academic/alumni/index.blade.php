@extends('layouts.app')

@section('header', 'Direktori Alumni')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.18em] text-indigo-600">Registry Alumni</p>
            <h1 class="mt-1 text-2xl font-black text-slate-900">Direktori Alumni</h1>
            <p class="mt-1 max-w-3xl text-sm text-slate-500">
                Setiap kartu berasal dari pencatatan keberangkatan resmi. Alumni tidak dibuat otomatis hanya karena kelas berakhir atau status kelulusan berubah.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('alumni.export-csv', array_merge(request()->except('page'), ['status' => request('status', 'active')])) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">
                Export CSV
            </a>
            <span class="rounded-lg bg-emerald-50 px-3 py-2.5 text-sm font-black text-emerald-700">
                {{ $totalAlumni }} hasil direktori
            </span>
            <a href="{{ route('alumni.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700">
                <span aria-hidden="true">+</span>
                Tambah Alumni
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

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Ringkasan Alumni">
        <article class="border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex items-start justify-between gap-3"><div><p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Total Alumni</p><p class="mt-2 text-3xl font-black text-slate-900">{{ number_format($analytics['total']) }}</p></div><span class="mt-1 h-2.5 w-2.5 rounded-full bg-indigo-500"></span></div>
        </article>
        <article class="border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex items-start justify-between gap-3"><div><p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Tokutei Ginou</p><p class="mt-2 text-3xl font-black text-slate-900">{{ number_format($analytics['tg']) }}</p></div><span class="mt-1 h-2.5 w-2.5 rounded-full bg-sky-500"></span></div>
        </article>
        <article class="border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex items-start justify-between gap-3"><div><p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Magang</p><p class="mt-2 text-3xl font-black text-slate-900">{{ number_format($analytics['magang']) }}</p></div><span class="mt-1 h-2.5 w-2.5 rounded-full bg-amber-500"></span></div>
        </article>
        <article class="border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex items-start justify-between gap-3"><div><p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Wilayah Kerja</p><p class="mt-2 text-3xl font-black text-slate-900">{{ number_format($analytics['total_regions']) }}</p></div><span class="mt-1 h-2.5 w-2.5 rounded-full bg-emerald-500"></span></div>
        </article>
    </section>

    <section class="grid grid-cols-1 gap-4 xl:grid-cols-5" aria-label="Analitik Alumni">
        <article class="border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
            <div class="flex items-start justify-between gap-4">
                <div><p class="text-[10px] font-black uppercase tracking-[0.16em] text-indigo-600">Komposisi jalur</p><h2 class="mt-1 text-sm font-black text-slate-900">TG vs Magang</h2></div>
                @if($analytics['unknown_pathway'] > 0)<span class="rounded bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-600">{{ $analytics['unknown_pathway'] }} belum diketahui</span>@endif
            </div>
            <div class="mt-4 h-56"><canvas id="pathwayChart" aria-label="Grafik perbandingan Tokutei Ginou dan Magang" role="img"></canvas></div>
        </article>
        <article class="border border-slate-200 bg-white p-5 shadow-sm xl:col-span-3">
            <div class="flex items-start justify-between gap-4">
                <div><p class="text-[10px] font-black uppercase tracking-[0.16em] text-indigo-600">Sebaran penempatan</p><h2 class="mt-1 text-sm font-black text-slate-900">Alumni berdasarkan wilayah kerja</h2></div>
                @if($analytics['unknown_region'] > 0)<span class="rounded bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-600">{{ $analytics['unknown_region'] }} tanpa wilayah</span>@endif
            </div>
            @if(count($analytics['region_chart']['labels']) > 0)
                <div class="mt-4 h-56"><canvas id="regionChart" aria-label="Grafik Alumni berdasarkan wilayah kerja" role="img"></canvas></div>
            @else
                <div class="mt-4 flex h-56 items-center justify-center border border-dashed border-slate-200 bg-slate-50 px-5 text-center text-sm text-slate-500">Belum ada wilayah kerja yang dapat diagregasi.</div>
            @endif
        </article>
    </section>

    <form method="GET" action="{{ route('alumni.index') }}" class="grid grid-cols-1 gap-3 border-y border-slate-200 bg-white py-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-12">
        <label class="sm:col-span-2">
            <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Cari</span>
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Nama, NIK, visa, wilayah, ID..." class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
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
        <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-1 xl:col-span-12 xl:justify-end">
            <button type="submit" class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-slate-900 px-3 py-2 text-sm font-bold text-white transition hover:bg-slate-700">
                Terapkan
            </button>
            @if(request()->anyFilled(['search', 'program', 'batch', 'class', 'source', 'pathway', 'visa_type', 'region', 'departure_year', 'status']))
                <a href="{{ route('alumni.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50">Reset</a>
            @endif
        </div>
    </form>

    @if($alumni->count() > 0)
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($alumni as $row)
                @php
                    $isLegacy = !empty($row['Is_Legacy']);
                    $isActive = \App\Helpers\SheetValue::isActive((array) $row);
                    $initial = strtoupper(substr(trim((string) ($row['Full_Name'] ?? 'A')), 0, 1));
                @endphp
                <article class="flex min-h-[330px] flex-col border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-start gap-3 border-b border-slate-100 p-4">
                        @if(!empty($row['Photo_URL']))
                            <img src="{{ $row['Photo_URL'] }}" alt="Foto {{ $row['Full_Name'] ?? 'Alumni' }}" class="h-14 w-14 shrink-0 rounded-lg border border-slate-200 object-cover">
                        @else
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-lg font-black text-indigo-700">{{ $initial }}</div>
                        @endif
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="truncate text-base font-black text-slate-900">{{ $row['Full_Name'] ?? '-' }}</h2>
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-slate-600">{{ $row['Source_Label'] ?? '-' }}</span>
                            </div>
                            <p class="mt-1 font-mono text-xs text-slate-500">{{ $row['Alumni_ID'] ?? '-' }} @if(!empty($row['Student_ID'])) · {{ $row['Student_ID'] }} @endif</p>
                        </div>
                        <span class="rounded-full px-2 py-1 text-[10px] font-black {{ $isLegacy ? 'bg-amber-50 text-amber-700' : ($isActive ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500') }}">{{ $isLegacy ? 'Legacy' : ($isActive ? 'Aktif' : 'Nonaktif') }}</span>
                    </div>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 p-4 text-sm">
                        <div>
                            <dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Jalur</dt>
                            <dd class="mt-0.5 font-bold text-indigo-700">{{ $row['Pathway_Label'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Wilayah kerja</dt>
                            <dd class="mt-0.5 truncate text-slate-700" title="{{ filled($row['Work_Region'] ?? null) ? $row['Work_Region'] : 'Belum tersedia' }}">{{ filled($row['Work_Region'] ?? null) ? $row['Work_Region'] : 'Belum tersedia' }}</dd>
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
                            <div class="col-span-2 border-t border-slate-100 pt-3">
                                <dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Snapshot pendidikan</dt>
                                <dd class="mt-0.5 text-xs text-slate-600">{{ $row['Program_Name'] ?? '-' }} · {{ $row['Batch_Name'] ?? '-' }} · {{ $row['Class_Name'] ?? '-' }}</dd>
                            </div>
                        @endif
                    </dl>
                    <div class="mt-auto flex items-center justify-between border-t border-slate-100 px-4 py-3">
                        <a href="{{ route('alumni.show', $row['Alumni_ID']) }}" class="text-xs font-black text-indigo-700 hover:text-indigo-900">Lihat detail</a>
                        <div class="flex items-center gap-3">
                            @if(!$isLegacy)
                                <a href="{{ route('alumni.edit', $row['Alumni_ID']) }}" class="text-xs font-bold text-slate-600 hover:text-slate-900">Edit</a>
                            @endif
                            @if($isActive && !$isLegacy)
                                <form method="POST" action="{{ route('alumni.destroy', $row['Alumni_ID']) }}" onsubmit="return confirm('Nonaktifkan data Alumni ini?')">
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
        <div class="border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
            <p class="text-sm font-black text-slate-700">{{ $schemaError ? 'Data belum dapat ditampilkan' : 'Tidak ada Alumni yang sesuai filter' }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ $schemaError ? 'Perbaiki schema MASTER_ALUMNI lalu muat ulang halaman.' : 'Ubah filter atau gunakan tombol Tambah Alumni untuk mencatat keberangkatan.' }}</p>
        </div>
    @endif

    @if($alumni->hasPages())
        <div class="border-t border-slate-200 pt-4">{{ $alumni->links() }}</div>
    @endif
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;

    const analytics = {{ Illuminate\Support\Js::from($analytics) }};
    const pathwayCanvas = document.getElementById('pathwayChart');
    if (pathwayCanvas) {
        new Chart(pathwayCanvas, {
            type: 'doughnut',
            data: {
                labels: analytics.pathway_chart.labels,
                datasets: [{ data: analytics.pathway_chart.values, backgroundColor: ['#4f46e5', '#f59e0b'], borderWidth: 0 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } }
            }
        });
    }

    const regionCanvas = document.getElementById('regionChart');
    if (regionCanvas) {
        new Chart(regionCanvas, {
            type: 'bar',
            data: {
                labels: analytics.region_chart.labels,
                datasets: [{ label: 'Alumni', data: analytics.region_chart.values, backgroundColor: '#4f46e5', borderRadius: 4 }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#e2e8f0' } },
                    y: { grid: { display: false }, ticks: { autoSkip: false } }
                }
            }
        });
    }
});
</script>
@endpush
