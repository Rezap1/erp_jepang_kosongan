@extends('layouts.app')

@section('header', 'Detail Pengumuman')

@section('content')
<div class="mx-auto max-w-5xl space-y-6 pb-24 sm:pb-8">
    <x-page-header
        title="Detail Pengumuman"
        description="Informasi lengkap dan percakapan penerima pengumuman."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Pengumuman' => route('announcements.index'), 'Detail' => '#']"
    />

    @php
        $priority = $announcement['Priority_Label'] ?? 'Informasi';
        $priorityClass = $priority === 'Mendesak' ? 'bg-rose-100 text-rose-700' : ($priority === 'Penting' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-700');
    @endphp

    <article class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="p-5 sm:p-8">
            <div class="flex flex-wrap items-center gap-2 pr-1">
                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $priorityClass }}">{{ $priority }}</span>
                <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">{{ $announcement['Audience_Label'] ?? 'Semua Siswa' }}</span>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ $announcement['Status_Label'] ?? 'Aktif' }}</span>
            </div>

            <h1 class="mt-5 break-words text-2xl font-black leading-tight text-slate-900 sm:text-3xl">{{ $announcement['Title'] ?? 'Pengumuman' }}</h1>

            <div class="mt-5 flex min-w-0 items-center gap-3">
                <x-resolved-avatar :name="$announcement['Creator_Name']" :url="$announcement['Creator_Avatar_URL']" class="h-11 w-11" />
                <div class="min-w-0">
                    <p class="break-words text-sm font-black text-slate-900">{{ $announcement['Creator_Name'] }}</p>
                    <p class="text-xs text-slate-500">{{ ucwords(strtolower($announcement['Creator_Role'])) }} · {{ $announcement['Created_Label'] }}</p>
                </div>
            </div>

            <div class="mt-6 whitespace-pre-line break-words text-base leading-7 text-slate-700 [overflow-wrap:anywhere]">{{ $announcement['Message'] ?? $announcement['Content'] ?? '' }}</div>

            <dl class="mt-8 grid gap-4 border-t border-slate-100 pt-6 text-sm sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-2xl bg-slate-50 p-4"><dt class="font-semibold text-slate-500">Kategori</dt><dd class="mt-1 break-words font-bold text-slate-800">{{ $announcement['Category_Label'] }}</dd></div>
                <div class="rounded-2xl bg-slate-50 p-4"><dt class="font-semibold text-slate-500">Target audiens</dt><dd class="mt-1 break-words font-bold text-slate-800">{{ $announcement['Audience_Label'] ?? '-' }}</dd></div>
                <div class="rounded-2xl bg-slate-50 p-4"><dt class="font-semibold text-slate-500">Jumlah komentar</dt><dd class="mt-1 font-bold text-slate-800">{{ $announcement['Comment_Count'] }} komentar aktif</dd></div>
                <div class="rounded-2xl bg-slate-50 p-4"><dt class="font-semibold text-slate-500">Waktu publikasi</dt><dd class="mt-1 font-bold text-slate-800">{{ $announcement['Created_Label'] }}</dd></div>
                <div class="rounded-2xl bg-slate-50 p-4"><dt class="font-semibold text-slate-500">Mulai tayang</dt><dd class="mt-1 font-bold text-slate-800">{{ $announcement['Start_Label'] ?? '-' }}</dd></div>
                <div class="rounded-2xl bg-slate-50 p-4"><dt class="font-semibold text-slate-500">Berakhir</dt><dd class="mt-1 font-bold text-slate-800">{{ $announcement['Expiry_Label'] ?? '-' }}</dd></div>
            </dl>

            <div class="mt-6 flex flex-wrap gap-3">
                @if($announcement['Can_Manage'] ?? false)
                    <x-button as="a" href="{{ route('announcements.edit', $announcement['Announcement_ID']) }}" variant="primary">Edit Pengumuman</x-button>
                @endif
                <x-button as="a" href="{{ route('announcements.index') }}" variant="secondary">Kembali ke Daftar</x-button>
            </div>
        </div>
    </article>

    @include('announcements.comments.thread', [
        'mode' => 'full',
        'announcement' => $announcement,
        'comments' => $comments,
        'commentCount' => $announcement['Comment_Count'],
        'showComposer' => false,
    ])
</div>
@endsection
