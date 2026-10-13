<?php

namespace App\Http\Requests;

use App\Helpers\SheetValue;
use App\Interfaces\GoogleSheets\AcademicYearRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\SubjectRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScheduleRequest extends FormRequest
{
    private const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'Class_ID' => ['required', 'string', 'max:50', $this->existingActiveRecord(ClassRepositoryInterface::class, 'Kelas')],
            'Subject_ID' => ['required', 'string', 'max:50', $this->existingActiveRecord(SubjectRepositoryInterface::class, 'Materi')],
            'Teacher_ID' => ['required', 'string', 'max:50', $this->existingActiveRecord(TeacherRepositoryInterface::class, 'Pengajar')],
            'Academic_Year_ID' => ['required', 'string', 'max:50', $this->existingAcademicYearRecord()],
            'Day_Of_Week' => ['required', 'array', 'min:1'],
            'Day_Of_Week.*' => ['required', 'string', Rule::in(self::DAYS)],
            'Start_Time' => ['required', 'date_format:H:i'],
            'End_Time' => ['required', 'date_format:H:i', 'after:Start_Time'],
            'Room' => ['nullable', 'string', 'max:100'],
            'Is_Active' => ['nullable', 'in:TRUE,FALSE'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'Academic_Year_ID' => trim((string) $this->input('Academic_Year_ID')),
            'Start_Time' => $this->timeForValidation($this->input('Start_Time')),
            'End_Time' => $this->timeForValidation($this->input('End_Time')),
        ]);
    }

    public function messages(): array
    {
        return [
            'Class_ID.required' => 'Kelas wajib dipilih.',
            'Subject_ID.required' => 'Materi wajib dipilih.',
            'Teacher_ID.required' => 'Pengajar wajib dipilih.',
            'Academic_Year_ID.required' => 'Tahun ajaran wajib dipilih.',
            'Day_Of_Week.required' => 'Hari wajib dipilih.',
            'Day_Of_Week.array' => 'Hari harus dipilih dari daftar.',
            'Day_Of_Week.min' => 'Pilih setidaknya satu hari.',
            'Day_Of_Week.*.required' => 'Hari wajib dipilih.',
            'Day_Of_Week.*.in' => 'Hari yang dipilih tidak valid.',
            'Start_Time.required' => 'Waktu mulai wajib diisi.',
            'Start_Time.date_format' => 'Waktu mulai harus dalam format jam yang valid.',
            'End_Time.required' => 'Waktu selesai wajib diisi.',
            'End_Time.date_format' => 'Waktu selesai harus dalam format jam yang valid.',
            'End_Time.after' => 'Waktu selesai harus setelah waktu mulai.',
            'Room.max' => 'Ruangan maksimal 100 karakter.',
            'Is_Active.in' => 'Status jadwal tidak valid.',
        ];
    }

    private function existingActiveRecord(string $repositoryClass, string $label): \Closure
    {
        return function ($attribute, $value, $fail) use ($repositoryClass, $label) {
            $record = app($repositoryClass)->findById(trim((string) $value));
            if (! $record) {
                $fail("{$label} tidak ditemukan.");

                return;
            }

            if (SheetValue::isInactive((array) $record)) {
                $fail("{$label} tidak aktif.");
            }
        };
    }

    private function existingAcademicYearRecord(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $value = trim((string) $value);
            $record = app(AcademicYearRepositoryInterface::class)->findById($value);
            if (! $record) {
                $fail('Tahun ajaran tidak ditemukan.');

                return;
            }

            if (SheetValue::isInactive((array) $record)) {
                $fail('Tahun ajaran tidak aktif.');
            }
        };
    }

    private function timeForValidation($value): mixed
    {
        $value = trim((string) $value);

        return $value === '' ? $value : substr($value, 0, 5);
    }
}
