@extends('layouts.app')

@section('header', 'Dashboard Siswa')

@section('content')

@if(!empty($billingPopup['current']))
    @php
        $popup = $billingPopup['current'];
    @endphp
    <div x-data="{
            open: true,
            saving: false,
            async acknowledge() {
                if (this.saving) return;
                this.saving = true;
                try {
                    const response = await fetch('{{ route('notifications.markRead', $popup['notification']['Notification_ID']) }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    if (!response.ok) throw new Error('acknowledgement_failed');
                    this.open = false;
                } catch (error) {
                    this.$refs.ackError.classList.remove('hidden');
                } finally {
                    this.saving = false;
                }
            }
        }"
        x-show="open"
        x-cloak
        @keydown.escape.window="acknowledge()"
        class="fixed inset-0 z-[70] flex items-end justify-center bg-slate-950/55 p-3 pb-[calc(5.5rem+env(safe-area-inset-bottom))] backdrop-blur-sm sm:items-center sm:p-6"
        role="dialog"
        aria-modal="true"
        aria-labelledby="billing-popup-title">
        <section x-show="open" x-transition x-init="$nextTick(() => $refs.laterButton.focus())" class="max-h-[calc(100dvh-7rem)] w-full max-w-md overflow-y-auto rounded-3xl bg-white shadow-2xl ring-1 ring-slate-900/10 sm:max-h-[calc(100dvh-3rem)]">
            <div class="border-b border-slate-100 bg-gradient-to-br from-emerald-50 to-sky-50 p-5 sm:p-6">
                <div class="flex items-start gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-sm" aria-hidden="true">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14l2 2 4-4m5-3.5A11 11 0 0112 3a11 11 0 01-8 5.5V12c0 5 3.4 8 8 9 4.6-1 8-4 8-9V8.5z"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-emerald-700">Notifikasi Tagihan</p>
                        <h2 id="billing-popup-title" class="mt-1 break-words text-lg font-black leading-tight text-slate-900">{{ $popup['title'] }}</h2>
                    </div>
                    <button type="button" @click="acknowledge()" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-slate-500 hover:bg-white/70 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500" aria-label="Tutup notifikasi">&times;</button>
                </div>
            </div>

            <div class="space-y-5 p-5 sm:p-6">
                <p class="break-words text-sm text-slate-700">Halo, <strong>{{ $popup['student_name'] }}</strong></p>
                <dl class="grid grid-cols-2 gap-3">
                    <div class="col-span-2 min-w-0 rounded-2xl bg-amber-50 p-4">
                        <dt class="text-[11px] font-bold uppercase tracking-wide text-amber-700">Sisa Pembayaran</dt>
                        <dd class="mt-1 break-words text-2xl font-black text-amber-900">Rp{{ number_format($popup['remaining'], 0, ',', '.') }}</dd>
                    </div>
                    <div class="min-w-0 rounded-2xl bg-slate-50 p-3">
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Total Tagihan</dt>
                        <dd class="mt-1 break-words text-sm font-black text-slate-900">Rp{{ number_format($popup['total'], 0, ',', '.') }}</dd>
                    </div>
                    <div class="min-w-0 rounded-2xl bg-emerald-50 p-3">
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">Sudah Dibayar</dt>
                        <dd class="mt-1 break-words text-sm font-black text-emerald-900">Rp{{ number_format($popup['paid'], 0, ',', '.') }}</dd>
                    </div>
                </dl>

                <div class="rounded-2xl border {{ $popup['overdue'] ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-slate-200 bg-white text-slate-700' }} p-4 text-sm">
                    <p><span class="font-bold">Jatuh Tempo:</span> {{ $popup['due_date'] }}</p>
                    @if($popup['overdue'])
                        <p class="mt-1 font-bold">Tagihan telah melewati jatuh tempo.</p>
                    @endif
                </div>

                <p class="text-sm leading-6 text-slate-600">{{ $popup['instruction'] }}</p>
                <p class="wms-break-anywhere font-mono text-xs font-bold text-slate-500">{{ $popup['invoice_id'] }}</p>
                <p x-ref="ackError" class="hidden rounded-xl bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700">Belum dapat menyimpan pilihan. Silakan coba lagi.</p>

                @if(($billingPopup['other_count'] ?? 0) > 0)
                    <a href="{{ route('notifications.index') }}" class="block rounded-xl bg-sky-50 px-4 py-3 text-center text-xs font-bold text-sky-700 hover:bg-sky-100">
                        Anda memiliki {{ $billingPopup['other_count'] }} notifikasi tagihan lainnya — Lihat Notifikasi
                    </a>
                @endif

                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <button x-ref="laterButton" type="button" @click="acknowledge()" :disabled="saving" class="inline-flex min-h-12 w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-60 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2">Nanti</button>
                    <form action="{{ route('notifications.read', $popup['notification']['Notification_ID']) }}" method="POST">
                        @csrf
                        <button type="submit" class="inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-emerald-600 px-5 py-3 text-sm font-black text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">Bayar Sekarang</button>
                    </form>
                </div>
                <a href="{{ route('notifications.show', $popup['notification']['Notification_ID']) }}" class="block text-center text-xs font-bold text-slate-500 hover:text-slate-800 hover:underline">Lihat Detail</a>
            </div>
        </section>
    </div>
