<?php

namespace App\Services\Core;

use App\Helpers\SheetValue;
use App\Interfaces\GoogleSheets\AlumniRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Support\ActorIdentity;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Alumni is an explicit registry. A row is created here only after the
 * operator submits the required departure data; graduation status alone never
 * creates an Alumni record.
 */
class AlumniService
{
    protected $alumniRepository;

    protected $studentRepository;

    public function __construct(
        AlumniRepositoryInterface $alumniRepository,
        StudentRepositoryInterface $studentRepository
    ) {
        $this->alumniRepository = $alumniRepository;
        $this->studentRepository = $studentRepository;
    }

    public function all(): Collection
    {
        return collect($this->alumniRepository->fetchAll())
            ->filter(fn ($row) => $this->isActive($row))
            ->sortByDesc(fn ($row) => (string) ($row['Departure_Date'] ?? $row['Created_At'] ?? ''))
            ->values()
            ->map(fn ($row) => $this->withPhoto($this->asArray($row)));
    }

    public function getAllAlumni(): Collection
    {
        return $this->all();
    }

    public function allIncludingInactive(): Collection
    {
        return collect($this->alumniRepository->fetchAll())
            ->sortByDesc(fn ($row) => (string) ($row['Departure_Date'] ?? $row['Created_At'] ?? ''))
            ->values()
            ->map(fn ($row) => $this->withPhoto($this->asArray($row)));
    }

    public function getById(string $id, bool $activeOnly = true): ?array
    {
        $row = $this->alumniRepository->findById($id);
        if (! $row) {
            return null;
        }

        $row = $this->withPhoto($this->asArray($row));
        if ($activeOnly && ! $this->isActive($row)) {
            return null;
        }

        return $row;
    }

    public function getAlumniById(string $id, bool $activeOnly = true): ?array
    {
        return $this->getById($id, $activeOnly);
    }

    public function getByStudentId(string $studentId, bool $activeOnly = true): ?array
    {
        $row = $this->alumniRepository->findByStudentId($studentId);
        if (! $row) {
            return null;
        }

        $row = $this->withPhoto($this->asArray($row));
        if ($activeOnly && ! $this->isActive($row)) {
            return null;
        }

        return $row;
    }

    /**
     * Preserve historical Alumni projections that predate MASTER_ALUMNI.
     * These rows are read-only compatibility data; this method never writes or
     * mass-migrates them into the new registry.
     */
    public function legacyHistorical(): Collection
    {
        $registeredStudentIds = [];
        try {
            $registeredStudentIds = collect($this->alumniRepository->fetchAll())
                ->pluck('Student_ID')
                ->filter()
                ->map(fn ($id) => strtolower(trim((string) $id)))
                ->all();
        } catch (Throwable $e) {
            // MASTER_ALUMNI may not exist yet; legacy read-only data can still
            // be shown from MASTER_STUDENT.
        }

        return collect($this->studentRepository->fetchAll())
            ->filter(fn ($student) => $this->isLegacyStudent($student))
            ->filter(function ($student) use ($registeredStudentIds) {
                $studentId = strtolower(trim((string) ($student['Student_ID'] ?? '')));

                return $studentId !== '' && ! in_array($studentId, $registeredStudentIds, true);
            })
            ->map(fn ($student) => $this->makeLegacyRow($this->asArray($student) ?: []))
            ->values();
    }

