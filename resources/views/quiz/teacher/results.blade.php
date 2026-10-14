@extends('layouts.app')
@section('header', 'Hasil Kuis')
@section('content')
@php
    $className = $selectedClassRow['Class_Name'] ?? $selectedClass;
@endphp
<div class="mx-auto min-w-0 max-w-6xl space-y-5 pb-28 md:pb-10">
    <header class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-widest text-sky-700">Hasil Kuis per Kelas</p>
                <h2 class="mt-1 text-2xl font-black text-slate-900">{{ $className ?: 'Belum Ada Kelas' }}</h2>
                <p class="mt-1 text-sm text-slate-600">Setiap kuis dan hasil siswa ditampilkan hanya untuk kelas yang dipilih.</p>
            </div>
            @if($selectedClass)
                <a href="{{ route('teacher.quizzes.leaderboard', $selectedClass) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-900 px-4 text-sm font-black text-white">Lihat Leaderboard 14 Hari</a>
            @endif
        </div>

        @if($classes->isNotEmpty())
            <form method="GET" action="{{ route('teacher.quizzes.results') }}" class="mt-5">
                <label for="quiz-result-class" class="text-sm font-black text-slate-800">Kelas</label>
                <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                    <select id="quiz-result-class" name="class" class="min-h-12 min-w-0 flex-1 rounded-xl border-slate-300" aria-label="Pilih kelas hasil kuis">
                        <option value="">-- Pilih Kelas --</option>
                        @foreach($classes as $class)
                            <option value="{{ $class['Class_ID'] }}" @selected($selectedClass === $class['Class_ID'])>{{ $class['Class_Name'] ?? $class['Class_ID'] }}</option>
                        @endforeach
                    </select>
                    <button class="min-h-12 rounded-xl bg-sky-600 px-5 font-black text-white">Tampilkan</button>
                </div>
            </form>
            @if($classes->count() > 1)
                <nav class="mt-3 flex gap-2 overflow-x-auto pb-1" aria-label="Navigasi kelas">
                    @foreach($classes as $class)
                        <a href="{{ route('teacher.quizzes.results', ['class' => $class['Class_ID']]) }}" class="shrink-0 rounded-full px-4 py-2 text-sm font-bold {{ $selectedClass === $class['Class_ID'] ? 'bg-sky-100 text-sky-800 ring-1 ring-sky-300' : 'bg-slate-100 text-slate-700' }}">{{ $class['Class_Name'] ?? $class['Class_ID'] }}</a>
                    @endforeach
                </nav>
            @endif
        @endif
    </header>

    @forelse($overview['groups'] as $group)
        <section class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="quiz-{{ $group['quiz_id'] }}">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-black uppercase tracking-wide text-sky-700">Kuis</p>
                    <h3 id="quiz-{{ $group['quiz_id'] }}" class="break-words text-xl font-black text-slate-900">{{ $group['title'] }}</h3>
                </div>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <div class="rounded-xl bg-slate-50 px-3 py-2"><p class="text-[11px] font-bold text-slate-500">Peserta</p><strong>{{ $group['participant_count'] }}</strong></div>
                    <div class="rounded-xl bg-emerald-50 px-3 py-2"><p class="text-[11px] font-bold text-emerald-700">Selesai</p><strong>{{ $group['completed_count'] }}</strong></div>
                    <div class="rounded-xl bg-amber-50 px-3 py-2"><p class="text-[11px] font-bold text-amber-700">Belum</p><strong>{{ $group['not_completed_count'] }}</strong></div>
                    <div class="rounded-xl bg-sky-50 px-3 py-2"><p class="text-[11px] font-bold text-sky-700">Rata-rata Poin</p><strong>{{ $group['average'] === null ? '—' : (float) $group['average'].' poin' }}</strong></div>
                </div>
            </div>

            <div class="mt-5 grid min-w-0 gap-3 md:grid-cols-2 xl:grid-cols-3">
                @forelse($group['results'] as $result)
                    <article class="min-w-0 rounded-2xl border border-slate-200 p-4">
                        <div class="flex min-w-0 items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h4 class="break-words font-black text-slate-900">{{ $result['Student_Name'] ?? 'Siswa' }}</h4>
                                @if(!empty($result['Student_Number']))<p class="text-xs text-slate-500">{{ $result['Student_Number'] }}</p>@endif
                            </div>
                            <span class="shrink-0 rounded-xl bg-emerald-50 px-3 py-2 text-xl font-black text-emerald-800">{{ (float) $result['Raw_Score'] }} / {{ (float) $result['Maximum_Score'] }} poin</span>
                        </div>
                        <p class="mt-3 text-xs text-slate-500">Selesai {{ $result['Completed_At'] ?? '-' }} WIB</p>
                        <a href="{{ route('teacher.quizzes.result', $result['Result_ID']) }}" class="mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-slate-300 text-sm font-bold text-slate-800">Lihat Detail</a>
                    </article>
                @empty
                    <p class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 md:col-span-2 xl:col-span-3">Belum ada siswa yang menyelesaikan kuis ini.</p>
                @endforelse
            </div>

            @if($group['not_completed']->isNotEmpty())
                <details class="mt-4 rounded-xl bg-amber-50 p-4">
                    <summary class="cursor-pointer text-sm font-black text-amber-900">Belum Mengerjakan ({{ $group['not_completed_count'] }})</summary>
                    <ul class="mt-3 grid gap-2 text-sm text-amber-900 sm:grid-cols-2">
                        @foreach($group['not_completed'] as $student)
                            <li class="rounded-lg bg-white/70 px-3 py-2 font-semibold">{{ $student['Full_Name'] ?? $student['Student_Name'] ?? $student['Student_ID'] }}</li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </section>
    @empty
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">
            {{ $classes->isEmpty() ? 'Belum ada kelas aktif dalam jadwal mengajar Anda.' : 'Belum ada hasil kuis untuk kelas ini.' }}
        </div>
    @endforelse
</div>
@endsection
