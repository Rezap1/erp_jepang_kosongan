@extends('layouts.app')

@section('header', 'Edit Pengguna')

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
        action="{{ route('users.update', $user['User_ID']) }}" 
        method="PUT"
        title="Ubah Detail Pengguna" 
        description="Mengubah data pengguna ID: {{ $user['User_ID'] }}"
        buttonText="Perbarui Pengguna"
    >
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <x-universal.input 
                name="User_ID_Display" 
                label="ID Pengguna" 
                value="{{ $user['User_ID'] }}" 
                readonly="true"
            />
            
            <x-universal.input 
                name="Username" 
                label="Username" 
                :required="true"
                value="{{ $user['Username'] ?? '' }}"
                placeholder="Contoh: budi.santoso"
            />
            
            <x-universal.input 
                name="Full_Name" 
                label="Nama Lengkap" 
                :required="true"
                value="{{ $user['Full_Name'] ?? $user['Name'] ?? '' }}"
                placeholder="Contoh: Budi Santoso"
            />
            
            <x-universal.input 
                name="Phone_Number" 
                label="Nomor HP" 
                value="{{ $user['Phone_Number'] ?? '' }}"
                placeholder="Contoh: 081234567890"
            />
            
            <x-universal.input 
                name="Email" 
                label="Alamat Email" 
                type="email"
                :required="true"
                value="{{ $user['Email'] ?? '' }}"
                placeholder="Contoh: budi@domain-lpk.test"
            />
            
            <x-universal.input 
                name="Password" 
                label="Kata Sandi Baru" 
                type="password"
                placeholder="Kosongkan jika tidak ingin mengubah"
                helper="Hanya isi jika ingin mereset kata sandi (min 8 karakter)."
            />
            
            <x-universal.select 
                name="Role_ID" 
                label="Peran (Role)" 
                :required="true"
                :options="$roleOptions"
                value="{{ $user['Role_ID'] ?? '' }}"
            />
            
            <x-universal.input 
                name="Employee_ID" 
                label="ID Karyawan" 
                value="{{ $user['Employee_ID'] ?? '' }}"
                placeholder="Contoh: EMP000001"
                helper="Biarkan kosong jika pengguna bukan karyawan."
            />
            
            <x-universal.select 
                name="Is_Active" 
                label="Status Akun" 
                :required="true"
                :options="['TRUE' => 'Aktif', 'FALSE' => 'Nonaktif']"
                value="{{ $user['Is_Active'] ?? 'TRUE' }}"
            />
            
            <div class="md:col-span-2">
                <x-universal.textarea 
                    name="Notes" 
                    label="Catatan Khusus" 
                    value="{{ $user['Notes'] ?? '' }}"
                />
            </div>
        </div>
    </x-universal.form>
</div>
@endsection