    public function legacyById(string $id): ?array
    {
        if (! str_starts_with(strtoupper(trim($id)), 'LEGACY-')) {
            return null;
        }

        $studentId = substr(trim($id), strlen('LEGACY-'));
        if ($studentId === '') {
            return null;
        }

        foreach ($this->legacyHistorical() as $row) {
            if (strcasecmp((string) ($row['Alumni_ID'] ?? ''), (string) $id) === 0) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Return active students which have not already been registered as Alumni.
     * The read is intentionally allowed to fail when MASTER_ALUMNI is absent,
     * so the controller can show the real schema blocker instead of guessing.
     */
    public function eligibleStudents(): Collection
    {
        $students = collect($this->studentRepository->fetchAll())
            ->filter(fn ($student) => SheetValue::isOperationalStudent($this->asArray($student) ?: []))
            ->values();
        $existingStudentIds = collect($this->alumniRepository->fetchAll())
            ->filter(fn ($row) => $this->isActive($row))
            ->pluck('Student_ID')
            ->filter()
            ->map(fn ($id) => strtolower(trim((string) $id)))
            ->all();

        return $students
            ->filter(function ($student) use ($existingStudentIds) {
                $id = strtolower(trim((string) ($student['Student_ID'] ?? '')));

                return $id !== '' && ! in_array($id, $existingStudentIds, true);
            })
            ->sortBy('Full_Name')
            ->values();
    }

    public function createAlumni(array $data, ?string $idempotencyKey = null): array
    {
        return $this->create($data, $idempotencyKey);
    }

    public function create(array $data, ?string $idempotencyKey = null): array
    {
        $actor = ActorIdentity::required();
        $fingerprint = $this->fingerprint($data);
        $cacheKey = $this->idempotencyCacheKey($idempotencyKey, $actor);

        if ($cacheKey !== null) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                $this->assertSameIdempotentPayload($cached, $fingerprint);

                return $this->asArray($cached['record'] ?? []);
            }
        }

        $result = Cache::lock('alumni_registry_write_lock', 120)->block(15, function () use (
            $data,
            $actor,
            $fingerprint,
            $cacheKey
        ) {
            if ($cacheKey !== null) {
                $cached = Cache::get($cacheKey);
                if (is_array($cached)) {
                    $this->assertSameIdempotentPayload($cached, $fingerprint);

                    return $this->asArray($cached['record'] ?? []);
                }
            }

            $source = $this->normalizeSource($data['Source_Type'] ?? $data['source_type'] ?? $data['source'] ?? '');
            $student = null;
            if ($source === 'WMS') {
                $studentId = trim((string) ($data['Student_ID'] ?? ''));
                if ($studentId === '') {
                    throw new InvalidArgumentException('Siswa WMS wajib dipilih.');
                }

                $student = $this->asArray($this->studentRepository->findById($studentId));
                if (! $student) {
                    throw new InvalidArgumentException('Siswa WMS yang dipilih tidak ditemukan.');
                }

                if (! SheetValue::isOperationalStudent($student)) {
                    throw new InvalidArgumentException('Siswa WMS sudah nonaktif/alumni dan tidak tersedia untuk konversi baru.');
                }
            }

            $normalized = $this->normalizePayload($data, $source, $student);
            $this->assertRequiredPayload($normalized);
            $this->assertNoActiveDuplicate($normalized);

            $alumniId = $this->alumniRepository->generateNewId('ALM', 6);
            $photoPath = $this->resolvePhotoForCreate($data['Photo'] ?? null, $student, $alumniId);
            $now = now()->toDateTimeString();
            $row = [
                'Alumni_ID' => $alumniId,
                'Source_Type' => $source,
                'Student_ID' => $normalized['Student_ID'],
                'Full_Name' => $normalized['Full_Name'],
                'NIK' => $normalized['NIK'],
                'Parent_Name' => $normalized['Parent_Name'],
                'Indonesia_Address' => $normalized['Indonesia_Address'],
                'Visa_Number' => $normalized['Visa_Number'],
                'Visa_Type' => $normalized['Visa_Type'],
                'Japan_City' => $normalized['Japan_City'],
                'Departure_Date' => $normalized['Departure_Date'],
                'Photo' => $photoPath ?? '',
                'Program_ID' => $normalized['Program_ID'],
                'Pathway' => $normalized['Pathway'],
                'Batch_ID' => $normalized['Batch_ID'],
                'Class_ID' => $normalized['Class_ID'],
                'Status' => 'ALUMNI',
                'Is_Active' => 'TRUE',
                'Created_At' => $now,
                'Updated_At' => $now,
                'Created_By' => $actor,
                'Updated_By' => $actor,
            ];

            $studentBefore = $student;
            try {
                $this->alumniRepository->create($row);
                $persistedAlumni = $this->freshAlumni($alumniId);
                if (! $persistedAlumni || strcasecmp((string) ($persistedAlumni['Alumni_ID'] ?? ''), $alumniId) !== 0) {
                    throw new RuntimeException('Data Alumni berhasil ditulis tetapi belum dapat diverifikasi.');
                }

                if ($source === 'WMS') {
                    $studentUpdate = [
                        'Enrollment_Status' => 'Alumni',
                        'Is_Active' => 'FALSE',
                        'Updated_At' => $now,
                        'Updated_By' => $actor,
                    ];
                    $this->studentRepository->update($normalized['Student_ID'], $studentUpdate);
                    $studentAfter = $this->freshStudent($normalized['Student_ID']);
                    if (! $studentAfter
                        || strtoupper(trim((string) ($studentAfter['Is_Active'] ?? 'TRUE'))) !== 'FALSE'
                        || strtolower(trim((string) ($studentAfter['Enrollment_Status'] ?? ''))) !== 'alumni') {
                        throw new RuntimeException('Status Student berhasil ditulis tetapi belum dapat diverifikasi.');
                    }
                }
            } catch (Throwable $e) {
                try {
                    $this->alumniRepository->softDelete($alumniId);
                } catch (Throwable $rollbackError) {
                    Log::error('Alumni rollback failed after coordinated write error', [
                        'alumni_id' => $alumniId,
                        'exception' => get_class($rollbackError),
                    ]);
                }

                if ($source === 'WMS' && $studentBefore) {
                    try {
                        $this->studentRepository->update($normalized['Student_ID'], [
                            'Enrollment_Status' => $studentBefore['Enrollment_Status'] ?? '',
                            'Is_Active' => $studentBefore['Is_Active'] ?? 'TRUE',
                            'Updated_At' => now()->toDateTimeString(),
                            'Updated_By' => $actor,
                        ]);
                    } catch (Throwable $rollbackError) {
                        Log::error('Student rollback failed after Alumni conversion error', [
                            'student_id' => $normalized['Student_ID'],
                            'exception' => get_class($rollbackError),
                        ]);
                    }
                }

                $this->deleteOwnedPhoto($photoPath);
                throw $e;
            }

            $record = $this->withPhoto($this->freshAlumni($alumniId) ?? $row);
            $this->dispatchEvent('CREATE', $alumniId, $actor, $record);
            if ($source === 'WMS') {
                $this->dispatchEvent('UPDATE', $normalized['Student_ID'], $actor, [
                    'Student_ID' => $normalized['Student_ID'],
                    'Enrollment_Status' => 'Alumni',
                    'Is_Active' => 'FALSE',
                ], 'STUDENT');
            }

            if ($cacheKey !== null) {
                Cache::put($cacheKey, [
                    'fingerprint' => $fingerprint,
                    'record' => $record,
                ], now()->addDay());
            }

            return $record;
        });

        return $this->asArray($result);
    }

