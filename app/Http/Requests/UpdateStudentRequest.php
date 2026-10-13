<?php

namespace App\Http\Requests;

use App\Services\Core\StudentService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Student_Number' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    $studentService = app(StudentService::class);
                    $student = $studentService->getAllStudents()->firstWhere('Student_Number', $value);
                    if ($student && $student['Student_ID'] !== $this->route('id')) {
                        $fail('Nomor Induk Siswa sudah digunakan oleh siswa lain.');
                    }
                },
            ],
            'Registration_Date' => 'required|date',
            'User_ID' => 'required|string',
            'Birth_Place' => 'nullable|string|max:100',
            'Birth_Date' => 'nullable|date',
            'Gender' => 'required|in:Laki-laki,Perempuan',
            'National_ID' => [
                'nullable',
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    if (! empty($value)) {
                        $studentService = app(StudentService::class);
                        $student = $studentService->getAllStudents()->firstWhere('National_ID', $value);
                        if ($student && $student['Student_ID'] !== $this->route('id')) {
                            $fail('NIK (KTP) sudah terdaftar atas nama siswa lain.');
                        }
                    }
                },
            ],

            'Address' => 'nullable|string',
            'Education' => 'required|string|max:100',
            'Program_ID' => 'required|string',
            'Batch_ID' => 'required|string',
            'Class_ID' => 'required|string',
            'Enrollment_Status' => 'required|string|max:50',
            'Graduation_Status' => 'nullable|string|max:50',
            'Is_Active' => 'nullable|in:TRUE,FALSE',
            'Notes' => 'nullable|string',
            'Photo' => 'nullable|image|mimes:jpeg,png,jpg|max:'.config('upload.max_kb', 5120),
            'Phone_Number' => 'nullable|string|max:20',
            'Email' => 'nullable|email|max:100',
        ];
    }

    public function messages()
    {
        return [
            'Program_ID.required' => 'Program wajib dipilih.',
            'Batch_ID.required' => 'Angkatan (Batch) wajib dipilih.',
            'Class_ID.required' => 'Kelas wajib dipilih.',
            'Photo.image' => 'File harus berupa gambar.',
            'Photo.max' => 'Ukuran gambar maksimal 5MB.',
            'Registration_Date.required' => 'Tanggal registrasi wajib diisi.',
            'User_ID.required' => 'Akun Pengguna (User) wajib dipilih.', 'Gender.required' => 'Jenis kelamin wajib dipilih.', 'Gender.in' => 'Jenis kelamin yang dipilih tidak valid.',
        ];
    }
}
