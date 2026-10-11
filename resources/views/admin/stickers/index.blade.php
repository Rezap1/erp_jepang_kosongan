@extends('layouts.app')
@section('header', 'Kelola Stiker & GIF')
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <x-page-header title="Library Stiker & GIF" description="Kelola pack serta media internal untuk komentar pengumuman." :breadcrumbs="['Dashboard' => route('dashboard'), 'Stiker' => '#']" />

    <div class="grid gap-5 lg:grid-cols-[.8fr_1.2fr]">
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-lg font-black text-slate-900">Pack Media</h2>
            <p class="mt-1 text-sm text-slate-500">Kelompokkan stiker dan GIF agar mudah dijelajahi.</p>
            <form action="{{ route('admin.stickers.packs.store') }}" method="POST" class="mt-5 grid gap-3 rounded-2xl bg-slate-50 p-4">
                @csrf
                <x-input name="Name" label="Nama Pack" required placeholder="Contoh: Reaksi" />
                <x-input name="Sort_Order" type="number" min="0" label="Urutan" value="0" />
                <label class="flex items-center gap-2 text-sm font-bold text-slate-700"><input type="checkbox" name="Is_Active" value="1" checked class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"> Aktif</label>
                <x-button type="submit" variant="primary">Buat Pack</x-button>
            </form>
            <div class="mt-5 space-y-3">
                @forelse($packs as $pack)
                    <form action="{{ route('admin.stickers.packs.update', $pack->Sticker_Pack_ID) }}" method="POST" class="grid grid-cols-[1fr_5rem_auto] items-end gap-2 rounded-xl border border-slate-100 p-3">
                        @csrf @method('PUT')
                        <x-input name="Name" label="Nama" value="{{ $pack->Name }}" required />
                        <x-input name="Sort_Order" type="number" label="Urut" value="{{ $pack->Sort_Order }}" />
                        <div><label class="mb-2 flex items-center gap-1 text-xs font-bold"><input type="checkbox" name="Is_Active" value="1" @checked($pack->Is_Active)> Aktif</label><button class="min-h-10 rounded-lg bg-slate-900 px-3 text-xs font-bold text-white">Simpan</button></div>
                    </form>
                @empty
                    <p class="py-5 text-center text-sm text-slate-500">Belum ada pack media.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-lg font-black text-slate-900">Media Internal</h2>
            <p class="mt-1 text-sm text-slate-500">PNG, WEBP, atau GIF; maksimal 2 MB dan 1024 x 1024 px.</p>
            <form action="{{ route('admin.stickers.store') }}" method="POST" enctype="multipart/form-data" class="mt-5 grid gap-3 rounded-2xl bg-slate-50 p-4 sm:grid-cols-2">
                @csrf
                <x-select name="Sticker_Pack_ID" label="Pack" required><option value="">Pilih pack</option>@foreach($packs as $pack)<option value="{{ $pack->Sticker_Pack_ID }}">{{ $pack->Name }}</option>@endforeach</x-select>
                <x-input name="Name" label="Nama Media" required />
                <x-input name="Sticker_File" type="file" accept="image/png,image/webp,image/gif" label="File Media" required />
                <x-input name="Sort_Order" type="number" min="0" label="Urutan" value="0" />
                <label class="flex items-center gap-2 text-sm font-bold text-slate-700"><input type="checkbox" name="Is_Active" value="1" checked> Aktif</label>
                <x-button type="submit" variant="primary">Unggah Media</x-button>
            </form>
            <div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2">
                @forelse($stickers as $sticker)
                    <article class="rounded-2xl border border-slate-100 bg-slate-50 p-3">
                        <div class="relative aspect-square rounded-xl bg-white p-2">
                            <img src="{{ $sticker->URL }}" alt="{{ $sticker->Name }}" class="h-full w-full object-contain">
                            @if($sticker->Mime_Type === 'image/gif')<span class="absolute right-2 top-2 rounded-full bg-violet-600 px-2 py-1 text-[10px] font-black text-white">GIF</span>@endif
                        </div>
                        <form action="{{ route('admin.stickers.update', $sticker->Sticker_ID) }}" method="POST" class="mt-3 grid gap-2">
                            @csrf @method('PUT')
                            <input name="Name" value="{{ $sticker->Name }}" aria-label="Nama media" required class="rounded-lg border-slate-200 text-sm">
                            <select name="Sticker_Pack_ID" aria-label="Pack media" class="rounded-lg border-slate-200 text-sm">@foreach($packs as $pack)<option value="{{ $pack->Sticker_Pack_ID }}" @selected($pack->Sticker_Pack_ID === $sticker->Sticker_Pack_ID)>{{ $pack->Name }}</option>@endforeach</select>
                            <div class="flex items-center justify-between gap-2">
                                <input name="Sort_Order" value="{{ $sticker->Sort_Order }}" type="number" min="0" aria-label="Urutan media" class="w-20 rounded-lg border-slate-200 text-sm">
                                <label class="text-xs font-bold"><input type="checkbox" name="Is_Active" value="1" @checked($sticker->Is_Active)> Aktif</label>
                                <button class="min-h-10 rounded-lg bg-slate-900 px-3 text-xs font-bold text-white">Simpan</button>
                            </div>
                        </form>
                    </article>
                @empty
                    <p class="col-span-full py-10 text-center text-sm text-slate-500">Belum ada media tersedia.</p>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection
