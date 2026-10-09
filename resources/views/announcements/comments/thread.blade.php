@php
    $threadMode = $mode ?? 'full';
    $isCompact = $threadMode === 'compact';
    $threadComments = $comments instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator
        ? $comments->getCollection()
        : collect($comments ?? []);
    $threadCount = (int) ($commentCount ?? ($comments instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $comments->total() : $threadComments->count()));
    $threadId = 'announcement-comments-'.md5((string) $announcement['Announcement_ID']);
    $otherCount = max(0, $threadCount - $threadComments->count());
    $detailUrl = $detailUrl ?? null;
    $showComposer = $showComposer ?? true;
@endphp

<section id="{{ $threadId }}" class="{{ $isCompact ? 'border-t border-slate-100' : 'rounded-3xl border border-slate-200 bg-white shadow-sm' }}" aria-labelledby="{{ $threadId }}-heading">
    <header class="flex flex-wrap items-center justify-between gap-2 {{ $isCompact ? 'px-4 py-3 sm:px-5' : 'border-b border-slate-100 px-5 py-5 sm:px-7' }}">
        <div>
            @unless($isCompact)<p class="text-xs font-bold uppercase tracking-[.18em] text-emerald-600">Percakapan</p>@endunless
            <h2 id="{{ $threadId }}-heading" class="{{ $isCompact ? 'text-sm' : 'mt-1 text-lg' }} font-black text-slate-900">
                <span aria-hidden="true">💬</span> {{ $threadCount }} Komentar
            </h2>
        </div>
        @if($isCompact && $detailUrl)
            <a href="{{ $detailUrl }}#{{ $threadId }}" class="inline-flex min-h-10 items-center text-xs font-black text-emerald-700 hover:underline">Lihat Detail</a>
        @endif
    </header>

    <div class="{{ $isCompact ? 'px-4 sm:px-5' : 'divide-y divide-slate-100 px-5 sm:px-7' }}">
        @forelse($threadComments as $comment)
            @php $displayName = $comment->User_Name ?: $comment->Username ?: 'Pengguna'; @endphp
            <article class="flex min-w-0 gap-3 {{ $isCompact ? 'border-t border-slate-100 py-3 first:border-t-0' : 'py-5' }}">
                <x-resolved-avatar :name="$displayName" :url="$comment->Avatar_URL ?? null" class="h-9 w-9 sm:h-10 sm:w-10" text-size="text-[10px] sm:text-xs" />
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h3 class="break-words text-sm font-black text-slate-900">{{ $displayName }}</h3>
                            <p class="mt-0.5 break-words text-[11px] text-slate-500">{{ ucwords(strtolower($comment->Role_Name ?: 'Pengguna')) }} · {{ \Carbon\Carbon::parse($comment->Created_At)->locale('id')->diffForHumans() }}</p>
                        </div>
                        @if($comment->Can_Delete ?? false)
                            <form action="{{ route('announcement-comments.destroy', [$announcement['Announcement_ID'], $comment->Comment_ID]) }}" method="POST" onsubmit="return confirm('Hapus komentar ini?')">
                                @csrf @method('DELETE')
                                <button class="min-h-10 rounded-lg px-2 text-xs font-bold text-slate-400 hover:bg-rose-50 hover:text-rose-600" aria-label="Hapus komentar {{ $displayName }}">Hapus</button>
                            </form>
                        @endif
                    </div>
                    @if($comment->Comment_Type === 'STICKER')
                        @if($comment->Sticker_URL ?? null)
                            <img src="{{ $comment->Sticker_URL }}" alt="Stiker {{ $comment->Sticker_Name ?: 'komentar' }}" loading="lazy" class="mt-2 h-24 w-24 object-contain sm:h-32 sm:w-32">
                        @else
                            <p class="mt-2 text-xs italic text-slate-500">Stiker ini tidak lagi tersedia.</p>
                        @endif
                    @else
                        <p class="mt-2 whitespace-pre-line break-words text-sm leading-6 text-slate-700 [overflow-wrap:anywhere]">{{ $comment->Comment_Text }}</p>
                    @endif
                </div>
            </article>
        @empty
            <div class="{{ $isCompact ? 'border-t border-slate-100 py-5' : 'py-12' }} text-center">
                <p class="font-bold text-slate-700">Belum ada komentar.</p>
                <p class="mt-1 text-sm text-slate-500">{{ $showComposer ? 'Jadilah yang pertama berkomentar.' : 'Komentar penerima akan muncul di sini.' }}</p>
            </div>
        @endforelse
    </div>

    @if($isCompact && $otherCount > 0 && $detailUrl)
        <div class="px-4 pb-3 sm:px-5">
            <a href="{{ $detailUrl }}#{{ $threadId }}" class="inline-flex min-h-10 items-center text-xs font-black text-slate-600 hover:text-emerald-700 hover:underline">Lihat {{ $otherCount }} komentar lainnya</a>
        </div>
    @elseif(!$isCompact && $comments->hasPages())
        <div class="border-t border-slate-100 px-5 py-4 sm:px-7">{{ $comments->onEachSide(1)->links() }}</div>
    @endif

    @if($showComposer)
        @include('announcements.comments.composer', [
            'announcement' => $announcement,
            'compact' => $isCompact,
            'currentUserAvatar' => $currentUserAvatar ?? null,
        ])
    @endif
</section>