    public function updateAlumni(string $id, array $data): array
    {
        return $this->update($id, $data);
    }

    public function update(string $id, array $data): array
    {
        $actor = ActorIdentity::required();

        return Cache::lock('alumni_registry_write_lock', 120)->block(15, function () use ($id, $data, $actor) {
            $existing = $this->getById($id, false);
            if (! $existing) {
                throw new RuntimeException('Data Alumni tidak ditemukan.');
            }

            $source = $this->normalizeSource($existing['Source_Type'] ?? '');
            $linkedStudent = null;
            if ($source === 'WMS' && ! empty($existing['Student_ID'])) {
                $linkedStudent = $this->asArray($this->studentRepository->findById((string) $existing['Student_ID']));
            }
            $payload = $this->normalizePayload($data, $source, $linkedStudent, $existing);
            $this->assertRequiredPayload($payload);
            $this->assertNoActiveDuplicate($payload, $id);

            $newPhoto = null;
            if (($data['Photo'] ?? null) instanceof UploadedFile) {
                $newPhoto = $this->storePhoto($id, $data['Photo']);
            } elseif ($this->truthy($data['remove_photo'] ?? false)) {
                $newPhoto = '';
            }

            $update = [
                'Full_Name' => $payload['Full_Name'],
                'NIK' => $payload['NIK'],
                'Parent_Name' => $payload['Parent_Name'],
                'Indonesia_Address' => $payload['Indonesia_Address'],
                'Visa_Number' => $payload['Visa_Number'],
                'Visa_Type' => $payload['Visa_Type'],
                'Japan_City' => $payload['Japan_City'],
                'Departure_Date' => $payload['Departure_Date'],
                'Pathway' => $payload['Pathway'],
                'Status' => 'ALUMNI',
                'Updated_At' => now()->toDateTimeString(),
                'Updated_By' => $actor,
            ];

            if ($newPhoto !== null) {
                $update['Photo'] = $newPhoto;
            }

            try {
                $this->alumniRepository->update($id, $update);
                $persisted = $this->freshAlumni($id);
                if (! $persisted || strcasecmp((string) ($persisted['Alumni_ID'] ?? ''), (string) $id) !== 0) {
                    throw new RuntimeException('Perubahan Alumni belum dapat diverifikasi.');
                }
            } catch (Throwable $e) {
                if ($newPhoto !== null && $newPhoto !== ($existing['Photo'] ?? '')) {
                    $this->deleteOwnedPhoto($newPhoto);
                }
                throw $e;
            }

            $oldPhoto = (string) ($existing['Photo'] ?? '');
            if ($newPhoto !== null && $oldPhoto !== $newPhoto) {
                $this->deleteOwnedPhoto($oldPhoto);
            }

            $record = $this->withPhoto($persisted);
            $this->dispatchEvent('UPDATE', (string) $id, $actor, $record);

            return $record;
        });
    }

