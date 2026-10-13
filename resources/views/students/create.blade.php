@extends('layouts.app')
@section('header', 'Pendaftaran Siswa Baru')
@section('content')
<div class="max-w-4xl mx-auto">
    @php
        $userOptions = [];
        $userContactData = [];
        foreach($users as $user) {
            $roleId = $user['Role_ID'] ?? '';
            $roleName = \App\Helpers\UserResolverHelper::getRoleName($roleId);
            $userOptions[$user['User_ID']] = $user['Full_Name'] . ' (' . $user['Email'] . ') - Peran: ' . $roleName;
            $userContactData[$user['User_ID']] = [
                'name' => $user['Full_Name'] ?? $user['Username'] ?? '',
                'email' => $user['Email'] ?? '',
                'phone' => $user['Phone_Number'] ?? '',
                'role' => $roleName,
            ];
        }

        $programOptions = [];
        foreach($programs as $program) {
            $programOptions[$program['Program_ID']] = $program['Program_Name'];
        }
    @endphp

    <x-universal.form 
        action="{{ route('students.store') }}" 
        method="POST"
        title="Formulir Pendaftaran Siswa" 
        description="Lengkapi data pribadi, kontak, dan penempatan akademik siswa."
        buttonText="Daftarkan Siswa"
        :has-files="true"
    >
        <div class="space-y-8">
            <!-- Section 1: Akademik & Penempatan -->
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Penempatan Akademik</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <x-universal.input 
                            name="Registration_Date" 
                            label="Tanggal Pendaftaran" 
                            type="date"
                            :required="true"
                            value="{{ date('Y-m-d') }}"
                        />
                    </div>

                    <x-universal.searchable-select 
                        name="Program_ID" 
                        label="Program" 
                        :required="true"
                        :options="$programOptions"
                        value=""
                    />

                    <div>
                        <label for="Batch_ID" class="block text-[13px] font-bold text-slate-700 mb-1.5">Angkatan <span class="text-rose-500 font-black ml-0.5">*</span></label>
                        <div class="relative">
                            <select name="Batch_ID" id="Batch_ID" required disabled class="w-full rounded-xl border-slate-200 text-sm shadow-sm transition-all focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 @error('Batch_ID') border-rose-500 focus:ring-rose-500/20 focus:border-rose-500 @enderror">
                                <option value="" disabled selected>Pilih Angkatan</option>
                                @foreach($batches as $batch)
                                    <option value="{{ $batch['Batch_ID'] }}" data-program="{{ $batch['Program_ID'] }}" class="hidden" {{ old('Batch_ID') == $batch['Batch_ID'] ? 'selected' : '' }}>
                                        {{ $batch['Batch_Name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @error('Batch_ID') <p class="mt-1.5 text-xs font-bold text-rose-500 flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> {{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="Class_ID" class="block text-[13px] font-bold text-slate-700 mb-1.5">Kelas Dasar <span class="text-rose-500 font-black ml-0.5">*</span></label>
                        <div class="relative">
                            <select name="Class_ID" id="Class_ID" required disabled class="w-full rounded-xl border-slate-200 text-sm shadow-sm transition-all focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 @error('Class_ID') border-rose-500 focus:ring-rose-500/20 focus:border-rose-500 @enderror">
                                <option value="" disabled selected>Pilih Kelas</option>
                                @foreach($classes as $cls)
                                    <option value="{{ $cls['Class_ID'] }}" data-batch="{{ $cls['Batch_ID'] }}" class="hidden" {{ old('Class_ID') == $cls['Class_ID'] ? 'selected' : '' }}>
                                        {{ $cls['Class_Name'] }} (Sisa: {{ max(0, $cls['Capacity'] - $cls['Current_Student']) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @error('Class_ID') <p class="mt-1.5 text-xs font-bold text-rose-500 flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> {{ $message }}</p> @enderror
                    </div>

                    <x-universal.select 
                        name="Enrollment_Status" 
                        label="Status Pendidikan" 
                        :required="true"
                        :options="['Aktif Belajar' => 'Aktif Belajar', 'Menunggu Kelas' => 'Menunggu Kelas', 'Cuti' => 'Cuti / Istirahat', 'Drop Out' => 'Drop Out (Berhenti)']"
                        value=""
                    />
                </div>
            </div>

            <!-- Section 2: Data Pribadi -->
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2 mt-8">Data Pribadi Siswa</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-universal.input 
                        name="Student_Number" 
                        label="Nomor Induk Siswa (NIS)" 
                        :required="true"
                        placeholder="Contoh: NIS2023001"
                    />

                    <x-universal.input 
                        name="National_ID" 
                        label="NIK KTP" 
                        placeholder="16 Digit NIK KTP"
                    />

                    <div class="md:col-span-2">
                        <x-universal.searchable-select 
                            name="User_ID" 
                            label="Pilih Akun Pengguna" 
                            :required="true"
                            :options="$userOptions"
                            value=""
                        />
                        <div id="selectedUserContact" class="hidden rounded-2xl border border-sky-100 bg-sky-50/70 p-4 text-sm">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div>
                                    <p class="text-[10px] font-black uppercase text-slate-400">Nama Akun</p>
                                    <p id="selectedUserName" class="mt-1 font-bold text-slate-800">-</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black uppercase text-slate-400">Email</p>
                                    <p id="selectedUserEmail" class="mt-1 font-bold text-slate-800 break-all">-</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black uppercase text-slate-400">Nomor HP</p>
                                    <p id="selectedUserPhone" class="mt-1 font-bold text-slate-800">-</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <x-universal.select
                        name="Gender"
                        label="Jenis Kelamin"
                        :required="true"
                        :options="['Laki-laki' => 'Laki-laki', 'Perempuan' => 'Perempuan']"
                        value="{{ old('Gender') }}"
                    />

                    <x-universal.select 
                        name="Education" 
                        label="Pendidikan Terakhir" 
                        :required="true"
                        :options="['SMP Sederajat' => 'SMP Sederajat', 'SMA / SMK Sederajat' => 'SMA / SMK Sederajat', 'D3 / Diploma' => 'D3 / Diploma', 'S1 / Sarjana' => 'S1 / Sarjana']"
                        value=""
                    />

                    <x-universal.input 
                        name="Birth_Place" 
                        label="Tempat Lahir" 
                        placeholder="Kota Lahir"
                    />

                    <x-universal.input 
                        name="Birth_Date" 
                        label="Tanggal Lahir" 
                        type="date"
                    />
                </div>
            </div>

            <!-- Section 3: Kontak & Alamat -->
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2 mt-8">Kontak & Alamat</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <x-universal.input 
                            name="Address" 
                            label="Alamat Lengkap" 
                            placeholder="Alamat domisili"
                        />
                    </div>
                </div>
            </div>

            <!-- Section 3: Data Tambahan -->
            <div>
                <h3 class="text-sm font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Data Tambahan</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <x-universal.input 
                            name="Photo" 
                            label="Pas Foto Siswa (Opsional)" 
                            type="file"
                            helper="Foto ini akan otomatis digunakan sebagai foto profil akun Siswa. Format: JPG, PNG. Maks: 5MB."
                        />
                    </div>
                    
                    <div class="md:col-span-2">
                        <x-universal.textarea 
                            name="Notes" 
                            label="Catatan Internal / Riwayat Penyakit" 
                        />
                    </div>
                </div>
            </div>
        </div>
    </x-universal.form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Triple Chained Dropdowns Logic
        const programSelect = document.getElementById('Program_ID');
        const batchSelect = document.getElementById('Batch_ID');
        const classSelect = document.getElementById('Class_ID');
        const userSelect = document.getElementById('User_ID');
        const userContactData = @json($userContactData);
        const contactCard = document.getElementById('selectedUserContact');
        const contactName = document.getElementById('selectedUserName');
        const contactEmail = document.getElementById('selectedUserEmail');
        const contactPhone = document.getElementById('selectedUserPhone');
        
        const batchOptions = batchSelect.querySelectorAll('option[data-program]');
        const classOptions = classSelect.querySelectorAll('option[data-batch]');
        
        const oldBatchId = "{{ old('Batch_ID') }}";
        const oldClassId = "{{ old('Class_ID') }}";

        function filterBatches() {
            const selectedProgramId = programSelect.value;
            
            batchOptions.forEach(option => {
                if (option.getAttribute('data-program') === selectedProgramId) {
                    option.classList.remove('hidden');
                    option.disabled = false;
                } else {
                    option.classList.add('hidden');
                    option.disabled = true;
                }
            });

            if (selectedProgramId) {
                batchSelect.disabled = false;
                const selectedOption = batchSelect.options[batchSelect.selectedIndex];
                if (selectedOption && selectedOption.disabled && selectedOption.value !== "") {
                    batchSelect.value = "";
                }
            } else {
                batchSelect.disabled = true;
                batchSelect.value = "";
            }
            
            // Trigger class filter automatically
            filterClasses();
        }

        function filterClasses() {
            const selectedBatchId = batchSelect.value;
            
            classOptions.forEach(option => {
                if (option.getAttribute('data-batch') === selectedBatchId) {
                    option.classList.remove('hidden');
                    option.disabled = false;
                } else {
                    option.classList.add('hidden');
                    option.disabled = true;
                }
            });

            if (selectedBatchId) {
                classSelect.disabled = false;
                const selectedOption = classSelect.options[classSelect.selectedIndex];
                if (selectedOption && selectedOption.disabled && selectedOption.value !== "") {
                    classSelect.value = "";
                }
            } else {
                classSelect.disabled = true;
                classSelect.value = "";
            }
        }

        programSelect.addEventListener('change', filterBatches);
        batchSelect.addEventListener('change', filterClasses);
        userSelect.addEventListener('change', updateUserContactPreview);
        
        // Initial setup on load (handling old() form data)
        if (programSelect.value) {
            filterBatches();
            if(oldBatchId) {
                batchSelect.value = oldBatchId;
                filterClasses();
                if (oldClassId) {
                    classSelect.value = oldClassId;
                }
            }
        }

        updateUserContactPreview();

        function updateUserContactPreview() {
            const user = userContactData[userSelect.value];
            if (!user) {
                contactCard.classList.add('hidden');
                return;
            }

            contactName.textContent = user.name || '-';
            contactEmail.textContent = user.email || '-';
            contactPhone.textContent = user.phone || '-';
            contactCard.classList.remove('hidden');
        }
    });
</script>
@endsection