@endif

<!-- Greeting first on mobile; shared dashboard data remains unchanged. -->
<div class="mb-5 block md:hidden">
    <x-mobile-dashboard-hero user-role="STUDENT" :kpi-data="$kpi ?? []" />
</div>

@php
    $quizPayload = $quizDashboard ?? [];
    $dashboardQuizzes = collect($quizPayload['available'] ?? []);
    $upcomingQuizzes = collect($quizPayload['upcoming'] ?? []);
    $leaderboardTop = collect(data_get($quizPayload, 'leaderboard.top', []));
    $leaderboardCurrent = data_get($quizPayload, 'leaderboard.current');
    $dashboardStudentId = $quizPayload['student_id'] ?? null;
@endphp
<section aria-labelledby="student-quiz-dashboard-heading" class="mb-6 min-w-0 rounded-2xl border border-sky-200 bg-white p-4 shadow-sm sm:p-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><p class="text-xs font-black uppercase tracking-widest text-sky-700">Kuis Kelas</p><h2 id="student-quiz-dashboard-heading" class="mt-1 text-xl font-black text-slate-900">Kuis &amp; Leaderboard</h2></div>
        <a href="{{ route('student.quizzes.index') }}" class="inline-flex min-h-11 items-center text-sm font-black text-sky-700">Lihat Semua Kuis</a>
    </div>
    <div class="mt-4 grid min-w-0 gap-4 lg:grid-cols-2">
        <div class="order-2 min-w-0 space-y-4 lg:order-1">
            <div>
                <h3 class="text-sm font-black text-slate-900">Tersedia Sekarang</h3>
                <div class="mt-2 space-y-2">
                    @forelse($dashboardQuizzes as $quiz)
                        <article class="flex min-w-0 flex-col gap-3 rounded-xl bg-sky-50 p-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0"><h4 class="break-words font-black text-slate-900">{{ $quiz['Title'] }}</h4><p class="mt-1 break-words text-xs text-slate-600">Hingga {{ $quiz['End_At'] }} WIB · {{ $quiz['Duration_Minutes'] }} menit</p></div>
                            @if(!empty($quiz['Attempt_ID']))
                                <a href="{{ route('student.quizzes.play', $quiz['Attempt_ID']) }}" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl bg-sky-600 px-4 text-sm font-black text-white">Lanjutkan</a>
                            @else
                                <form method="POST" action="{{ route('student.quizzes.start', $quiz['Quiz_ID']) }}" class="shrink-0">@csrf<button class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-sky-600 px-4 text-sm font-black text-white">Kerjakan Sekarang</button></form>
                            @endif
                        </article>
                    @empty
                        <p class="rounded-xl bg-slate-50 p-3 text-sm text-slate-500">Belum ada kuis yang tersedia.</p>
                    @endforelse
                </div>
            </div>
            @if($upcomingQuizzes->isNotEmpty())
                <div><h3 class="text-sm font-black text-slate-900">Akan Datang</h3><ul class="mt-2 space-y-2">@foreach($upcomingQuizzes as $quiz)<li class="min-w-0 rounded-xl border border-slate-200 p-3"><p class="break-words font-bold text-slate-900">{{ $quiz['Title'] }}</p><p class="mt-1 break-words text-xs text-slate-500">Mulai {{ $quiz['Start_At'] }} WIB</p></li>@endforeach</ul></div>
            @endif
        </div>
        <div class="order-1 min-w-0 overflow-hidden rounded-2xl border border-amber-200 bg-amber-50 lg:order-2">
            <div class="flex min-w-0 items-start justify-between gap-3 bg-gradient-to-r from-amber-100 to-amber-50 p-4">
                <div class="flex min-w-0 items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-400 text-amber-950" aria-hidden="true"><x-sidebar.icon name="trophy" class="h-6 w-6" /></span>
                    <div class="min-w-0"><p class="text-[10px] font-black uppercase tracking-widest text-amber-700">Kelas Anda</p><h3 class="font-black text-amber-950">Peringkat Kuis</h3>@if(data_get($quizPayload, 'leaderboard.period.label'))<p class="mt-1 flex items-center gap-1 text-xs text-amber-800"><x-sidebar.icon name="calendar" class="h-3.5 w-3.5 shrink-0" /> Periode {{ data_get($quizPayload, 'leaderboard.period.label') }}</p>@endif</div>
                </div>
                <a href="{{ route('student.quizzes.leaderboard') }}" class="inline-flex min-h-11 shrink-0 items-center text-xs font-black text-amber-900">Lihat Semua</a>
            </div>
            <div class="space-y-2 py-3">
                @forelse($leaderboardTop as $entry)
                    @php
                        $dashboardRank = (int) $entry['Rank'];
                        $dashboardRankTone = match ($dashboardRank) { 1 => 'bg-amber-400 text-amber-950', 2 => 'bg-slate-300 text-slate-900', 3 => 'bg-orange-300 text-orange-950', default => 'bg-slate-100 text-slate-700' };
                        $dashboardIsCurrent = $entry['Student_ID'] === $dashboardStudentId;
                    @endphp
                    <div class="mx-3 flex min-w-0 items-center justify-between gap-3 rounded-xl bg-white p-3 {{ $dashboardIsCurrent ? 'ring-2 ring-sky-300' : '' }}">
                        <div class="flex min-w-0 items-center gap-2.5"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-xs font-black {{ $dashboardRankTone }}">#{{ $entry['Rank'] }}</span><div class="min-w-0"><div class="flex min-w-0 flex-wrap items-center gap-1.5"><span class="min-w-0 break-words text-sm font-black text-slate-900">{{ $entry['Student_Name'] }}</span>@if($dashboardIsCurrent)<span class="rounded-full bg-sky-600 px-1.5 py-0.5 text-[8px] font-black uppercase text-white">Anda</span>@endif</div><p class="text-[10px] text-slate-500">{{ $entry['Quiz_Count'] ?? 0 }} kuis</p></div></div>
                        <strong class="flex shrink-0 items-center gap-1 text-sm text-slate-900"><x-sidebar.icon name="star" class="h-4 w-4 text-amber-500" /> {{ (float) $entry['Points'] }} poin</strong>
                    </div>
                @empty
                    <p class="mx-3 rounded-xl bg-white p-3 text-sm text-slate-500">Leaderboard akan muncul setelah siswa menyelesaikan kuis.</p>
                @endforelse
                @if($leaderboardCurrent)
                    <div class="border-t border-amber-200 bg-white/70 p-3"><p class="mb-2 text-[10px] font-black uppercase tracking-widest text-sky-700">Posisi Anda</p><div class="flex min-w-0 items-center justify-between gap-3 rounded-xl bg-sky-50 p-3 ring-2 ring-sky-300"><span class="min-w-0 break-words text-sm font-black text-slate-900">#{{ $leaderboardCurrent['Rank'] }} {{ $leaderboardCurrent['Student_Name'] }} <span class="rounded-full bg-sky-600 px-1.5 py-0.5 text-[8px] uppercase text-white">Anda</span></span><strong class="flex shrink-0 items-center gap-1 text-sm text-slate-900"><x-sidebar.icon name="star" class="h-4 w-4 text-amber-500" /> {{ (float) $leaderboardCurrent['Points'] }} poin</strong></div></div>
                @endif
                <div class="px-3"><a href="{{ route('student.quizzes.leaderboard') }}" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-amber-300 bg-white text-sm font-black text-amber-900">Lihat Semua Peringkat</a></div>
            </div>
        </div>
    </div>