    public function deactivateAlumni(string $id): bool
    {
        return $this->deactivate($id);
    }

    public function deactivate(string $id): bool
    {
        $actor = ActorIdentity::required();

        return Cache::lock('alumni_registry_write_lock', 120)->block(15, function () use ($id, $actor) {
            $existing = $this->getById($id, false);
            if (! $existing) {
                throw new RuntimeException('Data Alumni tidak ditemukan.');
            }

            $this->alumniRepository->update($id, [
                'Is_Active' => 'FALSE',
                'Updated_At' => now()->toDateTimeString(),
                'Updated_By' => $actor,
            ]);
            $persisted = $this->freshAlumni($id);
            if (! $persisted || $this->isActive($persisted)) {
                throw new RuntimeException('Data Alumni belum dapat dinonaktifkan.');
            }

            $this->dispatchEvent('DEACTIVATE', $id, $actor, [
                'Alumni_ID' => $id,
                'Is_Active' => 'FALSE',
            ]);

            return true;
        });
    }

    public function photoUrl(array $row): ?string
    {
        return $this->withPhoto($row)['Photo_URL'] ?? null;
    }

    protected function normalizePayload(array $data, string $source, ?array $student, ?array $existing = null): array
    {
        $student = $student ?: [];
        $existing = $existing ?: [];
        $isWms = $source === 'WMS';

        $fullName = $isWms
            ? ($student['Full_Name'] ?? $existing['Full_Name'] ?? '')
            : ($data['Full_Name'] ?? $existing['Full_Name'] ?? '');
        $nik = $isWms
            ? $this->firstFilled($student['National_ID'] ?? null, $data['NIK'] ?? null, $existing['NIK'] ?? null)
            : ($data['NIK'] ?? $existing['NIK'] ?? '');
        $studentParent = $this->firstFilled(
            $student['Parent_Name'] ?? null,
            $student['Guardian_Name'] ?? null,
            $student['Father_Name'] ?? null,
            $student['Mother_Name'] ?? null
        );
        $studentAddress = $this->firstFilled(
            $student['Indonesia_Address'] ?? null,
            $student['Full_Address'] ?? null,
            $student['Domicile_Address'] ?? null,
            $student['Address'] ?? null
        );

        return [
            'Student_ID' => $isWms
                ? trim((string) ($student['Student_ID'] ?? $existing['Student_ID'] ?? $data['Student_ID'] ?? ''))
                : '',
            'Full_Name' => trim((string) $fullName),
            'NIK' => trim((string) $nik),
            'Parent_Name' => trim((string) ($data['Parent_Name'] ?? ($isWms ? $studentParent : null) ?? $existing['Parent_Name'] ?? '')),
            'Indonesia_Address' => trim((string) ($data['Indonesia_Address'] ?? ($isWms ? $studentAddress : null) ?? $existing['Indonesia_Address'] ?? '')),
            'Visa_Number' => trim((string) ($data['Visa_Number'] ?? $existing['Visa_Number'] ?? '')),
            'Visa_Type' => trim((string) ($data['Visa_Type'] ?? $existing['Visa_Type'] ?? '')),
            'Japan_City' => trim((string) ($data['Japan_City'] ?? $existing['Japan_City'] ?? '')),
            'Departure_Date' => $this->normalizeDepartureDate($data['Departure_Date'] ?? $existing['Departure_Date'] ?? ''),
            'Program_ID' => $isWms
                ? trim((string) ($student['Program_ID'] ?? $existing['Program_ID'] ?? ''))
                : trim((string) ($existing['Program_ID'] ?? '')),
            'Pathway' => $this->normalizePathway($data['Pathway'] ?? $existing['Pathway'] ?? ''),
            'Batch_ID' => $isWms
                ? trim((string) ($student['Batch_ID'] ?? $existing['Batch_ID'] ?? ''))
                : trim((string) ($existing['Batch_ID'] ?? '')),
            'Class_ID' => $isWms
                ? trim((string) ($student['Class_ID'] ?? $existing['Class_ID'] ?? ''))
                : trim((string) ($existing['Class_ID'] ?? '')),
        ];
    }

