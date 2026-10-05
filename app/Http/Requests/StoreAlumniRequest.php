<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAlumniRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Source_Type' => 'required|in:WMS,MANUAL',
            'Student_ID' => 'nullable|required_if:Source_Type,WMS|string|max:50',
            'Full_Name' => 'nullable|required_if:Source_Type,MANUAL|string|max:150',
            'NIK' => 'nullable|required_if:Source_Type,MANUAL|string|max:50',
            'Parent_Name' => 'nullable|required_if:Source_Type,MANUAL|string|max:150',
            'Indonesia_Address' => 'nullable|required_if:Source_Type,MANUAL|string|max:1000',
            'Visa_Number' => 'required|string|max:100',
            'Visa_Type' => 'required|string|max:100',
            'Pathway' => 'required|in:TG,MAGANG',
            'Japan_City' => 'required|string|max:150',
            'Departure_Date' => 'required|date|before_or_equal:today',
            'Photo' => 'nullable|file|image|mimes:jpeg,png,jpg,webp|max:'.config('upload.max_kb', 5120),
        ];
    }

    public function messages(): array
    {
        return [
            'Source_Type.required' => 'Sumber Alumni wajib dipilih.',
            'Source_Type.in' => 'Sumber Alumni harus WMS atau Input Manual.',
            'Student_ID.required_if' => 'Siswa WMS wajib dipilih.',
            'Full_Name.required_if' => 'Nama lengkap wajib diisi untuk Input Manual.',
            'NIK.required_if' => 'NIK wajib diisi untuk Input Manual.',
            'Parent_Name.required_if' => 'Nama orang tua wajib diisi.',
            'Indonesia_Address.required_if' => 'Alamat lengkap di Indonesia wajib diisi.',
            'Visa_Number.required' => 'Nomor Visa wajib diisi.',
            'Visa_Type.required' => 'Jenis Visa wajib diisi.',
            'Pathway.required' => 'Jalur wajib dipilih.',
            'Pathway.in' => 'Jalur harus Tokutei Ginou (TG) atau Magang.',
            'Japan_City.required' => 'Kota di Jepang wajib diisi.',
            'Departure_Date.required' => 'Tanggal keberangkatan wajib diisi.',
            'Departure_Date.before_or_equal' => 'Tanggal keberangkatan tidak boleh di masa depan.',
            'Photo.image' => 'Foto Alumni harus berupa gambar.',
            'Photo.mimes' => 'Foto Alumni hanya boleh JPG, PNG, atau WEBP.',
            'Photo.max' => 'Ukuran foto Alumni maksimal 5MB.',
        ];
    }
}
