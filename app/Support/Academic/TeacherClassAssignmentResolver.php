<?php

namespace App\Support\Academic;

use App\Helpers\SheetValue;

final class TeacherClassAssignmentResolver
{
    /**
     * Resolve active homeroom classes from MASTER_CLASS only.
     *
     * @return list<string>
     */
    public static function managedClassIds(iterable $classes, string $teacherId): array
    {
        $teacherId = trim($teacherId);
        if ($teacherId === '') {
            return [];
        }

        $activeClasses = collect($classes)
            ->filter(fn ($class) => ! self::isInactive((array) $class))
            ->values();

        return $activeClasses
            ->filter(function ($class) use ($teacherId): bool {
                $class = (array) $class;
                $homeroomTeacherId = trim((string) ($class['Homeroom_Teacher_ID'] ?? ''));

                return $homeroomTeacherId !== ''
                    && SheetValue::id($homeroomTeacherId) === SheetValue::id($teacherId);
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
