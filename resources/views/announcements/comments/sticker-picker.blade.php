<dialog data-announcement-sticker-dialog class="m-auto max-h-[82dvh] w-[min(94vw,560px)] overflow-hidden rounded-3xl bg-white p-0 shadow-2xl backdrop:bg-slate-950/50 max-sm:mb-0 max-sm:w-full max-sm:max-w-none max-sm:rounded-b-none">
    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
        <div><h2 class="font-black text-slate-900">Stiker & GIF</h2><p class="text-xs text-slate-500">Pilih media untuk langsung mengirim.</p></div>
        <button type="button" data-sticker-close class="flex h-11 w-11 items-center justify-center rounded-full text-xl text-slate-500 hover:bg-slate-100" aria-label="Tutup pilihan media">×</button>
    </div>
    <div class="border-b border-slate-100 px-4 pt-3 sm:px-5">
        <div class="flex gap-2" role="tablist">
            <button type="button" data-media-panel-button="internal" class="announcement-media-panel-tab active" role="tab">Media Internal</button>
            <button type="button" data-media-panel-button="external" class="announcement-media-panel-tab" role="tab">GIF Online</button>
        </div>
    </div>
    <div class="max-h-[calc(82dvh-8rem)] overflow-y-auto overscroll-contain p-4 pb-[calc(1rem+env(safe-area-inset-bottom))] sm:p-5">
        <section data-media-panel="internal">
            <div class="mb-4 flex gap-2 overflow-x-auto pb-1">
                <button type="button" class="announcement-sticker-tab active" data-filter="all">Semua</button>
                <button type="button" class="announcement-sticker-tab" data-filter="favorites">Favorit Saya</button>
                @foreach($library['packs'] ?? [] as $pack)<button type="button" class="announcement-sticker-tab" data-filter="{{ $pack->Sticker_Pack_ID }}">{{ $pack->Name }}</button>@endforeach
            </div>
            <label class="relative block"><span class="sr-only">Cari media internal</span><input data-sticker-search type="search" placeholder="Cari stiker atau GIF internal" class="w-full rounded-xl border-slate-200 pl-4 text-sm focus:border-emerald-500 focus:ring-emerald-500"></label>
            <div data-sticker-grid class="mt-4 grid grid-cols-4 gap-2 sm:grid-cols-5">
                @forelse($library['stickers'] ?? [] as $sticker)
                    <div class="announcement-sticker-cell relative" data-pack="{{ $sticker->Sticker_Pack_ID }}" data-favorite="{{ $sticker->Is_Favorite ? '1' : '0' }}" data-name="{{ mb_strtolower($sticker->Name) }}">
                        <button type="button" data-sticker-send="{{ $sticker->Sticker_ID }}" class="relative flex aspect-square w-full items-center justify-center rounded-2xl bg-slate-50 p-2 transition hover:bg-emerald-50 active:scale-95">
                            <img src="{{ $sticker->URL }}" alt="{{ $sticker->Name }}" loading="lazy" class="h-full w-full object-contain">
                            @if($sticker->Mime_Type === 'image/gif')<span class="absolute bottom-1 left-1 rounded bg-violet-600 px-1.5 py-0.5 text-[9px] font-black text-white">GIF</span>@endif
                        </button>
                        <button type="button" data-sticker-favorite data-favorite-url="{{ route('announcement-stickers.favorite', $sticker->Sticker_ID) }}" class="absolute right-0 top-0 flex h-8 w-8 items-center justify-center rounded-full bg-white/95 text-sm shadow" aria-label="{{ $sticker->Is_Favorite ? 'Hapus dari' : 'Tambah ke' }} Favorit Saya">{{ $sticker->Is_Favorite ? '★' : '☆' }}</button>
                    </div>
                @empty
                    <p class="col-span-full py-10 text-center text-sm text-slate-500">Belum ada stiker tersedia. Admin dapat menambahkan stiker atau GIF internal.</p>
                @endforelse
            </div>
            <p data-sticker-empty class="hidden py-10 text-center text-sm text-slate-500">Belum ada media yang sesuai.</p>
        </section>

        <section data-media-panel="external" class="hidden">
            <form data-gif-search-form class="flex gap-2">
                <label class="min-w-0 flex-1"><span class="sr-only">Cari GIF online</span><input name="q" type="search" minlength="2" maxlength="50" required placeholder="Cari GIF online" class="w-full rounded-xl border-slate-200 text-sm focus:border-emerald-500 focus:ring-emerald-500"></label>
                <button class="min-h-11 rounded-xl bg-slate-900 px-4 text-sm font-black text-white">Cari</button>
            </form>
            <p data-gif-status class="mt-3 text-sm text-slate-500">Pencarian online hanya aktif bila provider aman telah dikonfigurasi.</p>
            <div data-gif-grid class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3"></div>
            <p data-gif-attribution class="mt-4 hidden text-center text-[10px] font-semibold uppercase tracking-wide text-slate-400"></p>
        </section>
    </div>
</dialog>

