<?php

namespace App\Http\Requests\Concerns;

use App\Exceptions\AnnouncementAudienceValidationException;
use App\Services\Academic\AnnouncementVisibilityService;
use Illuminate\Validation\Rule;

trait HandlesAnnouncementAudienceValidation
{
    protected function prepareForValidation(): void
    {
        $type = strtoupper(trim((string) $this->input('Audience_Type', '')));
        $legacySingleRole = $type === 'ROLE';
        if ($type === 'ROLE') {
            $type = AnnouncementVisibilityService::SELECTED_ROLES;
        }

        $merge = ['Audience_Type' => $type];
        if ($type === AnnouncementVisibilityService::ALL_ROLES) {
            $merge += [
                'Target_Roles' => [], 'Student_Scope' => null,
                'Target_Batch_ID' => null, 'Target_Class_IDs' => [],
            ];
        } elseif ($type === AnnouncementVisibilityService::SELECTED_ROLES) {
            $visibility = app(AnnouncementVisibilityService::class);
            $roles = $this->input('Target_Roles');
            if ($roles === null) {
                $legacyRole = trim((string) $this->input('Target_Role', ''));
                $match = collect($visibility->availableRoles())->first(
                    fn (array $role): bool => strcasecmp($role['name'], $legacyRole) === 0
                        || strcasecmp($role['id'], $legacyRole) === 0
                );
                $roles = $match ? [$match['id']] : [];
            }
            if (is_array($roles)) {
                $roles = array_values(array_unique(array_filter(array_map(
                    fn ($value): string => is_scalar($value) ? trim((string) $value) : '',
                    $roles
                ))));
            }
            $merge['Target_Roles'] = $roles;

            $studentRoleId = collect($visibility->availableRoles())->firstWhere('name', 'STUDENT')['id'] ?? null;
            $studentSelected = is_array($roles) && $studentRoleId && in_array($studentRoleId, $roles, true);
            if (! $studentSelected) {
                $merge += ['Student_Scope' => null, 'Target_Batch_ID' => null, 'Target_Class_IDs' => []];
            } else {
                $classIds = $this->input('Target_Class_IDs');
                if ($classIds === null) {
                    $legacyClass = trim((string) $this->input('Target_Class_ID', ''));
                    $classIds = $legacyClass === '' ? [] : [$legacyClass];
                }
                if (is_array($classIds)) {
                    $classIds = array_values(array_unique(array_filter(array_map(
                        fn ($value): string => is_scalar($value) ? trim((string) $value) : '',
                        $classIds
                    ))));
                }
                $merge['Target_Class_IDs'] = $classIds;
                if (! $this->filled('Student_Scope') && $legacySingleRole) {
                    $merge['Student_Scope'] = is_array($classIds) && $classIds !== []
                        ? AnnouncementVisibilityService::BATCH_SELECTED_CLASSES
                        : ($this->filled('Target_Batch_ID')
                            ? AnnouncementVisibilityService::BATCH_ALL_CLASSES
                            : AnnouncementVisibilityService::ALL_STUDENTS);
                }
            }
        }

        $this->merge($merge);
    }

    /** @return array<string, array<mixed>|string> */
    protected function audienceRules(): array
    {
        $roleIds = array_column(app(AnnouncementVisibilityService::class)->availableRoles(), 'id');

        return [
            'Target_Role' => ['nullable', 'string', 'max:100'],
            'Target_Audience' => ['nullable', 'string'],
            'Audience_Type' => ['required_without_all:Target_Audience,Target_Role', 'nullable', 'string', Rule::in([
                AnnouncementVisibilityService::ALL_ROLES,
                AnnouncementVisibilityService::SELECTED_ROLES,
                'ALL_STUDENTS',
                'CLASS',
            ])],
            'Target_Roles' => ['exclude_unless:Audience_Type,'.AnnouncementVisibilityService::SELECTED_ROLES, 'required_if:Audience_Type,'.AnnouncementVisibilityService::SELECTED_ROLES, 'array', 'min:1'],
            'Target_Roles.*' => ['string', 'max:191', 'distinct', Rule::in($roleIds)],
            'Student_Scope' => ['nullable', 'string', Rule::in([
                AnnouncementVisibilityService::ALL_STUDENTS,
                AnnouncementVisibilityService::BATCH_ALL_CLASSES,
                AnnouncementVisibilityService::BATCH_SELECTED_CLASSES,
            ])],
            'Target_ID' => 'nullable|string|max:191',
            'Audience_ID' => 'nullable|string|max:191',
            'Class_ID' => 'nullable|string|max:191',
            'Target_Batch_ID' => 'nullable|string|max:191',
            'Target_Class_ID' => 'nullable|string|max:191',
            'Target_Class_IDs' => ['nullable', 'array'],
            'Target_Class_IDs.*' => ['string', 'max:191', 'distinct'],
        ];
    }

    protected function validateCanonicalAudience($validator): void
    {
        $type = strtoupper((string) $this->input('Audience_Type'));
        if (! in_array($type, [AnnouncementVisibilityService::ALL_ROLES, AnnouncementVisibilityService::SELECTED_ROLES], true)) {
            return;
        }
        $studentRoleId = collect(app(AnnouncementVisibilityService::class)->availableRoles())->firstWhere('name', 'STUDENT')['id'] ?? null;
        $selectedRoles = $this->input('Target_Roles', []);
        if (is_array($selectedRoles) && $studentRoleId && in_array($studentRoleId, $selectedRoles, true) && ! $this->filled('Student_Scope')) {
            $validator->errors()->add('Student_Scope', 'Pilih target siswa.');

            return;
        }
        try {
            app(AnnouncementVisibilityService::class)->canonicalize($this->all());
        } catch (AnnouncementAudienceValidationException $e) {
            $validator->errors()->add($e->field, $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            $validator->errors()->add('Audience_Type', $e->getMessage());
        }
    }

    protected function audienceMessages(): array
    {
        return [
            'Audience_Type.required_without_all' => 'Pilih tujuan pengumuman.',
            'Target_Roles.required_if' => 'Pilih minimal satu role penerima.',
            'Target_Roles.array' => 'Pilihan role penerima tidak valid.',
            'Target_Roles.*.in' => 'Role penerima tidak valid atau tidak aktif.',
            'Target_Class_IDs.array' => 'Pilihan kelas tidak valid.',
            'Target_Class_IDs.*.distinct' => 'Kelas yang sama tidak perlu dipilih dua kali.',
        ];
    }
}