</section>

<!-- Pengumuman aktif: sumber data yang sama untuk desktop dan mobile -->
<section aria-labelledby="student-announcements-heading" class="mb-6 min-w-0">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-xs font-bold uppercase tracking-widest text-amber-700">Informasi terbaru</p>
            <h2 id="student-announcements-heading" class="mt-1 text-xl font-black text-slate-900">Pengumuman</h2>
        </div>
        @if(Route::has('student.portal.announcements'))
            <a href="{{ route('student.portal.announcements') }}" class="text-sm font-bold text-emerald-700 hover:underline">Lihat Semua Pengumuman</a>
        @endif
    </div>
    @if(($announcements ?? collect())->isEmpty())
        <p class="mt-4 rounded-2xl border border-slate-200 bg-white px-4 py-5 text-sm text-slate-500 shadow-sm">Belum ada pengumuman aktif untuk Anda.</p>
    @else
        <div class="mx-auto mt-4 max-w-3xl space-y-5">
            @foreach($announcements as $announcement)
                @php
                    $priority = $announcement['Priority_Label'] ?? 'Informasi';
                    $badge = $priority === 'Mendesak' ? 'bg-rose-600 text-white' : ($priority === 'Penting' ? 'bg-amber-500 text-white' : 'bg-slate-200 text-slate-700');
                    $creatorName = $announcement['Creator_Name'] ?? 'WMS';
                    $creatorInitials = collect(preg_split('/\s+/', trim($creatorName)))->filter()->take(2)->map(fn($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
                    $detailUrl = route('student.portal.announcements.show', $announcement['Announcement_ID']);
                @endphp
                <article class="min-w-0 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div class="p-4 sm:p-5">
                        <header class="flex min-w-0 items-start gap-3">
                            @if($announcement['Creator_Avatar_URL'] ?? null)
                                <img src="{{ $announcement['Creator_Avatar_URL'] }}" alt="Foto profil {{ $creatorName }}" class="h-11 w-11 shrink-0 rounded-full border-2 border-emerald-200 object-cover">
                            @else
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-slate-900 to-emerald-600 text-xs font-black text-white" aria-hidden="true">{{ $creatorInitials ?: 'WM' }}</div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <div class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1"><h3 class="break-words text-sm font-black text-slate-900">{{ $creatorName }}</h3><span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-extrabold {{ $badge }}">{{ $priority }}</span></div>
                                <p class="mt-0.5 break-words text-xs text-slate-500">{{ ucwords(strtolower($announcement['Creator_Role'] ?? 'WMS')) }} · {{ $announcement['Start_At_Label'] ?? '-' }}</p>
                            </div>
                        </header>
                        <h4 class="mt-4 break-words text-lg font-black leading-snug text-slate-900">{{ $announcement['Title'] ?? 'Pengumuman' }}</h4>
                        <p class="mt-2 whitespace-pre-line break-words text-sm leading-6 text-slate-700 [overflow-wrap:anywhere]">{{ $announcement['Message'] ?? $announcement['Content'] ?? '' }}</p>
                        <p class="mt-4 text-xs font-semibold text-slate-500">Berakhir: {{ $announcement['Expires_At_Label'] ?? '-' }}</p>
                    </div>
                    @include('announcements.comments.thread', [
                        'mode' => 'compact',
                        'announcement' => $announcement,
                        'comments' => $announcement['Latest_Comments'] ?? collect(),
                        'commentCount' => $announcement['Comment_Count'] ?? 0,
                        'commentStats' => ['total' => $announcement['Comment_Count'] ?? 0, 'roots' => $announcement['Comment_Root_Count'] ?? 0, 'replies' => $announcement['Comment_Reply_Count'] ?? 0],
                        'detailUrl' => $detailUrl,
                        'currentUserAvatar' => $announcementCommenterAvatar ?? null,
                    ])
                </article>
            @endforeach
        </div>
        @include('announcements.comments.sticker-picker', ['library' => $announcementStickerLibrary])
    @endif
</section>

<section class="mb-6 rounded-2xl border border-blue-100 bg-blue-50 p-4 shadow-sm sm:p-5" aria-labelledby="education-payment-summary-heading">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 id="education-payment-summary-heading" class="text-xs font-bold uppercase tracking-widest text-blue-800">Ringkasan Biaya Pendidikan</h2>
            <p class="mt-1 text-sm text-slate-600">Berdasarkan pembayaran Biaya Pendidikan yang sudah diverifikasi.</p>
        </div>
        <span class="inline-flex rounded-full px-3 py-1 text-xs font-black {{ ($kpi['status_biaya_pendidikan'] ?? '') === 'LUNAS' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
            {{ $kpi['status_biaya_pendidikan'] ?? 'BELUM BAYAR' }}
        </span>
    </div>
    <dl class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="min-w-0 rounded-xl bg-white p-3 shadow-sm">
            <dt class="text-[11px] font-bold text-slate-500">Biaya Pendidikan</dt>
            <dd class="mt-1 break-words text-base font-black text-slate-900 sm:text-lg">Rp {{ number_format($kpi['biaya_pendidikan'] ?? 0, 0, ',', '.') }}</dd>
        </div>
        <div class="min-w-0 rounded-xl bg-emerald-50 p-3">
            <dt class="text-[11px] font-bold text-emerald-700">Sudah Dibayar</dt>
            <dd class="mt-1 break-words text-base font-black text-emerald-900 sm:text-lg">Rp {{ number_format($kpi['sudah_dibayar'] ?? 0, 0, ',', '.') }}</dd>
        </div>
        <div class="min-w-0 rounded-xl bg-amber-50 p-3">
            <dt class="text-[11px] font-bold text-amber-700">Sisa Biaya Pendidikan</dt>
            <dd class="mt-1 break-words text-base font-black text-amber-900 sm:text-lg">Rp {{ number_format($kpi['sisa_biaya_pendidikan'] ?? 0, 0, ',', '.') }}</dd>
        </div>
    </dl>
</section>

@php
    $formattedKpi = [
        ['title' => "Kelas Hari Ini", 'value' => $kpi['today_class'] ?? 0, 'icon' => 'calendar', 'color' => 'indigo', 'link' => route('student.schedule')],
        ['title' => 'Tagihan dari LPK', 'value' => 'Rp '.number_format($kpi['tagihan_master'] ?? 0, 0, ',', '.'), 'icon' => 'document-text', 'color' => 'blue', 'link' => route('student.billing.index')],
        ['title' => 'Pengajuan Presensi', 'value' => ($kpi['request_pending'] ?? 0) . ' Pending / ' . ($kpi['request_approved'] ?? 0) . ' Setuju', 'icon' => 'document-text', 'color' => ($kpi['request_pending'] ?? 0) > 0 ? 'amber' : 'emerald', 'link' => route('student.attendance.requests.index')],
    ];

    $quickActions = [
        ['title' => 'Lihat Jadwal', 'url' => route('student.schedule'), 'icon' => 'calendar', 'color' => 'indigo'],
        ['title' => 'Unggah Pembayaran', 'url' => route('student.billing.index'), 'icon' => 'cash', 'color' => 'blue'],
        ['title' => 'Pengajuan Sakit/Izin', 'url' => route('student.attendance.requests.index'), 'icon' => 'document-text', 'color' => 'amber'],
        ['title' => 'Tugas Saya', 'url' => route('student.portal.assignments'), 'icon' => 'clipboard-list', 'color' => 'indigo'],
    ];
@endphp

<!-- MAIN UNIFIED DASHBOARD VIEW (HIDDEN ON MOBILE, VISIBLE ON DESKTOP) -->
<div class="hidden md:block w-full">
    <x-dashboard-header />
    <x-dashboard.action-center 
        title="Dashboard Siswa" 
        description="Pusat informasi akademik dan administrasi siswa LPK."
        :kpi="$formattedKpi"
        :quick-actions="$quickActions"
        :reminders="array_slice($reminders ?? [], 0, 5)"
        :recent-activities="$recentActivities ?? []"
    >
        <!-- Language Progress -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200 mt-6">
            <h4 class="text-lg font-extrabold text-slate-800 mb-5">Kemajuan Bahasa</h4>
            <div class="w-full bg-slate-100 rounded-full h-2.5 mb-2">
                <div class="bg-blue-500 h-2.5 rounded-full transition-all duration-500" style="width: {{ $langProgress ?? 0 }}%"></div>
            </div>
            <p class="text-xs text-slate-500 text-right">{{ $langProgress ?? 0 }}%</p>
        </div>
    </x-dashboard.action-center>
</div>

@endsection
