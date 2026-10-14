@extends('layouts.app')
@section('header', 'Penilaian')

@section('content')
@php
    $assessmentConfigMap = collect($assessmentConfigs ?? []);
@endphp
<div class="min-w-0 space-y-6 pb-28 md:pb-10">
    <x-page-header
        title="Penilaian"
        description="Manajemen nilai siswa pada kelas yang Anda ajar."
        :breadcrumbs="['Dashboard' => route('dashboard.teacher'), 'Penilaian' => '#']"
    />

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <form method="GET" action="{{ route('teacher.workspace.scores') }}" class="grid min-w-0 grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,.8fr)_auto] lg:items-end">
            <label class="min-w-0 text-sm font-black text-slate-700">Kelas
                <select name="class" class="mt-2 min-h-12 w-full min-w-0 rounded-xl border-slate-300">
                    <option value="">Semua Kelas</option>
                    @foreach($reportClasses ?? [] as $class)
                        <option value="{{ $class['Class_ID'] }}" @selected(($selectedClass ?? '') === $class['Class_ID'])>{{ $class['Class_Name'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="min-w-0 text-sm font-black text-slate-700">Mata Pelajaran
                <select name="subject" class="mt-2 min-h-12 w-full min-w-0 rounded-xl border-slate-300">
                    <option value="">Semua Mata Pelajaran</option>
                    @foreach($subjectOptions ?? [] as $subject)
                        <option value="{{ $subject['Subject_ID'] }}" @selected(($selectedSubject ?? '') === $subject['Subject_ID'])>{{ $subject['Subject_Name'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="min-w-0 text-sm font-black text-slate-700">Kategori
                <select name="category" class="mt-2 min-h-12 w-full min-w-0 rounded-xl border-slate-300">
                    <option value="all" @selected(($selectedCategory ?? 'all') === 'all')>Semua</option>
                    <option value="assessment" @selected(($selectedCategory ?? '') === 'assessment')>Assessment</option>
                    <option value="ujian_bab" @selected(($selectedCategory ?? '') === 'ujian_bab')>Ujian Bab</option>
                </select>
            </label>
            <button class="min-h-12 rounded-xl bg-slate-900 px-5 font-black text-white">Tampilkan</button>
        </form>

        <div class="mt-5 flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-black text-slate-900">{{ $selectedClassName ?? 'Kelas' }}</h2>
                <p class="text-sm text-slate-500">{{ $selectedSubjectName ?? 'Semua Mata Pelajaran' }}</p>
            </div>
            <dl class="grid grid-cols-3 gap-2 text-center">
                <div class="rounded-xl bg-slate-50 px-3 py-2"><dt class="text-[11px] font-bold text-slate-500">Siswa</dt><dd class="font-black">{{ $summary['students'] ?? 0 }}</dd></div>
                <div class="rounded-xl bg-emerald-50 px-3 py-2"><dt class="text-[11px] font-bold text-emerald-700">Dinilai</dt><dd class="font-black text-emerald-900">{{ $summary['assessed'] ?? 0 }}</dd></div>
                <div class="rounded-xl bg-amber-50 px-3 py-2"><dt class="text-[11px] font-bold text-amber-700">Belum</dt><dd class="font-black text-amber-900">{{ $summary['unassessed'] ?? 0 }}</dd></div>
            </dl>
        </div>
    </section>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-5 border-b border-slate-100 bg-slate-50 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h3 class="text-lg font-bold text-slate-900">Daftar Penilaian</h3>
                <p class="text-sm text-slate-500 mt-1">Data penilaian dari siswa kelas Anda.</p>
            </div>
            <div>
                <div class="flex flex-wrap gap-2">
                <a href="#score-pdf-filter" class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-bold rounded-xl shadow-sm transition-colors">
                    Pilih Filter PDF
                </a>
                <a href="{{ route('teacher.workspace.scores.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-xl shadow-sm transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Tambah Penilaian
                </a>
                </div>
            </div>
        </div>

        <div id="score-pdf-filter" class="p-5 border-b border-slate-200 bg-red-50/40" x-data="{ reportMode: @js(old('mode', 'student')) }">
            <div class="max-w-4xl">
                <h4 class="text-base font-extrabold text-slate-900">Unduh laporan nilai PDF</h4>
                <p class="mt-1 text-sm text-slate-600">Pilih satu siswa untuk seluruh riwayat nilainya, atau satu kelas pada tanggal tertentu.</p>

                @if(isset($errors) && $errors->hasAny(['mode', 'student_id', 'class_id', 'date']))
                    <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="GET" action="{{ route('teacher.workspace.reports.scores-pdf') }}" class="mt-4 space-y-4">
                    <fieldset>
                        <legend class="text-sm font-bold text-slate-700">Jenis laporan</legend>
                        <div class="mt-2 flex flex-wrap gap-4">
                            <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                                <input type="radio" name="mode" value="student" x-model="reportMode" required @checked(old('mode', 'student') === 'student') class="text-red-600 focus:ring-red-500">
                                Satu siswa
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                                <input type="radio" name="mode" value="class" x-model="reportMode" required @checked(old('mode') === 'class') class="text-red-600 focus:ring-red-500">
                                Satu kelas
                            </label>
                        </div>
                    </fieldset>

                    <div x-show="reportMode === 'student'" x-cloak>
                        <label for="report_student_id" class="block text-sm font-bold text-slate-700">Siswa</label>
                        <select id="report_student_id" name="student_id" :disabled="reportMode !== 'student'" :required="reportMode === 'student'" class="mt-1 block w-full rounded-xl border-slate-300 text-sm focus:border-red-500 focus:ring-red-500">
                            <option value="">Pilih siswa</option>
                            @foreach($reportStudents ?? [] as $student)
                                <option value="{{ $student['Student_ID'] }}" @selected(old('student_id') === $student['Student_ID'])>
                                    {{ $student['Full_Name'] }}{{ $student['Student_Number'] ? ' - ' . $student['Student_Number'] : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div x-show="reportMode === 'class'" x-cloak class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label for="report_class_id" class="block text-sm font-bold text-slate-700">Kelas</label>
                            <select id="report_class_id" name="class_id" :disabled="reportMode !== 'class'" :required="reportMode === 'class'" class="mt-1 block w-full rounded-xl border-slate-300 text-sm focus:border-red-500 focus:ring-red-500">
                                <option value="">Pilih kelas</option>
                                @foreach($reportClasses ?? [] as $class)
                                    <option value="{{ $class['Class_ID'] }}" @selected(old('class_id') === $class['Class_ID'])>{{ $class['Class_Name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="report_date" class="block text-sm font-bold text-slate-700">Tanggal nilai</label>
                            <input id="report_date" type="date" name="date" value="{{ old('date') }}" :disabled="reportMode !== 'class'" :required="reportMode === 'class'" class="mt-1 block w-full rounded-xl border-slate-300 text-sm focus:border-red-500 focus:ring-red-500">
                        </div>
                    </div>

                    <button type="submit" class="inline-flex items-center rounded-xl bg-red-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition-colors hover:bg-red-700">
                        Unduh PDF sesuai filter
                    </button>
                </form>
            </div>
        </div>

        <div class="grid gap-4 bg-slate-50 p-4 md:hidden">
            @forelse(($scoreGroups ?? collect(['Penilaian|' => $scores])) as $groupKey => $groupScores)
                @php
                    [$groupLabel, $groupDate] = array_pad(explode('|', (string) $groupKey, 2), 2, '');
                @endphp
                <div class="col-span-full flex min-w-0 items-end justify-between gap-3 pt-2 first:pt-0">
                    <h3 class="break-words text-sm font-black text-slate-800">{{ $groupLabel }}</h3>
                    @if($groupDate !== '')
                        <span class="shrink-0 text-xs font-semibold text-slate-500">{{ \App\Helpers\DateHelper::format($groupDate, 'd M Y') }}</span>
                    @endif
                </div>
                @foreach($groupScores as $score)
                @php
                    $category = strtoupper(trim((string) ($score['Assessment_Category'] ?? '')));
                    $config = $assessmentConfigMap->get($category);
                    $categoryLabel = $config['Category_Name'] ?? ($category ?: 'Legacy Score');
                    $type = strtoupper(trim((string) ($config['Score_Type'] ?? $config['ScoreType'] ?? $config['Input_Type'] ?? '')));
                    $isNumeric = in_array($type, ['NUMERIC', 'NUMBER', 'SCORE', 'NUMERIC_0_100', '0-100'], true) || $category === 'UJIAN_BAB';
                    $details = json_decode((string) ($score['Evaluation_Details'] ?? ''), true);
                    $details = is_array($details) ? $details : [];
                    $scoreValue = $score['Score'] ?? $score['Score_Value'] ?? '-';
                    $notes = trim((string) ($details['notes'] ?? ''));
                    $aspects = $config && !empty($config['Aspects_JSON']) ? json_decode($config['Aspects_JSON'], true) : [];
                    $aspectMap = collect(is_array($aspects) ? $aspects : [])->pluck('label', 'id')->toArray();
                    $hasAspectDetails = false;
                    foreach ($details as $key => $value) {
                        if (in_array(strtolower((string) $key), ['category', 'notes', 'subject_id'], true)) {
                            continue;
                        }
                        if (isset($aspectMap[$key])) {
                            $hasAspectDetails = true;
                            break;
                        }
                    }
                @endphp
                <article class="min-w-0 space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" x-data="{ open: false }">
                    <div class="min-w-0">
                        <p class="break-words text-base font-black text-slate-900">{{ $score['Student_Name'] ?? 'Data siswa tidak ditemukan' }}</p>
                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ $score['Subject_Name'] ?? 'Mata pelajaran' }} &bull; {{ \App\Helpers\DateHelper::format($score['Display_Date'] ?? $score['Created_At'] ?? now(), 'd M Y') }}</p>
                    </div>
                    <div class="flex items-center justify-between gap-4 rounded-xl bg-slate-50 p-3">
                        <span class="text-sm font-black text-slate-700">{{ $categoryLabel }}</span>
                        @if($isNumeric)
                            <span class="shrink-0 text-2xl font-black text-sky-800">{{ $scoreValue }}</span>
                        @else
                            <span class="shrink-0 rounded-full bg-emerald-100 px-3 py-1 text-xs font-black text-emerald-800">Dinilai</span>
                        @endif
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" @click="open = !open" class="min-h-11 rounded-xl border border-sky-200 bg-sky-50 px-3 text-sm font-black text-sky-800"><span x-show="!open">Lihat Detail</span><span x-show="open">Tutup Detail</span></button>
                        <a href="{{ route('teacher.workspace.scores.edit', $score['Score_ID']) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-3 text-sm font-black text-slate-800">Edit</a>
                    </div>
                    @if($isNumeric)
                        <div x-show="open" class="pt-3 border-t border-slate-100 text-sm space-y-2">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Nilai</span>
                                <span class="font-semibold text-slate-800">{{ $scoreValue }}</span>
                            </div>
                            @if($notes !== '')
                                <div class="mt-2 pt-2 border-t border-slate-50">
                                    <p class="text-xs text-slate-400">Catatan:</p>
                                    <p class="break-words text-slate-700 italic">{{ $notes }}</p>
                                </div>
                            @endif
                        </div>
                    @elseif($hasAspectDetails)
                        <div x-show="open" class="pt-3 border-t border-slate-100 text-sm space-y-2">
                            @php
                                $labels = [1 => 'Sangat Kurang', 2 => 'Kurang', 3 => 'Cukup', 4 => 'Baik', 5 => 'Sangat Baik'];
                            @endphp
                            @foreach($details as $key => $value)
                                @if(!in_array(strtolower((string) $key), ['category', 'notes'], true) && isset($aspectMap[$key]))
                                    <div class="flex justify-between">
                                        <span class="text-slate-500">{{ $aspectMap[$key] }}</span>
                                        <span class="font-semibold text-slate-800">{{ $value }} - {{ $labels[$value] ?? '' }}</span>
                                    </div>
                                @endif
                            @endforeach
                            @if(!empty($details['notes']))
                                <div class="mt-2 pt-2 border-t border-slate-50">
                                    <p class="text-xs text-slate-400">Catatan:</p>
                                    <p class="text-slate-700 italic">{{ $details['notes'] }}</p>
                                </div>
                            @endif
                        </div>
                    @endif
                </article>
                @endforeach
            @empty
                <div class="p-8">
                    <x-empty-state icon="chart-bar" title="Belum ada penilaian untuk kelas ini." message="Ubah filter kelas atau mata pelajaran untuk melihat data lain." />
                </div>
            @endforelse
        </div>

        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-6 py-4 font-bold uppercase text-xs tracking-wider">Penilaian</th>
                        <th class="px-6 py-4 font-bold uppercase text-xs tracking-wider">Siswa</th>
                        <th class="px-6 py-4 font-bold uppercase text-xs tracking-wider">Nilai</th>
                        <th class="px-6 py-4 text-right font-bold uppercase text-xs tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($scores as $score)
                        @php
                            $category = strtoupper(trim((string) ($score['Assessment_Category'] ?? '')));
                            $config = $assessmentConfigMap->get($category);
                            $categoryLabel = $config['Category_Name'] ?? ($category ?: 'Legacy Score');
                            $type = strtoupper(trim((string) ($config['Score_Type'] ?? $config['ScoreType'] ?? $config['Input_Type'] ?? '')));
                            $isNumeric = in_array($type, ['NUMERIC', 'NUMBER', 'SCORE', 'NUMERIC_0_100', '0-100'], true) || $category === 'UJIAN_BAB';
                            $details = json_decode((string) ($score['Evaluation_Details'] ?? ''), true);
                            $details = is_array($details) ? $details : [];
                            $scoreValue = $score['Score'] ?? $score['Score_Value'] ?? '-';
                            $notes = trim((string) ($details['notes'] ?? ''));
                            $aspects = $config && !empty($config['Aspects_JSON']) ? json_decode($config['Aspects_JSON'], true) : [];
                            $aspectMap = collect(is_array($aspects) ? $aspects : [])->pluck('label', 'id')->toArray();
                            $hasAspectDetails = false;
                            foreach ($details as $key => $value) {
                                if (in_array(strtolower((string) $key), ['category', 'notes', 'subject_id'], true)) {
                                    continue;
                                }
                                if (isset($aspectMap[$key])) {
                                    $hasAspectDetails = true;
                                    break;
                                }
                            }
                        @endphp
                        <tr class="hover:bg-slate-50 transition-colors" x-data="{ open: false }">
                            <td class="px-6 py-4 font-medium text-slate-600">
                                {{ $categoryLabel }}
                                <div class="text-xs text-slate-400 mt-1">{{ date('d M Y', strtotime($score['Created_At'] ?? now())) }}</div>
                            </td>
                            <td class="px-6 py-4 font-bold text-slate-900">{{ $score['Student_Name'] ?? 'Unknown Student' }}</td>
                            <td class="px-6 py-4">
                                @if($isNumeric)
                                    <span class="font-semibold text-slate-900 text-lg">{{ $scoreValue }}</span>
                                    @if($notes !== '')
                                        <div class="mt-2">
                                            <button @click="open = !open" class="text-xs font-bold text-blue-600 bg-blue-50 px-3 py-1.5 rounded-lg hover:bg-blue-100 transition-colors">
                                                <span x-show="!open">Catatan</span>
                                                <span x-show="open">Tutup Catatan</span>
                                            </button>
                                            <div x-show="open" class="mt-3 p-4 bg-white border border-slate-200 rounded-xl shadow-sm text-sm min-w-[250px] absolute z-10" @click.away="open = false">
                                                <div class="space-y-2">
                                                    <div class="flex justify-between gap-4">
                                                        <span class="text-slate-500">Nilai</span>
                                                        <span class="font-semibold text-slate-800">{{ $scoreValue }}</span>
                                                    </div>
                                                </div>
                                                <div class="mt-3 pt-3 border-t border-slate-100">
                                                    <p class="text-xs font-semibold text-slate-400 mb-1">Catatan:</p>
                                                    <p class="text-slate-700 italic whitespace-normal">{{ $notes }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @elseif($hasAspectDetails)
                                    <button @click="open = !open" class="text-xs font-bold text-blue-600 bg-blue-50 px-3 py-1.5 rounded-lg hover:bg-blue-100 transition-colors">
                                        <span x-show="!open">Lihat Detail</span>
                                        <span x-show="open">Tutup Detail</span>
                                    </button>

                                    <div x-show="open" class="mt-3 p-4 bg-white border border-slate-200 rounded-xl shadow-sm text-sm min-w-[250px] absolute z-10" @click.away="open = false">
                                        @php
                                            $labels = [1 => 'Sangat Kurang', 2 => 'Kurang', 3 => 'Cukup', 4 => 'Baik', 5 => 'Sangat Baik'];
                                        @endphp
                                        <div class="space-y-2">
                                        @foreach($details as $key => $value)
                                            @if(!in_array(strtolower((string) $key), ['category', 'notes'], true) && isset($aspectMap[$key]))
                                                <div class="flex justify-between gap-4">
                                                    <span class="text-slate-500">{{ $aspectMap[$key] }}</span>
                                                    <span class="font-semibold text-slate-800">{{ $value }} - {{ $labels[$value] ?? '' }}</span>
                                                </div>
                                            @endif
                                        @endforeach
                                        </div>
                                        @if(!empty($details['notes']))
                                            <div class="mt-3 pt-3 border-t border-slate-100">
                                                <p class="text-xs font-semibold text-slate-400 mb-1">Catatan:</p>
                                                <p class="text-slate-700 italic whitespace-normal">{{ $details['notes'] }}</p>
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <span class="font-semibold text-slate-900 text-lg">{{ $scoreValue }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('teacher.workspace.scores.edit', $score['Score_ID']) }}" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-200">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12">
                                <x-empty-state icon="chart-bar" title="Belum ada data penilaian." message="" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
