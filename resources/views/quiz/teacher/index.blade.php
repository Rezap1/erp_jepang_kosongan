@extends('layouts.app')
@section('header', 'Kuis Kelas')
@section('content')
<div class="mx-auto max-w-6xl space-y-6 pb-28 md:pb-10">
    <header class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <p class="text-xs font-black uppercase tracking-widest text-sky-700">Ruang Kuis Pengajar</p>
            <h2 class="mt-1 break-words text-2xl font-black text-slate-900">Pilih Kelas</h2>
            <p class="mt-1 text-sm text-slate-600">Buka satu kelas untuk mengelola kuis dan melihat peringkat tanpa mencampur kelas lain.</p>
        </div>
        <a href="{{ route('teacher.quizzes.create') }}" class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-black text-slate-800 shadow-sm">Buat Kuis Umum</a>
    </header>

    <div class="grid min-w-0 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($classes as $class)
            <article class="flex min-w-0 flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex min-w-0 items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-700" aria-hidden="true"><x-sidebar.icon name="academic-cap" class="h-6 w-6" /></span>
                    <div class="min-w-0"><p class="text-[11px] font-black uppercase tracking-widest text-sky-700">Kelas</p><h3 class="break-words text-xl font-black text-slate-900">{{ $class['Class_Name'] ?? 'Kelas Anda' }}</h3></div>
                </div>
                <dl class="mt-5 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-xl bg-emerald-50 p-3"><dt class="text-[10px] font-bold uppercase text-emerald-700">Aktif</dt><dd class="mt-1 text-xl font-black text-emerald-900">{{ $class['Active_Quiz_Count'] }}</dd></div>
                    <div class="rounded-xl bg-amber-50 p-3"><dt class="text-[10px] font-bold uppercase text-amber-700">Akan Datang</dt><dd class="mt-1 text-xl font-black text-amber-900">{{ $class['Upcoming_Quiz_Count'] }}</dd></div>
                    <div class="rounded-xl bg-slate-100 p-3"><dt class="text-[10px] font-bold uppercase text-slate-600">Selesai</dt><dd class="mt-1 text-xl font-black text-slate-900">{{ $class['Completed_Quiz_Count'] }}</dd></div>
                </dl>
                <div class="mt-4 flex items-center gap-2 text-sm font-bold text-slate-600"><x-sidebar.icon name="user-group" class="h-5 w-5 text-slate-400" /><span>{{ $class['Student_Count'] }} siswa</span><span aria-hidden="true">·</span><span>{{ $class['Quiz_Count'] }} kuis</span></div>
                <a href="{{ route('teacher.quizzes.class', $class['Class_ID']) }}" class="mt-5 inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-slate-900 px-4 text-sm font-black text-white">Buka Kelas</a>
            </article>
        @empty
            <section class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center md:col-span-2 xl:col-span-3">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-500"><x-sidebar.icon name="academic-cap" class="h-7 w-7" /></span>
                <h3 class="mt-4 font-black text-slate-900">Belum ada kelas aktif</h3>
                <p class="mt-1 text-sm text-slate-500">Kelas akan tampil setelah penugasan kelas aktif tersimpan.</p>
            </section>
        @endforelse
    </div>
</div>
@endsection
