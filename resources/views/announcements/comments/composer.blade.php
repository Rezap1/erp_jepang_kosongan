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
        <form
            id="{{ $composerId }}"
            action="{{ route('announcement-comments.store', $announcement['Announcement_ID']) }}"
            method="POST"
            data-comment-composer
            data-mention-url="{{ route('announcement-comments.mentions', $announcement['Announcement_ID']) }}"
            data-gif-url="{{ route('announcement-comments.gifs', $announcement['Announcement_ID']) }}"
            class="relative min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white p-2.5 shadow-sm focus-within:border-emerald-400 focus-within:ring-4 focus-within:ring-emerald-50"
        >
            @csrf
            <input type="hidden" name="Comment_Type" value="TEXT">
            <input type="hidden" name="Sticker_ID" value="">
            <input type="hidden" name="External_GIF_ID" value="">
            <input type="hidden" name="Parent_Comment_ID" value="{{ $showValidation ? old('Parent_Comment_ID') : '' }}">
            <input type="hidden" name="_announcement_id" value="{{ $announcement['Announcement_ID'] }}">

            <div data-reply-banner class="mb-2 hidden items-center justify-between gap-2 rounded-xl bg-emerald-50 px-3 py-2 text-xs text-emerald-900">
                <span>Membalas <strong data-reply-name></strong></span>
                <button type="button" data-reply-cancel class="min-h-8 rounded-lg px-2 font-black hover:bg-emerald-100">Batal</button>
            </div>

            <div data-mention-chips class="hidden flex-wrap gap-1.5 px-2 pb-1"></div>
            <label for="{{ $composerId }}-text" class="sr-only">Tulis komentar</label>
            <textarea id="{{ $composerId }}-text" name="Comment_Text" rows="{{ $compact ? 2 : 3 }}" maxlength="2000" required autocomplete="off" placeholder="Tulis komentar..." aria-describedby="{{ $composerId }}-hint" class="block min-h-12 w-full resize-y border-0 bg-transparent p-2 text-sm leading-6 text-slate-800 placeholder:text-slate-400 focus:ring-0">{{ $showValidation ? old('Comment_Text') : '' }}</textarea>
            <p id="{{ $composerId }}-hint" class="px-2 pb-1 text-[11px] text-slate-400">Ketik @ diikuti nama untuk mention.</p>
            <div data-mention-results class="absolute inset-x-3 z-20 hidden max-h-56 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-xl"></div>

            @if($showValidation)
                @error('Comment_Text')<p class="px-2 pb-1 text-sm font-semibold text-rose-600">{{ $message }}</p>@enderror
                @error('Sticker_ID')<p class="px-2 pb-1 text-sm font-semibold text-rose-600">{{ $message }}</p>@enderror
                @error('External_GIF_ID')<p class="px-2 pb-1 text-sm font-semibold text-rose-600">{{ $message }}</p>@enderror
                @error('Parent_Comment_ID')<p class="px-2 pb-1 text-sm font-semibold text-rose-600">{{ $message }}</p>@enderror
                @error('Mention_User_IDs')<p class="px-2 pb-1 text-sm font-semibold text-rose-600">{{ $message }}</p>@enderror
            @endif

            <div class="mt-1 flex flex-wrap items-center justify-between gap-2">
                <button type="button" data-sticker-open data-comment-form="{{ $composerId }}" class="inline-flex min-h-11 items-center rounded-xl px-3 text-sm font-bold text-slate-600 hover:bg-slate-100" aria-label="Buka pilihan stiker dan GIF">Stiker & GIF</button>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-600 px-5 text-sm font-black text-white shadow-sm hover:bg-emerald-700 disabled:opacity-60">Kirim</button>
            </div>
        </form>
    </div>
</div>

