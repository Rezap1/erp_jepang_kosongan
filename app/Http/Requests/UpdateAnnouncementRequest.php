<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HandlesAnnouncementAudienceValidation;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAnnouncementRequest extends FormRequest
{
    use HandlesAnnouncementAudienceValidation;

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
            ...$this->audienceRules(),
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

            $this->validateCanonicalAudience($validator);
        });
    }

    public function messages(): array
    {
        return array_merge([
            'Title.required' => 'Judul pengumuman wajib diisi.',
            'Content.required' => 'Isi pengumuman wajib diisi.',
            'Message.required' => 'Isi pengumuman wajib diisi.',
            'Expires_At.required_without' => 'Tanggal dan waktu berakhir wajib diisi.',
            'Expired_Date.required_without' => 'Tanggal dan waktu berakhir wajib diisi.',
            '*.date' => 'Format tanggal dan waktu tidak valid.',
        ], $this->audienceMessages());
    }
}
