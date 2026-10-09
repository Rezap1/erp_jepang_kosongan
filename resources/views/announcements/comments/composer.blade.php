@php
    $composerKey = md5((string) $announcement['Announcement_ID']);
    $composerId = 'announcement-comment-composer-'.$composerKey;
    $oldContextMatches = old('_announcement_id') === (string) $announcement['Announcement_ID'];
    $showValidation = ! $compact || $oldContextMatches;
    $currentName = auth()->user()->Full_Name ?? auth()->user()->Username ?? 'Anda';
@endphp

<div class="border-t border-slate-200 bg-slate-50 {{ $compact ? 'p-3 sm:p-4' : 'p-4 sm:p-6' }}">
    <div class="flex min-w-0 items-start gap-2.5 sm:gap-3">
        <x-resolved-avatar :name="$currentName" :url="$currentUserAvatar" class="mt-1 h-9 w-9 sm:h-10 sm:w-10" text-size="text-[10px] sm:text-xs" />
        <form id="{{ $composerId }}" action="{{ route('announcement-comments.store', $announcement['Announcement_ID']) }}" method="POST" class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-2.5 shadow-sm focus-within:border-emerald-400 focus-within:ring-4 focus-within:ring-emerald-50">
            @csrf
            <input type="hidden" name="Comment_Type" value="TEXT">
            <input type="hidden" name="Sticker_ID" value="">
            <input type="hidden" name="_announcement_id" value="{{ $announcement['Announcement_ID'] }}">
            <label for="{{ $composerId }}-text" class="sr-only">Tulis komentar</label>
            <textarea id="{{ $composerId }}-text" name="Comment_Text" rows="{{ $compact ? 2 : 3 }}" maxlength="2000" required placeholder="Tulis komentar..." class="block min-h-12 w-full resize-y border-0 bg-transparent p-2 text-sm leading-6 text-slate-800 placeholder:text-slate-400 focus:ring-0">{{ $showValidation ? old('Comment_Text') : '' }}</textarea>
            @if($showValidation)
                @error('Comment_Text')<p class="px-2 pb-1 text-sm font-semibold text-rose-600">{{ $message }}</p>@enderror
                @error('Sticker_ID')<p class="px-2 pb-1 text-sm font-semibold text-rose-600">{{ $message }}</p>@enderror
            @endif
            <div class="mt-1 flex flex-wrap items-center justify-between gap-2">
                <button type="button" data-sticker-open data-comment-form="{{ $composerId }}" class="inline-flex min-h-11 items-center rounded-xl px-3 text-sm font-bold text-slate-600 hover:bg-slate-100" aria-label="Buka pilihan stiker">☺ Stiker</button>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-600 px-5 text-sm font-black text-white shadow-sm hover:bg-emerald-700 disabled:opacity-60">Kirim</button>
            </div>
        </form>
    </div>
</div>