    protected function assertRequiredPayload(array $payload): void
    {
        $labels = [
            'Full_Name' => 'Nama lengkap',
            'NIK' => 'NIK',
            'Parent_Name' => 'Nama orang tua',
            'Indonesia_Address' => 'Alamat lengkap di Indonesia',
            'Visa_Number' => 'Nomor Visa',
            'Visa_Type' => 'Jenis Visa',
            'Pathway' => 'Jalur',
            'Japan_City' => 'Kota di Jepang',
            'Departure_Date' => 'Tanggal keberangkatan',
        ];

        foreach ($labels as $field => $label) {
            if (trim((string) ($payload[$field] ?? '')) === '') {
                throw new InvalidArgumentException("{$label} wajib diisi.");
            }
        }

        if (mb_strlen((string) $payload['Full_Name']) > 150) {
            throw new InvalidArgumentException('Nama lengkap maksimal 150 karakter.');
        }
        if (mb_strlen((string) $payload['NIK']) > 50) {
            throw new InvalidArgumentException('NIK maksimal 50 karakter.');
        }
        if (mb_strlen((string) $payload['Visa_Type']) > 100) {
            throw new InvalidArgumentException('Jenis Visa maksimal 100 karakter.');
        }
        if (mb_strlen((string) $payload['Parent_Name']) > 150) {
            throw new InvalidArgumentException('Nama orang tua maksimal 150 karakter.');
        }
    }

