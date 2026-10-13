<?php

namespace App\Support\Academic;

use App\Helpers\SheetValue;

final class TeacherClassAssignmentResolver
{
    /**
     * Resolve classes a teacher manages. Explicit homeroom assignments are
     * authoritative; schedule ownership is used only for legacy classes that
     * do not have a homeroom teacher yet.
     *
     * @return list<string>
     */
    public static function managedClassIds(iterable $classes, iterable $teacherSchedules, string $teacherId): array
    {
        $teacherId = trim($teacherId);
        if ($teacherId === '') {
            return [];
        }

        $scheduledClassIds = collect($teacherSchedules)
            ->filter(fn ($schedule) => ! self::isInactive((array) $schedule))
            ->pluck('Class_ID')
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->unique()
            ->values();

        $activeClasses = collect($classes)
            ->filter(fn ($class) => ! self::isInactive((array) $class))
            ->values();
        if ($activeClasses->isEmpty()) {
            return $scheduledClassIds->all();
        }

        return $activeClasses
            ->filter(function ($class) use ($teacherId, $scheduledClassIds): bool {
                $class = (array) $class;
                $homeroomTeacherId = trim((string) ($class['Homeroom_Teacher_ID'] ?? ''));

                if ($homeroomTeacherId !== '') {
                    return $homeroomTeacherId === $teacherId;
                }

                return $scheduledClassIds->contains(trim((string) ($class['Class_ID'] ?? '')));
            })
            ->pluck('Class_ID')
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private static function isInactive(array $row): bool
    {
        return SheetValue::isInactive($row);
    }
}
