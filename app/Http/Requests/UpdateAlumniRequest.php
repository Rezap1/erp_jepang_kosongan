<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAlumniRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Full_Name' => 'nullable|string|max:150',
            'NIK' => 'nullable|string|max:50',
            'Parent_Name' => 'required|string|max:150',
            'Indonesia_Address' => 'required|string|max:1000',
            'Visa_Number' => 'required|string|max:100',
            'Visa_Type' => 'required|string|max:100',
            'Pathway' => 'required|in:TG,MAGANG',
            'Work_Field' => 'nullable|string|max:150',
            'Japan_City' => 'required|string|max:150',
            'Departure_Date' => 'required|date|before_or_equal:today',
            'Photo' => 'nullable|file|image|mimes:jpeg,png,jpg,webp|max:'.config('upload.max_kb', 5120),
            'remove_photo' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'Parent_Name.required' => 'Nama orang tua wajib diisi.',
            'Indonesia_Address.required' => 'Alamat lengkap di Indonesia wajib diisi.',
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
