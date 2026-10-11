@extends('layouts.app')
@section('header', 'Pengumuman')
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <x-page-header title="Pengumuman" description="Informasi terbaru yang ditujukan untuk Anda." :breadcrumbs="['Dashboard' => route('dashboard'), 'Pengumuman' => '#']" />
    @if($announcements->isEmpty())
        <section class="rounded-3xl border border-slate-200 bg-white px-6 py-16 text-center shadow-sm"><span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-2xl" aria-hidden="true">📣</span><h2 class="mt-4 font-black text-slate-900">Belum ada pengumuman</h2><p class="mt-1 text-sm text-slate-500">Informasi baru untuk Anda akan muncul di sini.</p></section>
    @else
        <div class="grid gap-4 md:grid-cols-2">
            @foreach($announcements as $announcement)
                @php $priority = $announcement['Priority_Label']; $accent = $priority === 'Mendesak' ? 'bg-rose-500' : ($priority === 'Penting' ? 'bg-amber-400' : 'bg-emerald-500'); @endphp
                <article class="group relative min-w-0 overflow-hidden rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md sm:p-6">
                    <span class="absolute inset-y-0 left-0 w-1.5 {{ $accent }}"></span>
                    <div class="flex flex-wrap items-center gap-2"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-extrabold text-slate-700">{{ $priority }}</span><span class="max-w-full truncate rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700">{{ $announcement['Audience_Label'] }}</span></div>
                    <h2 class="mt-4 break-words text-lg font-black leading-snug text-slate-900">{{ $announcement['Title'] }}</h2>
                    <p class="mt-2 line-clamp-3 whitespace-pre-line break-words text-sm leading-6 text-slate-600">{{ $announcement['Message'] ?? $announcement['Content'] }}</p>
                    <div class="mt-5 flex items-center justify-between gap-3 border-t border-slate-100 pt-4 text-xs font-semibold text-slate-500"><span>{{ $announcement['Start_Label'] }}</span><span aria-label="{{ $announcement['Comment_Count'] }} komentar">{{ $announcement['Comment_Count'] }} komentar</span></div>
                    <p class="mt-1 text-right text-[11px] text-slate-400">{{ $announcement['Comment_Root_Count'] }} utama · {{ $announcement['Comment_Reply_Count'] }} balasan</p>
                    <a href="{{ route('announcement-feed.show', $announcement['Announcement_ID']) }}" class="mt-4 inline-flex min-h-11 items-center font-bold text-emerald-700 hover:text-emerald-800">Baca pengumuman <span class="ml-1" aria-hidden="true">→</span></a>
                </article>
            @endforeach
        </div>
    @endif
</div>
@endsection