@once
<style>
    .announcement-sticker-tab,.announcement-media-panel-tab{white-space:nowrap;border-radius:999px;background:#f1f5f9;padding:.55rem .85rem;font-size:.75rem;font-weight:800;color:#475569}.announcement-sticker-tab.active,.announcement-media-panel-tab.active{background:#059669;color:white}dialog[data-announcement-sticker-dialog]::backdrop{backdrop-filter:blur(3px)}@media(max-width:640px){dialog[data-announcement-sticker-dialog][open]{margin-top:auto}}
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.querySelector('[data-announcement-sticker-dialog]');
    if (!dialog) return;
    let filter = 'all';

    const activeForm = () => document.getElementById(dialog.dataset.commentForm);
    const applyFilter = () => {
        const query = (dialog.querySelector('[data-sticker-search]')?.value || '').toLocaleLowerCase();
        let shown = 0;
        dialog.querySelectorAll('.announcement-sticker-cell').forEach((cell) => {
            const matchesFilter = filter === 'all' || (filter === 'favorites' && cell.dataset.favorite === '1') || cell.dataset.pack === filter;
            const visible = matchesFilter && (cell.dataset.name || '').includes(query);
            cell.classList.toggle('hidden', !visible);
            if (visible) shown++;
        });
        dialog.querySelector('[data-sticker-empty]')?.classList.toggle('hidden', shown > 0 || dialog.querySelectorAll('.announcement-sticker-cell').length === 0);
    };
    const submitMedia = (type, id) => {
        const form = activeForm();
        if (!form) return;
        form.elements.Comment_Type.value = type;
        form.elements.Sticker_ID.value = type === 'STICKER' ? id : '';
        form.elements.External_GIF_ID.value = type === 'GIF' ? id : '';
        form.querySelectorAll('input[name="Mention_User_IDs[]"]').forEach((input) => input.remove());
        form.querySelector('[data-mention-chips]')?.replaceChildren();
        form.elements.Comment_Text.required = false;
        form.elements.Comment_Text.value = '';
        form.querySelector('button[type="submit"]').disabled = true;
        form.requestSubmit();
    };

    document.querySelectorAll('[data-sticker-open]').forEach((button) => button.addEventListener('click', () => {
        dialog.dataset.commentForm = button.dataset.commentForm;
        dialog.showModal();
    }));
    dialog.querySelector('[data-sticker-close]')?.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });

    dialog.querySelectorAll('[data-media-panel-button]').forEach((button) => button.addEventListener('click', () => {
        dialog.querySelectorAll('[data-media-panel-button]').forEach((tab) => tab.classList.toggle('active', tab === button));
        dialog.querySelectorAll('[data-media-panel]').forEach((panel) => panel.classList.toggle('hidden', panel.dataset.mediaPanel !== button.dataset.mediaPanelButton));
    }));
    dialog.querySelectorAll('.announcement-sticker-tab').forEach((button) => button.addEventListener('click', () => {
        dialog.querySelectorAll('.announcement-sticker-tab').forEach((tab) => tab.classList.remove('active'));
        button.classList.add('active'); filter = button.dataset.filter; applyFilter();
    }));
    dialog.querySelector('[data-sticker-search]')?.addEventListener('input', applyFilter);
    dialog.querySelectorAll('[data-sticker-send]').forEach((button) => button.addEventListener('click', () => submitMedia('STICKER', button.dataset.stickerSend)));

    dialog.querySelectorAll('[data-sticker-favorite]').forEach((button) => button.addEventListener('click', async () => {
        const cell = button.closest('.announcement-sticker-cell');
        const favorite = cell.dataset.favorite === '1';
        const response = await fetch(button.dataset.favoriteUrl, {method: favorite ? 'DELETE' : 'POST', headers: {'X-CSRF-TOKEN': @js(csrf_token()), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}});
        if (!response.ok) return;
        cell.dataset.favorite = favorite ? '0' : '1'; button.textContent = favorite ? '☆' : '★';
        button.setAttribute('aria-label', favorite ? 'Tambah ke Favorit Saya' : 'Hapus dari Favorit Saya'); applyFilter();
    }));

    dialog.querySelector('[data-gif-search-form]')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = activeForm();
        const status = dialog.querySelector('[data-gif-status]');
        const grid = dialog.querySelector('[data-gif-grid]');
        const attribution = dialog.querySelector('[data-gif-attribution]');
        grid.replaceChildren(); attribution.classList.add('hidden'); status.textContent = 'Mencari GIF…';
        if (!form?.dataset.gifUrl) { status.textContent = 'Pencarian GIF online tidak tersedia.'; return; }
        try {
            const url = new URL(form.dataset.gifUrl, window.location.origin);
            url.searchParams.set('q', new FormData(event.currentTarget).get('q'));
            const response = await fetch(url, {headers: {'Accept': 'application/json'}});
            const payload = await response.json();
            if (!response.ok) throw new Error('request');
            status.textContent = payload.message || (payload.results?.length ? 'Pilih GIF untuk mengirim.' : 'GIF tidak ditemukan.');
            (payload.results || []).forEach((gif) => {
                const button = document.createElement('button');
                button.type = 'button'; button.className = 'overflow-hidden rounded-xl bg-slate-100 text-left transition hover:ring-2 hover:ring-emerald-400';
                const image = document.createElement('img');
                image.src = gif.preview_url; image.alt = gif.alt || gif.title || 'GIF'; image.loading = 'lazy'; image.className = 'aspect-video h-full w-full object-cover';
                button.append(image); button.addEventListener('click', () => submitMedia('GIF', gif.id)); grid.append(button);
            });
            if (payload.attribution) { attribution.textContent = payload.attribution; attribution.classList.remove('hidden'); }
        } catch { status.textContent = 'Pencarian GIF online sedang tidak tersedia. Gunakan media internal.'; }
    });

    document.querySelectorAll('[data-comment-composer]').forEach((form) => form.addEventListener('submit', () => { form.querySelector('button[type="submit"]').disabled = true; }));
});
</script>
@endonce
