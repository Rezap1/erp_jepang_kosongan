@extends('layouts.app')

@section('header', 'Tambah Pengguna Baru')

@section('content')
<div class="max-w-4xl mx-auto">
    @php
        $roleOptions = [];
        foreach($roles as $role) {
            if(isset($role['Role_ID'])) {
                $roleOptions[$role['Role_ID']] = \App\Support\Presentation\IndonesianPresentation::role($role['Role_Name'] ?? $role['Role_ID']);
            }
        }
    @endphp

    <x-universal.form 
        action="{{ route('users.store') }}" 
        method="POST"
        title="Tambah Pengguna" 
        description="Informasi ini akan disimpan langsung ke dalam tabel MASTER_USER di Google Sheets."
        buttonText="Simpan Pengguna"
    >
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <x-universal.input 
                name="User_ID_Display" 
                label="ID Pengguna" 
                value="{{ $nextUserId ?? 'Dibuat Otomatis' }}" 
                readonly="true"
                helper="Dibuat otomatis berurutan."
            />
            
            <x-universal.input 
                name="Username" 
                label="Username" 
                :required="true"
                placeholder="Username unik"
            />
            
            <x-universal.input 
                name="Full_Name" 
                label="Nama Lengkap" 
                :required="true"
                placeholder="Nama Lengkap Pengguna"
            />
            
            <x-universal.input 
                name="Phone_Number" 
                label="Nomor HP" 
                placeholder="08123456789"
            />
            
            <x-universal.input 
                name="Email" 
                label="Alamat Email" 
                type="email"
                :required="true"
                placeholder="nama@domain-lpk.test"
            />
            
            <x-universal.input 
                name="Password" 
                label="Kata Sandi" 
                type="password"
                :required="true"
                placeholder="Minimal 8 karakter"
            />
            
            <x-universal.select 
                name="Role_ID" 
                label="Peran (Role)" 
                :required="true"
                :options="$roleOptions"
                value=""
            />
            
            <x-universal.input 
                name="Employee_ID" 
                label="ID Karyawan" 
                placeholder="Opsional (mis. EMP000001)"
                helper="Biarkan kosong jika pengguna bukan karyawan."
            />
            
            <x-universal.select 
                name="Is_Active" 
                label="Status Akun" 
                :required="true"
                :options="['TRUE' => 'Aktif', 'FALSE' => 'Nonaktif']"
                value="TRUE"
            />
            
            <div class="md:col-span-2">
                <x-universal.textarea 
                    name="Notes" 
                    label="Catatan Khusus" 
                    placeholder="Catatan tambahan opsional..."
                />
            </div>
        </div>
    </x-universal.form>
</div>
@endsection