@once
<script>
document.addEventListener('DOMContentLoaded', () => {
    const hideResults = (form) => {
        const results = form.querySelector('[data-mention-results]');
        results.replaceChildren();
        results.classList.add('hidden');
    };

    const renderChips = (form) => {
        const container = form.querySelector('[data-mention-chips]');
        const inputs = [...form.querySelectorAll('input[name="Mention_User_IDs[]"]')];
        container.replaceChildren();
        container.classList.toggle('hidden', inputs.length === 0);
        container.classList.toggle('flex', inputs.length > 0);
        inputs.forEach((input) => {
            const chip = document.createElement('span');
            chip.className = 'inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-800';
            chip.append(document.createTextNode('@' + input.dataset.name));
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'rounded-full px-1 hover:bg-emerald-100';
            remove.setAttribute('aria-label', 'Hapus mention ' + input.dataset.name);
            remove.textContent = '×';
            remove.addEventListener('click', () => { input.remove(); renderChips(form); });
            chip.append(remove);
            container.append(chip);
        });
    };

    document.querySelectorAll('[data-comment-composer]').forEach((form) => {
        const textarea = form.elements.Comment_Text;
        let timer;
        let request;
        textarea.addEventListener('input', () => {
            window.clearTimeout(timer);
            request?.abort();
            const beforeCursor = textarea.value.slice(0, textarea.selectionStart);
            const match = beforeCursor.match(/(?:^|\s)@([^\s@]{2,50})$/u);
            if (!match) { hideResults(form); return; }
            timer = window.setTimeout(async () => {
                request = new AbortController();
                try {
                    const url = new URL(form.dataset.mentionUrl, window.location.origin);
                    url.searchParams.set('q', match[1]);
                    const response = await fetch(url, {headers: {'Accept': 'application/json'}, signal: request.signal});
                    if (!response.ok) throw new Error('mention');
                    const people = (await response.json()).results || [];
                    const results = form.querySelector('[data-mention-results]');
                    results.replaceChildren();
                    people.forEach((person) => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left hover:bg-slate-50';
                        const identity = document.createElement('span');
                        identity.className = 'min-w-0';
                        const name = document.createElement('strong');
                        name.className = 'block truncate text-sm text-slate-900';
                        name.textContent = person.name;
                        const details = document.createElement('span');
                        details.className = 'block truncate text-xs text-slate-500';
                        details.textContent = [person.username ? '@' + person.username : '', person.role].filter(Boolean).join(' · ');
                        identity.append(name, details);
                        button.append(identity);
                        button.addEventListener('click', () => {
                            if (!form.querySelector(`input[name="Mention_User_IDs[]"][value="${CSS.escape(person.user_id)}"]`)
                                && form.querySelectorAll('input[name="Mention_User_IDs[]"]').length < 5) {
                                const input = document.createElement('input');
                                input.type = 'hidden'; input.name = 'Mention_User_IDs[]'; input.value = person.user_id; input.dataset.name = person.name;
                                form.append(input);
                            }
                            const cursor = textarea.selectionStart;
                            const prefix = textarea.value.slice(0, cursor);
                            const start = prefix.lastIndexOf('@' + match[1]);
                            textarea.value = textarea.value.slice(0, start) + '@' + person.name + ' ' + textarea.value.slice(cursor);
                            textarea.focus();
                            textarea.setSelectionRange(start + person.name.length + 2, start + person.name.length + 2);
                            renderChips(form); hideResults(form);
                        });
                        results.append(button);
                    });
                    results.classList.toggle('hidden', people.length === 0);
                } catch (error) { if (error.name !== 'AbortError') hideResults(form); }
            }, 220);
        });

        form.querySelector('[data-reply-cancel]')?.addEventListener('click', () => {
            form.elements.Parent_Comment_ID.value = '';
            form.querySelector('[data-reply-banner]').classList.add('hidden');
            form.querySelector('[data-reply-banner]').classList.remove('flex');
        });
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-comment-reply]');
        if (!button) return;
        const form = document.getElementById(button.dataset.commentForm);
        if (!form) return;
        form.elements.Parent_Comment_ID.value = button.dataset.commentReply;
        form.querySelector('[data-reply-name]').textContent = button.dataset.replyName || 'komentar';
        form.querySelector('[data-reply-banner]').classList.remove('hidden');
        form.querySelector('[data-reply-banner]').classList.add('flex');
        form.scrollIntoView({behavior: 'smooth', block: 'center'});
        form.elements.Comment_Text.focus({preventScroll: true});
    });
});
</script>
@endonce
