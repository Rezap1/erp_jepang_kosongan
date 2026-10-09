<dialog data-announcement-sticker-dialog class="m-auto max-h-[82dvh] w-[min(94vw,520px)] overflow-hidden rounded-3xl bg-white p-0 shadow-2xl backdrop:bg-slate-950/50 max-sm:mb-0 max-sm:w-full max-sm:max-w-none max-sm:rounded-b-none">
    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
        <div><h2 class="font-black text-slate-900">Stiker</h2><p class="text-xs text-slate-500">Pilih stiker untuk langsung mengirim.</p></div>
        <button type="button" data-sticker-close class="flex h-11 w-11 items-center justify-center rounded-full text-xl text-slate-500 hover:bg-slate-100" aria-label="Tutup pilihan stiker">×</button>
    </div>
    <div class="max-h-[calc(82dvh-5rem)] overflow-y-auto overscroll-contain p-4 pb-[calc(1rem+env(safe-area-inset-bottom))] sm:p-5">
        <div class="mb-4 flex gap-2 overflow-x-auto pb-1">
            <button type="button" class="announcement-sticker-tab active" data-filter="all">Semua</button>
            <button type="button" class="announcement-sticker-tab" data-filter="favorites">Stiker Saya</button>
            @foreach($library['packs'] ?? [] as $pack)<button type="button" class="announcement-sticker-tab" data-filter="{{ $pack->Sticker_Pack_ID }}">{{ $pack->Name }}</button>@endforeach
        </div>
        <label class="relative block"><span class="sr-only">Cari stiker</span><input data-sticker-search type="search" placeholder="Cari stiker" class="w-full rounded-xl border-slate-200 pl-4 text-sm focus:border-emerald-500 focus:ring-emerald-500"></label>
        <div data-sticker-grid class="mt-4 grid grid-cols-4 gap-2 sm:grid-cols-5">
            @forelse($library['stickers'] ?? [] as $sticker)
                <div class="announcement-sticker-cell relative" data-pack="{{ $sticker->Sticker_Pack_ID }}" data-favorite="{{ $sticker->Is_Favorite ? '1' : '0' }}" data-name="{{ mb_strtolower($sticker->Name) }}">
                    <button type="button" data-sticker-send="{{ $sticker->Sticker_ID }}" class="flex aspect-square w-full items-center justify-center rounded-2xl bg-slate-50 p-2 transition hover:bg-emerald-50 active:scale-95"><img src="{{ $sticker->URL }}" alt="{{ $sticker->Name }}" loading="lazy" class="h-full w-full object-contain"></button>
                    <button type="button" data-sticker-favorite data-favorite-url="{{ route('announcement-stickers.favorite', $sticker->Sticker_ID) }}" class="absolute right-0 top-0 flex h-8 w-8 items-center justify-center rounded-full bg-white/95 text-sm shadow" aria-label="{{ $sticker->Is_Favorite ? 'Hapus dari' : 'Tambah ke' }} Stiker Saya">{{ $sticker->Is_Favorite ? '★' : '☆' }}</button>
                </div>
            @empty
                <p class="col-span-full py-10 text-center text-sm text-slate-500">Belum ada stiker tersedia.</p>
            @endforelse
        </div>
        <p data-sticker-empty class="hidden py-10 text-center text-sm text-slate-500">Belum ada stiker yang sesuai.</p>
    </div>
</dialog>

@once
    <style>
        .announcement-sticker-tab{white-space:nowrap;border-radius:999px;background:#f1f5f9;padding:.55rem .85rem;font-size:.75rem;font-weight:800;color:#475569}.announcement-sticker-tab.active{background:#059669;color:white}dialog[data-announcement-sticker-dialog]::backdrop{backdrop-filter:blur(3px)}@media(max-width:640px){dialog[data-announcement-sticker-dialog][open]{margin-top:auto}}
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const dialog = document.querySelector('[data-announcement-sticker-dialog]');
            if (!dialog) return;
            let filter = 'all';
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

            document.querySelectorAll('[data-sticker-open]').forEach((button) => button.addEventListener('click', () => {
                dialog.dataset.commentForm = button.dataset.commentForm;
                dialog.showModal();
            }));
            dialog.querySelector('[data-sticker-close]')?.addEventListener('click', () => dialog.close());
            dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
            dialog.querySelectorAll('.announcement-sticker-tab').forEach((button) => button.addEventListener('click', () => {
                dialog.querySelectorAll('.announcement-sticker-tab').forEach((tab) => tab.classList.remove('active'));
                button.classList.add('active');
                filter = button.dataset.filter;
                applyFilter();
            }));
            dialog.querySelector('[data-sticker-search]')?.addEventListener('input', applyFilter);
            dialog.querySelectorAll('[data-sticker-send]').forEach((button) => button.addEventListener('click', () => {
                const form = document.getElementById(dialog.dataset.commentForm);
                if (!form) return;
                form.elements.Comment_Type.value = 'STICKER';
                form.elements.Sticker_ID.value = button.dataset.stickerSend;
                form.elements.Comment_Text.required = false;
                form.elements.Comment_Text.value = '';
                form.querySelector('button[type="submit"]').disabled = true;
                form.submit();
            }));
            dialog.querySelectorAll('[data-sticker-favorite]').forEach((button) => button.addEventListener('click', async () => {
                const cell = button.closest('.announcement-sticker-cell');
                const favorite = cell.dataset.favorite === '1';
                const response = await fetch(button.dataset.favoriteUrl, {
                    method: favorite ? 'DELETE' : 'POST',
                    headers: {'X-CSRF-TOKEN': @js(csrf_token()), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                });
                if (!response.ok) return;
                cell.dataset.favorite = favorite ? '0' : '1';
                button.textContent = favorite ? '☆' : '★';
                button.setAttribute('aria-label', favorite ? 'Tambah ke Stiker Saya' : 'Hapus dari Stiker Saya');
                applyFilter();
            }));
            document.querySelectorAll('form[id^="announcement-comment-composer-"]').forEach((form) => form.addEventListener('submit', () => {
                form.querySelector('button[type="submit"]').disabled = true;
            }));
        });
    </script>
@endonce
