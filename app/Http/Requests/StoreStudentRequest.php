<?php

namespace App\Http\Requests;

use App\Services\Core\BatchService;
use App\Services\Core\ClassService;
use App\Services\Core\ProgramService;
use App\Services\Core\StudentService;
use App\Services\Core\UserService;
use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
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
                    if ($student) {
                        $fail('Nomor Induk Siswa sudah digunakan oleh siswa lain.');
                    }
                },
            ],
            'Registration_Date' => 'required|date',
            'User_ID' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $userService = app(UserService::class);
                    if (! $userService->getUserById($value)) {
                        $fail('User tidak ditemukan.');
                    }
                },
            ],
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
                        if ($student) {
                            $fail('NIK (KTP) sudah terdaftar atas nama siswa lain.');
                        }
                    }
                },
            ],

            'Address' => 'nullable|string',
            'Education' => 'required|string|max:100',
            'Program_ID' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $programService = app(ProgramService::class);
                    if (! $programService->getProgramById($value)) {
                        $fail('Program tidak ditemukan.');
                    }
                },
            ],
            'Batch_ID' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $batchService = app(BatchService::class);
                    if (! $batchService->getBatchById($value)) {
                        $fail('Batch tidak ditemukan.');
                    }
                },
            ],
            'Class_ID' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $classService = app(ClassService::class);
                    if (! $classService->getClassById($value)) {
                        $fail('Class tidak ditemukan.');
                    }
                },
            ],
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
            'Class_ID.required' => 'Class wajib dipilih.',
            'Photo.image' => 'File harus berupa gambar.',
            'Photo.max' => 'Ukuran gambar maksimal 5MB.',
            'Registration_Date.required' => 'Tanggal registrasi wajib diisi.',
            'User_ID.required' => 'Akun Pengguna (User) wajib dipilih.', 'Gender.required' => 'Jenis kelamin wajib dipilih.', 'Gender.in' => 'Jenis kelamin yang dipilih tidak valid.',
        ];
    }
}
