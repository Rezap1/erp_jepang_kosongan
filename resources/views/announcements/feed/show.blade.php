@extends('layouts.app')
@section('header', 'Detail Pengumuman')
@section('content')
<div class="mx-auto max-w-4xl space-y-5">
    <a href="{{ route('announcement-feed.index') }}" class="inline-flex min-h-11 items-center text-sm font-bold text-slate-600 hover:text-emerald-700">← Kembali ke pengumuman</a>
    <article class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950 px-5 py-8 text-white sm:px-9 sm:py-10">
            <p class="text-xs font-extrabold uppercase tracking-[.22em] text-emerald-300">Pengumuman</p>
            <h1 class="mt-3 break-words text-2xl font-black leading-tight sm:text-3xl">{{ $announcement['Title'] }}</h1>
            <div class="mt-5 flex flex-wrap items-center gap-x-2 gap-y-2 text-xs font-semibold text-slate-300"><span>{{ $announcement['Creator_Name'] }}</span><span aria-hidden="true">•</span><time>{{ $announcement['Start_Label'] }}</time></div>
            <span class="mt-4 inline-flex max-w-full rounded-full bg-white/10 px-3 py-1.5 text-xs font-bold text-emerald-100 ring-1 ring-white/15">{{ $announcement['Audience_Label'] }}</span>
        </div>
        <div class="px-5 py-7 sm:px-9 sm:py-9"><div class="whitespace-pre-line break-words text-[15px] leading-7 text-slate-700 sm:text-base">{{ $announcement['Message'] ?? $announcement['Content'] }}</div><p class="mt-8 border-t border-slate-100 pt-5 text-xs font-semibold text-slate-500">Tayang hingga {{ $announcement['Expiry_Label'] }}</p></div>
    </article>

    @include('announcements.comments.thread', [
        'mode' => 'full',
        'announcement' => $announcement,
        'comments' => $comments,
        'commentStats' => $commentStats ?? null,
        'currentUserAvatar' => $currentUserAvatar ?? null,
    ])
</div>
@include('announcements.comments.sticker-picker', ['library' => $library])
@endsection
