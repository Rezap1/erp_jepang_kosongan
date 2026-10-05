@extends('layouts.app')

@section('header', 'Tambah Alumni')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <div>
        <a href="{{ route('alumni.index') }}" class="text-xs font-black text-indigo-700 hover:text-indigo-900">← Kembali ke Direktori Alumni</a>
        <h1 class="mt-2 text-2xl font-black text-slate-900">Catat keberangkatan Alumni</h1>
        <p class="mt-1 text-sm text-slate-500">Pilih sumber data. Untuk WMS, identitas dan snapshot pendidikan diambil dari Student_ID di server.</p>
    </div>

    @if($schemaError)
        <div class="border-l-4 border-amber-500 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
            <p class="font-black">Form belum siap digunakan</p>
            <p class="mt-1">{{ $schemaError }}</p>
        </div>
    @endif

    @if($errors->any())
        <div class="border-l-4 border-rose-500 bg-rose-50 px-4 py-3 text-sm text-rose-900">
            <p class="font-black">Periksa kembali isian berikut:</p>
            <ul class="mt-1 list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('alumni.store') }}" enctype="multipart/form-data" class="space-y-6 border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @csrf

        <fieldset class="space-y-4">
            <legend class="text-sm font-black text-slate-900">Sumber & identitas</legend>
            <label class="block">
                <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Sumber Alumni <span class="text-rose-600">*</span></span>
                <select name="Source_Type" id="Source_Type" required class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="WMS" @selected(old('Source_Type', 'WMS') === 'WMS')>Dari Siswa WMS</option>
                    <option value="MANUAL" @selected(old('Source_Type') === 'MANUAL')>Input Manual</option>
                </select>
            </label>

            <div id="wms-fields">
                <label class="block">
                    <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Siswa WMS <span class="text-rose-600">*</span></span>
                    <select name="Student_ID" id="Student_ID" class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Pilih siswa aktif</option>
                        @foreach($students as $student)
                            <option
                                value="{{ $student['Student_ID'] }}"
                                data-name="{{ $student['Full_Name'] ?? '' }}"
                                data-nik="{{ $student['National_ID'] ?? '' }}"
                                data-parent="{{ $student['Parent_Name'] ?? $student['Guardian_Name'] ?? $student['Father_Name'] ?? $student['Mother_Name'] ?? '' }}"
                                data-address="{{ $student['Indonesia_Address'] ?? $student['Full_Address'] ?? $student['Domicile_Address'] ?? $student['Address'] ?? '' }}"
                                @selected(old('Student_ID', $selectedStudentId) == ($student['Student_ID'] ?? ''))>
                                {{ $student['Full_Name'] ?? '-' }} · {{ $student['Class_Name'] ?? '-' }} · {{ $student['Program_Name'] ?? '-' }} / {{ $student['Batch_Name'] ?? '-' }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Daftar hanya memuat Student aktif yang belum memiliki Alumni aktif.</p>
                </label>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Nama lengkap <span class="text-rose-600">*</span></span>
                    <input type="text" name="Full_Name" id="Full_Name" value="{{ old('Full_Name') }}" readonly required class="w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2.5 text-sm read-only:cursor-not-allowed read-only:text-slate-600 focus:border-indigo-500 focus:ring-indigo-500">
                    <p id="full-name-help" class="mt-1 text-xs text-slate-500">Terisi otomatis dari Student WMS.</p>
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">NIK <span class="text-rose-600">*</span></span>
                    <input type="text" name="NIK" id="NIK" value="{{ old('NIK') }}" readonly required class="w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2.5 text-sm read-only:cursor-not-allowed read-only:text-slate-600 focus:border-indigo-500 focus:ring-indigo-500">
                    <p id="nik-help" class="mt-1 text-xs text-slate-500">Untuk WMS, NIK terkunci bila sudah tersedia. Jika kosong, isi saat conversion.</p>
                </label>
            </div>
        </fieldset>

        <fieldset class="space-y-4 border-t border-slate-100 pt-5">
            <legend class="text-sm font-black text-slate-900">Data keberangkatan</legend>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Nama orang tua <span class="text-rose-600">*</span></span>
                    <input type="text" name="Parent_Name" id="Parent_Name" value="{{ old('Parent_Name') }}" required class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Nomor Visa <span class="text-rose-600">*</span></span>
                    <input type="text" name="Visa_Number" value="{{ old('Visa_Number') }}" required class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Jenis Visa <span class="text-rose-600">*</span></span>
                    <input type="text" name="Visa_Type" value="{{ old('Visa_Type') }}" placeholder="Contoh: Specified Skilled Worker" required class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Jalur <span class="text-rose-600">*</span></span>
                    <select name="Pathway" required class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Pilih jalur</option>
                        <option value="TG" @selected(old('Pathway') === 'TG')>Tokutei Ginou (TG)</option>
                        <option value="MAGANG" @selected(old('Pathway') === 'MAGANG')>Magang</option>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Bidang Pekerjaan</span>
                    <input type="text" name="Work_Field" value="{{ old('Work_Field') }}" maxlength="150" placeholder="Masukkan bidang pekerjaan" class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Kota di Jepang <span class="text-rose-600">*</span></span>
                    <input type="text" name="Japan_City" value="{{ old('Japan_City') }}" required class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Tanggal keberangkatan <span class="text-rose-600">*</span></span>
                    <input type="date" name="Departure_Date" value="{{ old('Departure_Date') }}" max="{{ now()->format('Y-m-d') }}" required class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </label>
            </div>
            <label class="block">
                <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Alamat lengkap di Indonesia <span class="text-rose-600">*</span></span>
                <textarea name="Indonesia_Address" id="Indonesia_Address" rows="4" required class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('Indonesia_Address') }}</textarea>
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Foto Alumni</span>
                <input type="file" name="Photo" accept="image/jpeg,image/png,image/webp" class="block w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-indigo-700">
                <p class="mt-1 text-xs text-slate-500">Opsional. Foto Student WMS digunakan otomatis bila tersedia. Maksimal 5MB.</p>
            </label>
        </fieldset>

        <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">
            <a href="{{ route('alumni.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-50">Batal</a>
            <button type="submit" @disabled($schemaError) class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-slate-300">Simpan Alumni</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const source = document.getElementById('Source_Type');
    const wmsFields = document.getElementById('wms-fields');
    const student = document.getElementById('Student_ID');
    const fullName = document.getElementById('Full_Name');
    const nik = document.getElementById('NIK');
    const parentName = document.getElementById('Parent_Name');
    const indonesiaAddress = document.getElementById('Indonesia_Address');
    const hasOldParent = parentName.value !== '';
    const hasOldAddress = indonesiaAddress.value !== '';
    const fullNameHelp = document.getElementById('full-name-help');
    const nikHelp = document.getElementById('nik-help');

    function syncSource(initial = false) {
        const isWms = source.value === 'WMS';
        wmsFields.classList.toggle('hidden', !isWms);
        student.required = isWms;
        fullName.readOnly = isWms;
        const selected = student.options[student.selectedIndex];
        const hasWmsNik = isWms && selected && selected.value && (selected.dataset.nik || '') !== '';
        nik.readOnly = hasWmsNik;
        fullName.classList.toggle('bg-slate-100', isWms);
        fullName.classList.toggle('bg-slate-50', !isWms);
        nik.classList.toggle('bg-slate-100', hasWmsNik);
        nik.classList.toggle('bg-slate-50', !hasWmsNik);
        fullNameHelp.textContent = isWms ? 'Terisi otomatis dari Student WMS.' : 'Isi nama sesuai dokumen keberangkatan.';
        nikHelp.textContent = hasWmsNik ? 'Terisi otomatis dari Student WMS.' : (isWms ? 'Isi NIK karena data Student WMS belum tersedia.' : 'Isi NIK dari dokumen identitas.');
        if (isWms) syncStudent(initial);
    }

    function syncStudent(initial = false) {
        const option = student.options[student.selectedIndex];
        if (!option || !option.value) {
            fullName.value = '';
            nik.value = '';
            return;
        }
        fullName.value = option.dataset.name || '';
        if (!initial || option.dataset.nik) nik.value = option.dataset.nik || '';
        if (!initial || !hasOldParent) parentName.value = option.dataset.parent || '';
        if (!initial || !hasOldAddress) indonesiaAddress.value = option.dataset.address || '';
    }

    source.addEventListener('change', () => syncSource(false));
    student.addEventListener('change', () => syncSource(false));
    syncSource(true);
});
</script>
@endsection
