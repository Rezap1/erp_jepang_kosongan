@extends('layouts.app')
@section('header', 'Tambah Perusahaan Baru')
@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <x-universal.form 
        action="{{ route('companies.store') }}" 
        method="POST"
        title="Formulir Pendaftaran Perusahaan" 
        description="Lengkapi data identitas, legalitas, kontak, dan aset visual perusahaan."
        buttonText="Simpan Perusahaan"
        enctype="multipart/form-data"
    >
        <div class="space-y-8">
            <!-- Section 1: Identitas & Legalitas -->
            <div>
                <h3 class="text-base font-bold text-slate-800 border-b border-slate-100 pb-2 mb-4">1. Identitas & Legalitas Perusahaan</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-universal.input name="Company_Code" label="Kode Perusahaan" placeholder="Contoh: CMP001" :required="true" />
                    <x-universal.input name="Legal_Name" label="Nama Legalitas (PT/CV)" placeholder="Contoh: PT. Mitra Sejahtera" :required="true" />
                    <x-universal.input name="Company_Name" label="Nama Populer / Merek" placeholder="Contoh: Mitra Jepang" :required="true" />
                    <x-universal.input name="NPWP" label="NPWP (Opsional)" placeholder="00.000.000.0-000.000" />
                    <x-universal.input name="Business_License_Number" label="NIB / Izin Usaha (Opsional)" placeholder="Nomor Induk Berusaha" />
                    <x-universal.input name="Director_Name" label="Nama Direktur / Pimpinan" placeholder="Nama lengkap pimpinan" />
                </div>
            </div>

            <!-- Section 2: Alamat & Kontak -->
            <div>
                <h3 class="text-base font-bold text-slate-800 border-b border-slate-100 pb-2 mb-4">2. Alamat & Kontak</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <x-universal.textarea name="Address" label="Alamat Lengkap" placeholder="Jalan, Gedung, Nomor" />
                    </div>
                    <x-universal.input name="City" label="Kota / Kabupaten" />
                    <x-universal.input name="Province" label="Provinsi" />
                    <x-universal.input name="Postal_Code" label="Kode Pos" />
                    <x-universal.input name="Country" label="Negara" value="Indonesia" :required="true" />
                    <x-universal.input name="Phone_Number" label="Nomor Telepon" placeholder="021-XXXXXXX" />
                    <x-universal.input type="email" name="Email" label="Alamat Email" placeholder="contact@company.com" />
                    <div class="md:col-span-2">
                        <x-universal.input type="url" name="Website" label="Situs Web" placeholder="https://www.company.com" />
                    </div>
                </div>
            </div>

            <!-- Section 3: Aset Visual -->
            <div>
                <h3 class="text-base font-bold text-slate-800 border-b border-slate-100 pb-2 mb-4">3. Aset Visual</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Logo Perusahaan</label>
                        <div class="flex items-center gap-4">
                            <div id="logo-preview-container" class="h-20 w-20 rounded-xl bg-slate-50 border-2 border-dashed border-slate-300 flex items-center justify-center overflow-hidden flex-shrink-0">
                                <svg id="logo-placeholder" class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                <img id="logo-preview" src="#" alt="Logo Preview" class="hidden h-full w-full object-cover">
                            </div>
                            <div class="flex-grow">
                                <input type="file" name="Company_Logo" id="Company_Logo" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-colors">
                                <p class="mt-1.5 text-xs text-slate-500">Maks. 5MB (JPG, PNG, WEBP)</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block mb-1.5 text-[13px] font-bold text-slate-700">Stempel / Cap</label>
                        <div class="flex items-center gap-4">
                            <div id="stamp-preview-container" class="h-20 w-20 rounded-xl bg-slate-50 border-2 border-dashed border-slate-300 flex items-center justify-center overflow-hidden flex-shrink-0">
                                <svg id="stamp-placeholder" class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                <img id="stamp-preview" src="#" alt="Stamp Preview" class="hidden h-full w-full object-cover">
                            </div>
                            <div class="flex-grow">
                                <input type="file" name="Company_Stamp" id="Company_Stamp" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-colors">
                                <p class="mt-1.5 text-xs text-slate-500">Maks. 5MB (JPG, PNG, WEBP)</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div>
                <x-universal.textarea name="Notes" label="Catatan Internal (Opsional)" />
            </div>
        </div>
    </x-universal.form>
</div>

<script>
    function setupImagePreview(inputId, previewId, placeholderId) {
        document.getElementById(inputId).addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById(previewId);
            const placeholder = document.getElementById(placeholderId);
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                    placeholder.classList.add('hidden');
                }
                reader.readAsDataURL(file);
            } else {
                preview.src = '#';
                preview.classList.add('hidden');
                placeholder.classList.remove('hidden');
            }
        });
    }

    setupImagePreview('Company_Logo', 'logo-preview', 'logo-placeholder');
    setupImagePreview('Company_Stamp', 'stamp-preview', 'stamp-placeholder');
</script>
@endsection
