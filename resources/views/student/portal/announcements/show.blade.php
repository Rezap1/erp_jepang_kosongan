@extends('layouts.app')

@section('header', 'Detail Pengumuman')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <x-page-header title="Detail Pengumuman" description="Informasi resmi dari sekolah." :breadcrumbs="['Dashboard' => route('dashboard.student'), 'Pengumuman' => route('student.portal.announcements'), 'Detail' => '#']" />
    @php $priority = $announcement['Priority_Label'] ?? 'Informasi'; @endphp
    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">{{ $priority }}</span>
        <h1 class="mt-4 break-words text-2xl font-black leading-tight text-slate-900">{{ $announcement['Title'] ?? 'Pengumuman' }}</h1>
        <div class="mt-5 whitespace-pre-line break-words text-base leading-7 text-slate-700">{{ $announcement['Message'] ?? $announcement['Content'] ?? '' }}</div>
        <p class="mt-8 border-t border-slate-100 pt-4 text-sm font-semibold text-slate-600">Berakhir: {{ $announcement['Expires_At_Label'] ?? '-' }}</p>
    </article>
    @include('announcements.comments.thread', [
        'mode' => 'full',
        'announcement' => $announcement,
        'comments' => $comments,
        'currentUserAvatar' => $currentUserAvatar,
    ])
</div>
@include('announcements.comments.sticker-picker', ['library' => $library])
@endsection
