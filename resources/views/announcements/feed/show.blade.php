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

    <section class="rounded-3xl border border-slate-200 bg-white shadow-sm" aria-labelledby="comments-heading">
        <header class="flex items-center justify-between border-b border-slate-100 px-5 py-5 sm:px-7"><div><p class="text-xs font-bold uppercase tracking-[.18em] text-emerald-600">Percakapan</p><h2 id="comments-heading" class="mt-1 text-lg font-black text-slate-900">💬 {{ $comments->total() }} Komentar</h2></div></header>
        <div class="divide-y divide-slate-100 px-5 sm:px-7">
            @forelse($comments as $comment)
                @php $displayName = $comment->User_Name ?: $comment->Username ?: 'Pengguna'; $initials = collect(preg_split('/\s+/', trim($displayName)))->filter()->take(2)->map(fn($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode(''); @endphp
                <article class="flex gap-3 py-5">
                    @if($comment->Avatar_URL ?? null)<img src="{{ $comment->Avatar_URL }}" alt="Foto profil {{ $displayName }}" class="h-10 w-10 shrink-0 rounded-full border-2 border-emerald-200 object-cover">@else<div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-slate-800 to-emerald-600 text-xs font-black text-white" aria-hidden="true">{{ $initials ?: 'US' }}</div>@endif
                    <div class="min-w-0 flex-1"><div class="flex flex-wrap items-start justify-between gap-2"><div><h3 class="text-sm font-black text-slate-900">{{ $displayName }}</h3><p class="mt-0.5 text-xs text-slate-500">{{ ucwords(strtolower($comment->Role_Name ?: 'Pengguna')) }} • {{ \Carbon\Carbon::parse($comment->Created_At)->locale('id')->diffForHumans() }}</p></div>@if($comment->Can_Delete)<form action="{{ route('announcement-comments.destroy', [$announcement['Announcement_ID'], $comment->Comment_ID]) }}" method="POST" onsubmit="return confirm('Hapus komentar ini?')">@csrf @method('DELETE')<button class="min-h-10 rounded-lg px-2 text-xs font-bold text-slate-400 hover:bg-rose-50 hover:text-rose-600" aria-label="Hapus komentar {{ $displayName }}">Hapus</button></form>@endif</div>
                        @if($comment->Comment_Type === 'STICKER' && $comment->Sticker_URL)<img src="{{ $comment->Sticker_URL }}" alt="Stiker {{ $comment->Sticker_Name ?: 'komentar' }}" loading="lazy" class="mt-3 h-32 w-32 object-contain">@else<p class="mt-3 whitespace-pre-line break-words text-sm leading-6 text-slate-700">{{ $comment->Comment_Text }}</p>@endif
                    </div>
                </article>
            @empty
                <div class="py-12 text-center"><p class="font-bold text-slate-700">Belum ada komentar.</p><p class="mt-1 text-sm text-slate-500">Jadilah yang pertama berkomentar.</p></div>
            @endforelse
        </div>
        @if($comments->hasPages())<div class="border-t border-slate-100 px-5 py-4 sm:px-7">{{ $comments->onEachSide(1)->links() }}</div>@endif

        <div class="border-t border-slate-200 bg-slate-50 p-4 sm:p-6">
            <form id="commentComposer" action="{{ route('announcement-comments.store', $announcement['Announcement_ID']) }}" method="POST" class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm focus-within:border-emerald-400 focus-within:ring-4 focus-within:ring-emerald-50">@csrf
                <input type="hidden" name="Comment_Type" value="TEXT"><input type="hidden" name="Sticker_ID" value="">
                <label for="commentText" class="sr-only">Tulis komentar</label><textarea id="commentText" name="Comment_Text" rows="3" maxlength="2000" required placeholder="Tulis komentar..." class="w-full resize-none border-0 p-2 text-sm leading-6 focus:ring-0">{{ old('Comment_Text') }}</textarea>
                @error('Comment_Text')<p class="px-2 pb-1 text-sm font-semibold text-rose-600">{{ $message }}</p>@enderror
                @error('Sticker_ID')<p class="px-2 pb-1 text-sm font-semibold text-rose-600">{{ $message }}</p>@enderror
                <div class="mt-2 flex items-center justify-between gap-3"><button type="button" onclick="document.getElementById('stickerDialog').showModal()" class="inline-flex min-h-11 items-center rounded-xl px-3 text-sm font-bold text-slate-600 hover:bg-slate-100" aria-label="Buka pilihan stiker">☺ Stiker</button><button type="submit" class="inline-flex min-h-11 items-center rounded-xl bg-emerald-600 px-5 text-sm font-black text-white shadow-sm hover:bg-emerald-700 disabled:opacity-60">Kirim</button></div>
            </form>
        </div>
    </section>
</div>

<dialog id="stickerDialog" class="m-auto max-h-[82vh] w-[min(94vw,520px)] overflow-hidden rounded-3xl bg-white p-0 shadow-2xl backdrop:bg-slate-950/50 max-sm:mb-0 max-sm:w-full max-sm:max-w-none max-sm:rounded-b-none">
    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 class="font-black text-slate-900">Stiker</h2><p class="text-xs text-slate-500">Pilih stiker untuk langsung mengirim.</p></div><button onclick="document.getElementById('stickerDialog').close()" class="flex h-11 w-11 items-center justify-center rounded-full text-xl text-slate-500 hover:bg-slate-100" aria-label="Tutup pilihan stiker">×</button></div>
    <div class="max-h-[65vh] overflow-y-auto p-4 sm:p-5">
        <div class="mb-4 flex gap-2 overflow-x-auto pb-1"><button type="button" class="sticker-tab active" data-filter="all">Semua</button><button type="button" class="sticker-tab" data-filter="favorites">Stiker Saya</button>@foreach($library['packs'] as $pack)<button type="button" class="sticker-tab" data-filter="{{ $pack->Sticker_Pack_ID }}">{{ $pack->Name }}</button>@endforeach</div>
        <label class="relative block"><span class="sr-only">Cari stiker</span><input id="stickerSearch" type="search" placeholder="Cari stiker" class="w-full rounded-xl border-slate-200 pl-4 text-sm focus:border-emerald-500 focus:ring-emerald-500"></label>
        <div id="stickerGrid" class="mt-4 grid grid-cols-4 gap-2 sm:grid-cols-5">
            @forelse($library['stickers'] as $sticker)
                <div class="sticker-cell relative" data-pack="{{ $sticker->Sticker_Pack_ID }}" data-favorite="{{ $sticker->Is_Favorite ? '1' : '0' }}" data-name="{{ strtolower($sticker->Name) }}">
                    <button type="button" class="flex aspect-square w-full items-center justify-center rounded-2xl bg-slate-50 p-2 transition hover:bg-emerald-50 active:scale-95" onclick="sendSticker(@js($sticker->Sticker_ID))"><img src="{{ $sticker->URL }}" alt="{{ $sticker->Name }}" loading="lazy" class="h-full w-full object-contain"></button>
                    <button type="button" onclick="toggleFavorite(this,@js($sticker->Sticker_ID))" class="absolute right-0 top-0 flex h-8 w-8 items-center justify-center rounded-full bg-white/95 text-sm shadow" aria-label="{{ $sticker->Is_Favorite ? 'Hapus dari' : 'Tambah ke' }} Stiker Saya">{{ $sticker->Is_Favorite ? '★' : '☆' }}</button>
                </div>
            @empty
                <p class="col-span-full py-10 text-center text-sm text-slate-500">Belum ada stiker tersedia.</p>
            @endforelse
        </div>
        <p id="stickerEmpty" class="hidden py-10 text-center text-sm text-slate-500">Belum ada stiker favorit.</p>
    </div>
</dialog>
<style>.sticker-tab{white-space:nowrap;border-radius:999px;background:#f1f5f9;padding:.55rem .85rem;font-size:.75rem;font-weight:800;color:#475569}.sticker-tab.active{background:#059669;color:white}dialog::backdrop{backdrop-filter:blur(3px)}@media(max-width:640px){#stickerDialog[open]{margin-top:auto}}</style>
<script>
function sendSticker(id){const f=document.getElementById('commentComposer');f.Comment_Type.value='STICKER';f.Sticker_ID.value=id;f.Comment_Text.required=false;f.Comment_Text.value='';f.querySelector('button[type="submit"]').disabled=true;f.submit()}
async function toggleFavorite(button,id){const favorite=button.textContent.trim()==='★';await fetch(`/announcement-stickers/${encodeURIComponent(id)}/favorite`,{method:favorite?'DELETE':'POST',headers:{'X-CSRF-TOKEN':@js(csrf_token()),'Accept':'application/json'}});button.textContent=favorite?'☆':'★';button.closest('.sticker-cell').dataset.favorite=favorite?'0':'1'}
document.addEventListener('DOMContentLoaded',()=>{const form=document.getElementById('commentComposer');form.addEventListener('submit',()=>form.querySelector('button[type="submit"]').disabled=true);let filter='all';const apply=()=>{let shown=0,q=document.getElementById('stickerSearch').value.toLowerCase();document.querySelectorAll('.sticker-cell').forEach(el=>{const ok=(filter==='all'||filter==='favorites'&&el.dataset.favorite==='1'||el.dataset.pack===filter)&&el.dataset.name.includes(q);el.classList.toggle('hidden',!ok);if(ok)shown++});document.getElementById('stickerEmpty').classList.toggle('hidden',shown>0)};document.querySelectorAll('.sticker-tab').forEach(btn=>btn.addEventListener('click',()=>{document.querySelectorAll('.sticker-tab').forEach(x=>x.classList.remove('active'));btn.classList.add('active');filter=btn.dataset.filter;apply()}));document.getElementById('stickerSearch').addEventListener('input',apply)});
</script>
@endsection
