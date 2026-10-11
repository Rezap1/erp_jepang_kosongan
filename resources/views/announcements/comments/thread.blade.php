@php
    $threadMode = $mode ?? 'full';
    $isCompact = $threadMode === 'compact';
    $threadComments = $comments instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator
        ? $comments->getCollection()
        : collect($comments ?? []);
    $stats = $commentStats ?? [
        'total' => (int) ($commentCount ?? ($comments instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $comments->total() : $threadComments->count())),
        'roots' => (int) ($announcement['Comment_Root_Count'] ?? $commentCount ?? $threadComments->count()),
        'replies' => (int) ($announcement['Comment_Reply_Count'] ?? 0),
    ];
    $threadCount = (int) ($stats['total'] ?? 0);
    $rootCount = (int) ($stats['roots'] ?? $threadCount);
    $replyCount = (int) ($stats['replies'] ?? 0);
    $threadId = 'announcement-comments-'.md5((string) $announcement['Announcement_ID']);
    $composerId = 'announcement-comment-composer-'.md5((string) $announcement['Announcement_ID']);
    $otherCount = max(0, $threadCount - $threadComments->count());
    $detailUrl = $detailUrl ?? null;
    $showComposer = $showComposer ?? true;
@endphp

<section id="{{ $threadId }}" class="{{ $isCompact ? 'border-t border-slate-100' : 'rounded-3xl border border-slate-200 bg-white shadow-sm' }}" aria-labelledby="{{ $threadId }}-heading">
    <header class="flex flex-wrap items-center justify-between gap-2 {{ $isCompact ? 'px-4 py-3 sm:px-5' : 'border-b border-slate-100 px-5 py-5 sm:px-7' }}">
        <div>
            @unless($isCompact)<p class="text-xs font-bold uppercase tracking-[.18em] text-emerald-600">Percakapan</p>@endunless
            <h2 id="{{ $threadId }}-heading" class="{{ $isCompact ? 'text-sm' : 'mt-1 text-lg' }} font-black text-slate-900">{{ $threadCount }} Komentar</h2>
            <p class="mt-0.5 text-[11px] font-semibold text-slate-500">{{ $rootCount }} komentar utama · {{ $replyCount }} balasan</p>
        </div>
        @if($isCompact && $detailUrl)
            <a href="{{ $detailUrl }}#{{ $threadId }}" class="inline-flex min-h-10 items-center text-xs font-black text-emerald-700 hover:underline">Lihat Detail</a>
        @endif
    </header>

    <div class="{{ $isCompact ? 'px-4 sm:px-5' : 'divide-y divide-slate-100 px-5 sm:px-7' }}">
        @forelse($threadComments as $comment)
            @php
                $displayName = $comment->User_Name ?: $comment->Username ?: 'Pengguna';
                $isReply = !empty($comment->Parent_Comment_ID);
                $parentName = $comment->Parent_User_Name ?? $comment->Parent_Username ?? 'komentar';
                $externalUrl = trim((string) ($comment->External_Media_URL ?? ''));
                $externalUrlIsSafe = preg_match('#^https://[^/@:]+(?::443)?(?:/|$)#i', $externalUrl) === 1;
            @endphp
            <article id="comment-{{ $comment->Comment_ID }}" class="flex min-w-0 gap-3 scroll-mt-24 {{ $isCompact ? 'border-t border-slate-100 py-3 first:border-t-0' : 'py-5' }} {{ $isReply ? 'ml-5 border-l-2 border-emerald-100 pl-3 sm:ml-10 sm:pl-5' : '' }}">
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

                    @if($isReply)
                        <p class="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">
                            Membalas <strong class="text-slate-700">{{ $parentName }}</strong>
                            @if(!($comment->Parent_Deleted_At ?? null) && ($comment->Parent_Comment_Text ?? null))
                                · “{{ \Illuminate\Support\Str::limit($comment->Parent_Comment_Text, 80) }}”
                            @else
                                · komentar asal tidak tersedia
                            @endif
                        </p>
                    @endif

                    @if($comment->Comment_Type === 'STICKER')
                        @if($comment->Sticker_URL ?? null)
                            <div class="relative mt-2 inline-block">
                                <img src="{{ $comment->Sticker_URL }}" alt="{{ $comment->Sticker_Mime_Type === 'image/gif' ? 'GIF' : 'Stiker' }} {{ $comment->Sticker_Name ?: 'komentar' }}" loading="lazy" class="h-24 w-24 object-contain sm:h-32 sm:w-32">
                                @if($comment->Sticker_Mime_Type === 'image/gif')<span class="absolute bottom-0 right-0 rounded bg-violet-600 px-1.5 py-0.5 text-[9px] font-black text-white">GIF</span>@endif
                            </div>
                        @else
                            <p class="mt-2 text-xs italic text-slate-500">Media ini tidak lagi tersedia.</p>
                        @endif
                    @elseif($comment->Comment_Type === 'GIF')
                        @if($externalUrlIsSafe)
                            <figure class="mt-2 inline-block max-w-full">
                                <img src="{{ $externalUrl }}" alt="{{ $comment->External_Media_Alt ?: 'GIF komentar' }}" loading="lazy" class="max-h-72 max-w-full rounded-xl object-contain">
                                <figcaption class="mt-1 text-[10px] font-semibold uppercase tracking-wide text-slate-400">GIF via {{ $comment->External_Media_Provider ?: 'provider eksternal' }}</figcaption>
                            </figure>
                        @else
                            <p class="mt-2 text-xs italic text-slate-500">GIF online ini tidak lagi tersedia.</p>
                        @endif
                    @else
                        <p class="mt-2 whitespace-pre-line break-words text-sm leading-6 text-slate-700 [overflow-wrap:anywhere]">{{ $comment->Comment_Text }}</p>
                        @if(($comment->Mentions ?? collect())->isNotEmpty())
                            <div class="mt-2 flex flex-wrap gap-1.5" aria-label="Pengguna yang disebut">
                                @foreach($comment->Mentions as $mention)
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-800">{{ '@'.($mention->User_Name ?: $mention->Username ?: 'Pengguna') }}</span>
                                @endforeach
                            </div>
                        @endif
                    @endif

                    @if($showComposer && !$isReply)
                        <button type="button" data-comment-reply="{{ $comment->Comment_ID }}" data-reply-name="{{ $displayName }}" data-comment-form="{{ $composerId }}" class="mt-2 min-h-9 rounded-lg px-2 text-xs font-black text-emerald-700 hover:bg-emerald-50">Balas</button>
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
        <div class="px-4 pb-3 sm:px-5"><a href="{{ $detailUrl }}#{{ $threadId }}" class="inline-flex min-h-10 items-center text-xs font-black text-slate-600 hover:text-emerald-700 hover:underline">Lihat {{ $otherCount }} komentar lainnya</a></div>
    @elseif(!$isCompact && $comments->hasPages())
        <div class="border-t border-slate-100 px-5 py-4 sm:px-7">{{ $comments->onEachSide(1)->links() }}</div>
    @endif

    @if($showComposer)
        @include('announcements.comments.composer', ['announcement' => $announcement, 'compact' => $isCompact, 'currentUserAvatar' => $currentUserAvatar ?? null])
    @endif
</section>
