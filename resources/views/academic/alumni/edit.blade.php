@extends('layouts.app')

@section('header', 'Edit Alumni')

@section('content')
@php
    $isWms = strtoupper((string) ($alumni['Source_Type'] ?? '')) === 'WMS';
    $lockWmsNik = $isWms && filled($alumni['NIK'] ?? null);
@endphp
<div class="mx-auto max-w-4xl space-y-6">
    <div>
        <a href="{{ route('alumni.show', $alumni['Alumni_ID']) }}" class="text-xs font-black text-indigo-700 hover:text-indigo-900">← Kembali ke Detail Alumni</a>
        <h1 class="mt-2 text-2xl font-black text-slate-900">Edit data Alumni</h1>
        <p class="mt-1 text-sm text-slate-500">Sumber, Student_ID, dan snapshot pendidikan adalah data immutable.</p>
    </div>

    @if($errors->any())
        <div class="border-l-4 border-rose-500 bg-rose-50 px-4 py-3 text-sm text-rose-900">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('alumni.update', $alumni['Alumni_ID']) }}" enctype="multipart/form-data" class="space-y-6 border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @csrf
        @method('PUT')

        <fieldset class="space-y-4">
            <legend class="text-sm font-black text-slate-900">Sumber & identitas</legend>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Sumber</span>
                    <div class="rounded-lg border border-slate-200 bg-slate-100 px-3 py-2.5 text-sm font-bold text-slate-700">{{ $isWms ? 'Dari Siswa WMS' : 'Input Manual' }}</div>
                </div>
                <div>
                    <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Alumni_ID</span>
                    <div class="rounded-lg border border-slate-200 bg-slate-100 px-3 py-2.5 font-mono text-sm text-slate-700">{{ $alumni['Alumni_ID'] }}</div>
                </div>
                <div>
                    <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Student_ID</span>
                    <div class="rounded-lg border border-slate-200 bg-slate-100 px-3 py-2.5 font-mono text-sm text-slate-700">{{ $alumni['Student_ID'] ?: 'Tidak ada (manual)' }}</div>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Nama lengkap</span>
                    <input type="text" name="Full_Name" value="{{ old('Full_Name', $alumni['Full_Name'] ?? '') }}" @readonly($isWms) class="w-full rounded-lg border border-slate-300 {{ $isWms ? 'bg-slate-100' : 'bg-slate-50' }} px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">NIK</span>
                    <input type="text" name="NIK" value="{{ old('NIK', $alumni['NIK'] ?? '') }}" @readonly($lockWmsNik) class="w-full rounded-lg border border-slate-300 {{ $lockWmsNik ? 'bg-slate-100' : 'bg-slate-50' }} px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </label>
            </div>
            @if($isWms && $student)
                <p class="text-xs text-slate-500">Identitas mengikuti Student WMS {{ $student['Student_ID'] ?? $alumni['Student_ID'] }} dan tidak dapat diubah dari form ini.</p>
            @endif
        </fieldset>

        <fieldset class="space-y-4 border-t border-slate-100 pt-5">
            <legend class="text-sm font-black text-slate-900">Data keberangkatan</legend>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Nama orang tua <span class="text-rose-600">*</span></span>
                    <input type="text" name="Parent_Name" value="{{ old('Parent_Name', $alumni['Parent_Name'] ?? '') }}" required class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Nomor Visa <span class="text-rose-600">*</span></span>
                    <input type="text" name="Visa_Number" value="{{ old('Visa_Number', $alumni['Visa_Number'] ?? '') }}" required class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Jenis Visa <span class="text-rose-600">*</span></span>
                    <input type="text" name="Visa_Type" value="{{ old('Visa_Type', $alumni['Visa_Type'] ?? '') }}" required class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Jalur <span class="text-rose-600">*</span></span>
                    <select name="Pathway" required class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Pilih jalur</option>
                        <option value="TG" @selected(old('Pathway', $alumni['Pathway'] ?? '') === 'TG')>Tokutei Ginou (TG)</option>
                        <option value="MAGANG" @selected(old('Pathway', $alumni['Pathway'] ?? '') === 'MAGANG')>Magang</option>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Bidang Pekerjaan</span>
                    <input type="text" name="Work_Field" value="{{ old('Work_Field', $alumni['Work_Field'] ?? '') }}" maxlength="150" placeholder="Masukkan bidang pekerjaan" class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Kota di Jepang <span class="text-rose-600">*</span></span>
                    <input type="text" name="Japan_City" value="{{ old('Japan_City', $alumni['Japan_City'] ?? '') }}" required class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Tanggal keberangkatan <span class="text-rose-600">*</span></span>
                    <input type="date" name="Departure_Date" value="{{ old('Departure_Date', $alumni['Departure_Date'] ?? '') }}" max="{{ now()->format('Y-m-d') }}" required class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </label>
            </div>
            <label class="block">
                <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Alamat lengkap di Indonesia <span class="text-rose-600">*</span></span>
                <textarea name="Indonesia_Address" rows="4" required class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('Indonesia_Address', $alumni['Indonesia_Address'] ?? '') }}</textarea>
            </label>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Ganti foto</span>
                    <input type="file" name="Photo" accept="image/jpeg,image/png,image/webp" class="block w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-indigo-700">
                    <p class="mt-1 text-xs text-slate-500">Opsional, maksimal 5MB.</p>
                </label>
                <div>
                    <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Foto saat ini</span>
                    <div class="flex items-center gap-3">
                        @if(!empty($alumni['Photo_URL']))
                            <img src="{{ $alumni['Photo_URL'] }}" alt="Foto {{ $alumni['Full_Name'] }}" class="h-16 w-16 rounded-lg border border-slate-200 object-cover">
                        @else
                            <span class="text-sm text-slate-500">Belum ada foto</span>
                        @endif
                        @if(!empty($alumni['Photo']))
                            <label class="inline-flex items-center gap-2 text-xs font-bold text-slate-600">
                                <input type="checkbox" name="remove_photo" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                Hapus foto
                            </label>
                        @endif
                    </div>
                </div>
            </div>
        </fieldset>

        <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">
            <a href="{{ route('alumni.show', $alumni['Alumni_ID']) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-50">Batal</a>
            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-indigo-700">Simpan perubahan</button>
        </div>
    </form>
</div>
@endsection