    protected function assertNoActiveDuplicate(array $payload, ?string $ignoreId = null): void
    {
        $rows = collect($this->alumniRepository->fetchAll())
            ->filter(fn ($row) => $this->isActive($row));
        $nik = strtolower(trim((string) ($payload['NIK'] ?? '')));
        $visa = strtolower(trim((string) ($payload['Visa_Number'] ?? '')));
        $studentId = strtolower(trim((string) ($payload['Student_ID'] ?? '')));

        foreach ($rows as $row) {
            $row = $this->asArray($row);
            if ($ignoreId !== null && strcasecmp((string) ($row['Alumni_ID'] ?? ''), (string) $ignoreId) === 0) {
                continue;
            }

            if ($studentId !== '' && $studentId === strtolower(trim((string) ($row['Student_ID'] ?? '')))) {
                throw new InvalidArgumentException('Student tersebut sudah memiliki Alumni aktif.');
            }
            if ($nik !== '' && $nik === strtolower(trim((string) ($row['NIK'] ?? '')))) {
                throw new InvalidArgumentException('NIK sudah terdaftar pada Alumni aktif.');
            }
            if ($visa !== '' && $visa === strtolower(trim((string) ($row['Visa_Number'] ?? '')))) {
                throw new InvalidArgumentException('Nomor Visa sudah terdaftar pada Alumni aktif.');
            }
        }
    }

    protected function findActiveByField(string $field, string $value): ?array
    {
        $needle = strtolower(trim($value));
        if ($needle === '') {
            return null;
        }

        foreach (collect($this->alumniRepository->fetchAll())->filter(fn ($row) => $this->isActive($row)) as $row) {
            if (strcasecmp(trim((string) ($row[$field] ?? '')), $needle) === 0) {
                return $this->withPhoto($this->asArray($row));
            }
        }

        return null;
    }

    protected function isLegacyStudent($student): bool
    {
        $student = $this->asArray($student) ?: [];
        $enrollment = strtolower(trim((string) ($student['Enrollment_Status'] ?? '')));
        $graduation = strtolower(trim((string) ($student['Graduation_Status'] ?? '')));
        $inactive = strtoupper(trim((string) ($student['Is_Active'] ?? 'TRUE'))) === 'FALSE';

        return $enrollment === 'alumni'
            || ($inactive
                && (in_array($enrollment, ['lulus', 'graduated', 'completed'], true)
                    || in_array($graduation, ['lulus', 'graduated', 'completed'], true)));
    }

    protected function makeLegacyRow(array $student): array
    {
        $studentId = trim((string) ($student['Student_ID'] ?? ''));
        $parent = $this->firstFilled(
            $student['Parent_Name'] ?? null,
            $student['Guardian_Name'] ?? null,
            $student['Father_Name'] ?? null,
            $student['Mother_Name'] ?? null
        );
        $address = $this->firstFilled(
            $student['Indonesia_Address'] ?? null,
            $student['Full_Address'] ?? null,
            $student['Domicile_Address'] ?? null,
            $student['Address'] ?? null
        );

        return $this->withPhoto([
            'Alumni_ID' => 'LEGACY-'.$studentId,
            'Source_Type' => 'LEGACY',
            'Source_Label' => 'Legacy WMS',
            'Is_Legacy' => true,
            'Student_ID' => $studentId,
            'Full_Name' => $student['Full_Name'] ?? '',
            'NIK' => $student['National_ID'] ?? '',
            'Parent_Name' => $parent,
            'Indonesia_Address' => $address,
            'Visa_Number' => '',
            'Visa_Type' => '',
            'Japan_City' => '',
            'Departure_Date' => '',
            'Photo' => '',
            'Program_ID' => $student['Program_ID'] ?? '',
            'Pathway' => '',
            'Batch_ID' => $student['Batch_ID'] ?? '',
            'Class_ID' => $student['Class_ID'] ?? '',
            'Status' => 'LEGACY',
            'Is_Active' => 'TRUE',
            'Created_At' => $student['Created_At'] ?? '',
            'Updated_At' => $student['Updated_At'] ?? '',
            'Created_By' => $student['Created_By'] ?? '',
            'Updated_By' => $student['Updated_By'] ?? '',
        ]);
    }

