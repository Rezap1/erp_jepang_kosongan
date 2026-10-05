<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportSelectedAlumniPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'alumni_ids' => ['required', 'array', 'min:1', 'max:100'],
            'alumni_ids.*' => ['required', 'string', 'max:50', 'regex:/\AALM\d{6,}\z/i'],
        ];
    }

    public function messages(): array
    {
        return [
            'alumni_ids.required' => 'Pilih minimal satu Alumni untuk diunduh.',
            'alumni_ids.array' => 'Pilihan Alumni tidak valid.',
            'alumni_ids.min' => 'Pilih minimal satu Alumni untuk diunduh.',
            'alumni_ids.max' => 'Maksimal 100 Alumni dapat diunduh dalam satu PDF.',
            'alumni_ids.*.regex' => 'Format Alumni ID tidak valid.',
        ];
    }
}
