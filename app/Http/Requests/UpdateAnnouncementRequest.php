<?php

namespace App\Http\Requests;

use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Services\Academic\AnnouncementVisibilityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAnnouncementRequest extends FormRequest
{
    private const TARGET_ROLES = ['ALL', 'ALL_USERS', 'TEACHER', 'STUDENT', 'CLASS', 'ALL_STUDENTS'];

    private const AUDIENCES = ['ALL_ROLES', 'ROLE', 'ALL_STUDENTS', 'CLASS'];

    private const PRIORITIES = ['NORMAL', 'IMPORTANT', 'URGENT', 'Low', 'Normal', 'High', 'LOW', 'MEDIUM', 'HIGH'];

    private const STATUSES = ['DRAFT', 'PUBLISHED', 'ACTIVE', 'INACTIVE'];

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
        $afterStart = function (string $attribute, $value, \Closure $fail): void {
            if (blank($value)) {
                return;
            }
            try {
                $startRaw = $this->input('Start_At') ?: $this->input('Publish_Date');
                $start = blank($startRaw) ? now(config('app.timezone', 'Asia/Jakarta')) : CarbonImmutable::parse($startRaw, config('app.timezone', 'Asia/Jakarta'));
                $expires = CarbonImmutable::parse($value, config('app.timezone', 'Asia/Jakarta'));
                if ($expires->lessThanOrEqualTo($start)) {
                    $fail('Waktu berakhir harus setelah waktu mulai.');
                }
            } catch (\Throwable) {
            }
        };

        return [
            'Title' => 'required|string|max:255',
            'Content' => 'required_without:Message|string',
            'Message' => 'required_without:Content|string',
            'Target_Role' => ['nullable', 'string', 'max:100'],
            'Target_Audience' => ['nullable', 'string', Rule::in(array_merge(self::AUDIENCES, self::TARGET_ROLES))],
            'Audience_Type' => ['required_without_all:Target_Audience,Target_Role', 'nullable', 'string', Rule::in(self::AUDIENCES)],
            'Target_ID' => 'nullable|string|max:100',
            'Audience_ID' => 'nullable|string|max:100',
            'Class_ID' => 'nullable|string|max:100',
            'Target_Batch_ID' => 'nullable|string|max:191',
            'Target_Class_ID' => 'nullable|string|max:191',
            'Priority' => ['nullable', 'string', Rule::in(self::PRIORITIES)],
            'Status' => ['required', 'string', Rule::in(self::STATUSES)],
            'Start_At' => 'nullable|date',
            'Expires_At' => ['required_without:Expired_Date', 'nullable', 'date', $afterStart],
            'Publish_Date' => 'nullable|date',
            'Expired_Date' => ['required_without:Expires_At', 'nullable', 'date', $afterStart],
            'Is_Active' => 'nullable|in:TRUE,FALSE',
            'Notes' => 'nullable|string|max:1000',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $audience = strtoupper((string) ($this->input('Audience_Type') ?: $this->input('Target_Audience') ?: $this->input('Target_Role', 'ALL_STUDENTS')));
            if (in_array($audience, ['CLASS', 'KELAS'], true)) {
                $classId = trim((string) ($this->input('Audience_ID') ?: $this->input('Class_ID') ?: $this->input('Target_ID')));
                if ($classId === '') {
                    $validator->errors()->add('Audience_ID', 'Kelas wajib dipilih untuk target kelas tertentu.');
                } elseif (app()->bound(ClassRepositoryInterface::class)) {
                    try {
                        $class = app(ClassRepositoryInterface::class)->findById($classId);
                        if (! $class || strtoupper((string) ($class['Is_Active'] ?? 'TRUE')) === 'FALSE') {
                            $validator->errors()->add('Audience_ID', 'Kelas yang dipilih tidak tersedia.');
                        }
                    } catch (\Throwable) {
                        $validator->errors()->add('Audience_ID', 'Kelas yang dipilih tidak dapat diverifikasi.');
                    }
                }
            }

            if (in_array(strtoupper((string) $this->input('Audience_Type')), ['ALL_ROLES', 'ROLE'], true)) {
                try {
                    app(AnnouncementVisibilityService::class)->canonicalize($this->all());
                } catch (\InvalidArgumentException $e) {
                    $field = str_contains(strtolower($e->getMessage()), 'kelas') ? 'Target_Class_ID'
                        : (str_contains(strtolower($e->getMessage()), 'batch') ? 'Target_Batch_ID' : 'Target_Role');
                    $validator->errors()->add($field, $e->getMessage());
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'Title.required' => 'Judul pengumuman wajib diisi.',
            'Content.required' => 'Isi pengumuman wajib diisi.',
            'Message.required' => 'Isi pengumuman wajib diisi.',
            'Expires_At.required_without' => 'Tanggal dan waktu berakhir wajib diisi.',
            'Expired_Date.required_without' => 'Tanggal dan waktu berakhir wajib diisi.',
            '*.date' => 'Format tanggal dan waktu tidak valid.',
        ];
    }
}
