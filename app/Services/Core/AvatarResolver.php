<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Resolve an avatar from the authenticated user's canonical User_ID.
 *
 * Profile photos are currently stored on the public Laravel disk using the
 * employee_{Employee_ID} / student_{Student_ID} filename convention.  The
 * spreadsheet rows provide the identity mapping; names are deliberately not
 * used because they are not unique identifiers.
 */
final class AvatarResolver
{
    public function __construct(
        private EmployeeRepositoryInterface $employeeRepository,
        private StudentRepositoryInterface $studentRepository,
        private EmployeeService $employeeService,
    ) {}

    public function resolve(mixed $user = null): ?string
    {
        $user = $user ?? auth()->user();
        $userData = $this->toArray($user);
        $userId = trim((string) ($userData['User_ID'] ?? ''));

        // No canonical identity means no photo lookup.  In particular, do not
        // use id/name as a substitute that could point to another account.
        if ($userId === '') {
            return null;
        }

        try {
            // A photo explicitly attached to the canonical user row is valid
            // only after URL/path validation below.
            $direct = $this->normalizePhoto($userData['Profile_Photo'] ?? null);
            if ($direct !== null) {
                return $direct;
            }

            $employee = $this->findByUserId($this->employeeRepository->fetchAll(), $userId);
            if ($employee !== null) {
                $photo = $this->normalizePhoto($employee['Profile_Photo'] ?? null);
                if ($photo !== null) {
                    return $photo;
                }

                $employeeId = trim((string) ($employee['Employee_ID'] ?? ''));
                $photoPath = $employeeId === '' ? null : $this->employeeService->getProfilePhotoPath($employeeId);
                $photo = $this->normalizePhoto($photoPath);
                if ($photo !== null) {
                    return $photo;
                }
            }

            $student = $this->findByUserId($this->studentRepository->fetchAll(), $userId);
            if ($student !== null) {
                $photo = $this->normalizePhoto($student['Profile_Photo'] ?? null);
                if ($photo !== null) {
                    return $photo;
                }

                $studentId = trim((string) ($student['Student_ID'] ?? ''));
                if ($studentId !== '') {
                    $photo = $this->findProfileFile('student', $studentId);
                    if ($photo !== null) {
                        return $photo;
                    }
                }
            }

            // Preserve the existing user-file convention, but still scope it
            // to the exact canonical User_ID.
            return $this->findProfileFile('user', $userId);
        } catch (\Throwable $e) {
            // Avatar failure must never break a page or disclose repository
            // details.  The user-specific id is safe diagnostic context.
            Log::warning('Avatar resolution failed', [
                'user_id' => $userId,
                'exception' => get_class($e),
            ]);

            return null;
        }
    }

    /**
     * Resolve a bounded user collection with one employee read, one student
     * read, and one profile-directory scan. Intended for comment streams.
     *
     * @return array<string, string|null>
     */
    public function resolveMany(iterable $users): array
    {
        $items = collect($users)->map(fn ($user): array => $this->toArray($user))->values();
        $ids = $items->pluck('User_ID')->map(fn ($id): string => trim((string) $id))->filter()->unique();
        if ($ids->isEmpty()) {
            return [];
        }

        try {
            $employees = collect($this->employeeRepository->fetchAll())
                ->filter(fn ($row): bool => $ids->contains(trim((string) ($row['User_ID'] ?? ''))))
                ->keyBy(fn ($row): string => strtolower(trim((string) ($row['User_ID'] ?? ''))));
            $students = collect($this->studentRepository->fetchAll())
                ->filter(fn ($row): bool => $ids->contains(trim((string) ($row['User_ID'] ?? ''))))
                ->keyBy(fn ($row): string => strtolower(trim((string) ($row['User_ID'] ?? ''))));
            $files = collect(Storage::disk('public')->files('profiles'));

            return $items->mapWithKeys(function (array $user) use ($employees, $students, $files): array {
                $userId = trim((string) ($user['User_ID'] ?? ''));
                $photo = $this->normalizePhoto($user['Profile_Photo'] ?? null);
                $employee = (array) ($employees->get(strtolower($userId)) ?? []);
                $student = (array) ($students->get(strtolower($userId)) ?? []);
                $photo ??= $this->normalizePhoto($employee['Profile_Photo'] ?? null);
                $photo ??= $this->normalizePhoto($student['Profile_Photo'] ?? null);

                if (! $photo) {
                    $identities = array_filter([
                        ['employee', trim((string) ($employee['Employee_ID'] ?? ''))],
                        ['student', trim((string) ($student['Student_ID'] ?? ''))],
                        ['user', $userId],
                    ], fn (array $identity): bool => $identity[1] !== '');
                    foreach ($identities as [$prefix, $id]) {
                        $path = $files->first(fn (string $candidate): bool => (bool) preg_match('/^'.preg_quote($prefix.'_'.$id, '/').'\.[A-Za-z0-9]+$/', basename($candidate)));
                        if ($path) {
                            $photo = Storage::disk('public')->url($path);
                            break;
                        }
                    }
                }

                return [$userId => $photo];
            })->all();
        } catch (\Throwable $e) {
            Log::warning('Bulk avatar resolution failed', ['exception' => get_class($e)]);

            return $ids->mapWithKeys(fn (string $id): array => [$id => null])->all();
        }
    }

    private function findByUserId(iterable $rows, string $userId): ?array
    {
        foreach ($rows as $row) {
            $candidate = is_object($row) && method_exists($row, 'toArray')
                ? $row->toArray()
                : (array) $row;
            if (strcasecmp(trim((string) ($candidate['User_ID'] ?? '')), $userId) === 0) {
                return $candidate;
            }
        }

        return null;
    }

    private function findProfileFile(string $prefix, string $identifier): ?string
    {
        foreach (Storage::disk('public')->files('profiles') as $path) {
            if (preg_match('/^'.preg_quote($prefix.'_'.$identifier, '/').'\\.[A-Za-z0-9]+$/', basename($path))) {
                return Storage::disk('public')->url($path);
            }
        }

        return null;
    }

    private function normalizePhoto(mixed $value): ?string
    {
        $photo = trim((string) $value);
        if ($photo === '') {
            return null;
        }

        if (preg_match('#^https://#i', $photo)) {
            return $photo;
        }

        // Only public-disk relative paths are accepted.  Reject traversal,
        // data/javascript URLs, and arbitrary filesystem paths.
        $photo = ltrim($photo, '/');
        if (str_contains($photo, '..') || preg_match('#^[A-Za-z][A-Za-z0-9+.-]*:#', $photo)) {
            return null;
        }

        if (str_starts_with($photo, 'storage/')) {
            $photo = substr($photo, strlen('storage/'));
        }
        if (! str_starts_with($photo, 'profiles/')) {
            return null;
        }

        $disk = Storage::disk('public');

        return $disk->exists($photo) ? $disk->url($photo) : null;
    }

    private function toArray(mixed $user): array
    {
        if (is_object($user) && method_exists($user, 'toArray')) {
            return $user->toArray();
        }

        if (is_array($user)) {
            return $user;
        }

        if (! is_object($user)) {
            return [];
        }

        // Illuminate\Auth\GenericUser keeps its attributes protected and
        // exposes them through __get/__isset rather than toArray().
        $data = [];
        foreach (['User_ID', 'id', 'Profile_Photo', 'Username', 'Name', 'Full_Name', 'Role', 'Role_ID'] as $key) {
            if (isset($user->{$key})) {
                $data[$key] = $user->{$key};
            }
        }

        return $data;
    }
}