    protected function normalizeSource($source): string
    {
        $source = strtoupper(trim((string) $source));

        return match ($source) {
            'WMS', 'STUDENT', 'DARI SISWA WMS', 'DARI SISWA' => 'WMS',
            'MANUAL', 'INPUT MANUAL', 'DARI INPUT MANUAL' => 'MANUAL',
            default => throw new InvalidArgumentException('Sumber Alumni harus WMS atau Input Manual.'),
        };
    }

    protected function normalizePathway(mixed $pathway): string
    {
        $pathway = strtoupper(trim((string) $pathway));
        if (! in_array($pathway, ['TG', 'MAGANG'], true)) {
            throw new InvalidArgumentException('Jalur harus Tokutei Ginou (TG) atau Magang.');
        }

        return $pathway;
    }

    protected function firstFilled(...$values): string
    {
        foreach ($values as $value) {
            $value = trim((string) ($value ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    protected function normalizeDepartureDate($value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $value, config('app.timezone', 'Asia/Jakarta'));
            if (! $date || $date->format('Y-m-d') !== $value) {
                throw new RuntimeException;
            }
        } catch (Throwable $e) {
            throw new InvalidArgumentException('Tanggal keberangkatan harus menggunakan format YYYY-MM-DD.');
        }

        if ($date->startOfDay()->gt(Carbon::now(config('app.timezone', 'Asia/Jakarta'))->startOfDay())) {
            throw new InvalidArgumentException('Tanggal keberangkatan tidak boleh di masa depan.');
        }

        return $date->format('Y-m-d');
    }

    protected function resolvePhotoForCreate($photo, ?array $student, string $alumniId): ?string
    {
        if ($photo instanceof UploadedFile) {
            return $this->storePhoto($alumniId, $photo);
        }

        if ($student) {
            return $this->findStudentPhotoPath((string) ($student['Student_ID'] ?? ''));
        }

        return null;
    }

    protected function storePhoto(string $alumniId, UploadedFile $file): string
    {
        $mimeToExtension = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        $mime = strtolower((string) $file->getMimeType());
        $size = (int) ($file->getSize() ?? 0);
        $maxBytes = ((int) config('upload.max_kb', 5120)) * 1024;
        if (! $file->isValid() || ! isset($mimeToExtension[$mime]) || $size <= 0 || $size > $maxBytes) {
            throw new InvalidArgumentException('Foto harus JPG, PNG, atau WEBP dan berukuran maksimal 5MB.');
        }

        $filename = 'alumni_'.preg_replace('/[^A-Za-z0-9_-]/', '', $alumniId)
            .'_'.Str::lower(Str::random(16)).'.'.$mimeToExtension[$mime];
        $stored = $file->storeAs('profiles', $filename, 'public');
        if (! $stored || ! Storage::disk('public')->exists($stored)) {
            throw new RuntimeException('Foto Alumni gagal diunggah.');
        }

        return 'storage/'.ltrim(str_replace('\\', '/', $stored), '/');
    }

    protected function findStudentPhotoPath(string $studentId): ?string
    {
        $studentId = trim($studentId);
        if ($studentId === '') {
            return null;
        }

        foreach (Storage::disk('public')->files('profiles') as $path) {
            if (preg_match('/^student_'.preg_quote($studentId, '/').'\.(?:jpe?g|png|webp)$/i', basename($path))) {
                return 'storage/'.ltrim(str_replace('\\', '/', $path), '/');
            }
        }

        return null;
    }

    protected function freshAlumni(string $id): ?array
    {
        return $this->asArray($this->alumniRepository->findByIdFresh($id));
    }

    protected function freshStudent(string $id): ?array
    {
        $this->studentRepository->clearCache();

        return $this->asArray($this->studentRepository->findById($id));
    }

    protected function withPhoto(array $row): array
    {
        $photo = $this->normalizePhotoPath($row['Photo'] ?? '');
        if ($photo === null && ! empty($row['Student_ID'])) {
            $photo = $this->normalizePhotoPath($this->findStudentPhotoPath((string) $row['Student_ID']));
        }
        $row['Photo'] = $photo ? 'storage/'.$photo : (string) ($row['Photo'] ?? '');
        $row['Photo_URL'] = $photo ? Storage::disk('public')->url($photo) : null;

        return $row;
    }

    protected function normalizePhotoPath($path): ?string
    {
        $path = ltrim(str_replace('\\', '/', trim((string) $path)), '/');
        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
            return null;
        }
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }
        if (! str_starts_with($path, 'profiles/') || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return $path;
    }

    protected function deletePhoto(?string $path): void
    {
        $normalized = $this->normalizePhotoPath($path);
        if ($normalized !== null) {
            Storage::disk('public')->delete($normalized);
        }
    }

    protected function deleteOwnedPhoto(?string $path): void
    {
        $normalized = $this->normalizePhotoPath($path);
        if ($normalized !== null && str_starts_with(basename($normalized), 'alumni_')) {
            Storage::disk('public')->delete($normalized);
        }
    }

    protected function isActive($row): bool
    {
        $row = $this->asArray($row) ?: [];

        return strtoupper(trim((string) ($row['Is_Active'] ?? 'TRUE'))) !== 'FALSE';
    }

    protected function truthy($value): bool
    {
        return in_array(strtoupper(trim((string) $value)), ['1', 'TRUE', 'YES', 'ON'], true);
    }

    protected function asArray($row): ?array
    {
        if ($row === null) {
            return null;
        }
        if (is_array($row)) {
            return $row;
        }
        if (is_object($row) && method_exists($row, 'toArray')) {
            return $row->toArray();
        }

        return (array) $row;
    }

    protected function idempotencyCacheKey(?string $key, string $actor): ?string
    {
        $key = trim((string) $key);
        if ($key === '') {
            return null;
        }

        return 'alumni_idempotency_'.hash('sha256', $actor.'|'.$key);
    }

    protected function fingerprint(array $data): string
    {
        $normalized = [];
        foreach ($data as $key => $value) {
            if ($value instanceof UploadedFile) {
                $normalized[$key] = [
                    'mime' => $value->getMimeType(),
                    'size' => $value->getSize(),
                    'original' => $value->getClientOriginalName(),
                ];

                continue;
            }
            if (is_scalar($value) || $value === null) {
                $normalized[$key] = (string) $value;
            }
        }
        ksort($normalized);

        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    protected function assertSameIdempotentPayload(array $cached, string $fingerprint): void
    {
        if (($cached['fingerprint'] ?? null) !== $fingerprint) {
            throw new InvalidArgumentException('Kunci idempotensi sudah digunakan untuk payload yang berbeda.');
        }
    }

    protected function dispatchEvent(string $action, string $referenceId, string $actor, array $metadata, string $module = 'ALUMNI'): void
    {
        try {
            app(EnterpriseEventService::class)->dispatch(
                $module,
                $action,
                $module,
                $referenceId,
                $actor,
                ['ADMINISTRATOR', 'ACADEMIC'],
                [],
                $metadata
            );
        } catch (Throwable $e) {
            Log::warning('Alumni event dispatch failed', [
                'module' => $module,
                'action' => $action,
                'reference_id' => $referenceId,
                'exception' => get_class($e),
            ]);
        }
    }
}
