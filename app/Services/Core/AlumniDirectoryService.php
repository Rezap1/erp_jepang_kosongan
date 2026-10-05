<?php

namespace App\Services\Core;

use App\Helpers\CollectionHelper;
use App\Helpers\SheetValue;
use App\Support\AlumniPathway;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class AlumniDirectoryService
{
    public const PATHWAY_TG = AlumniPathway::TG;

    public const PATHWAY_MAGANG = AlumniPathway::MAGANG;

    public const PATHWAY_ENGINEER = AlumniPathway::ENGINEER;

    public function __construct(
        private readonly AlumniService $alumniService,
        private readonly StudentService $studentService,
        private readonly ProgramService $programService,
        private readonly BatchService $batchService,
        private readonly ClassService $classService
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{alumni: LengthAwarePaginator, analytics: array<string, mixed>, programs: Collection, batches: Collection, classes: Collection, visaTypes: Collection, workFields: Collection, regions: Collection}
     */
    public function directory(array $filters): array
    {
        $baseRows = $this->rowsForStatus($filters['status'] ?? 'active');
        $rows = $this->applyFilters($baseRows, $filters);

        return [
            'alumni' => CollectionHelper::paginate($rows, 15)->withQueryString(),
            'analytics' => $this->analytics($rows),
            'programs' => $this->relationOptions($baseRows, 'Program_ID', 'Program_Name'),
            'batches' => $this->relationOptions($baseRows, 'Batch_ID', 'Batch_Name'),
            'classes' => $this->relationOptions($baseRows, 'Class_ID', 'Class_Name'),
            'visaTypes' => $this->filterOptions($baseRows, 'Visa_Type'),
            'workFields' => $this->filterOptions($baseRows, 'Work_Field'),
            'regions' => $this->filterOptions($baseRows, 'Work_Region'),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function filteredRows(array $filters, string $defaultStatus = 'active'): Collection
    {
        $rows = $this->rowsForStatus($filters['status'] ?? $defaultStatus);

        return $this->applyFilters($rows, $filters);
    }

    /** @param list<string> $ids */
    public function selectedProfiles(array $ids): Collection
    {
        return $this->enrich($this->alumniService->selectedByIds($ids))
            ->map(function (array $row): array {
                $row['Photo_Data_URI'] = $this->alumniService->photoDataUri($row);

                return $row;
            });
    }

    /** @param array<string, mixed> $filters */
    protected function applyFilters(Collection $rows, array $filters): Collection
    {

        if ($this->filled($filters['search'] ?? null)) {
            $rows = CollectionHelper::search($rows, $filters['search'], [
                'Alumni_ID',
                'Student_ID',
                'Full_Name',
                'NIK',
                'Parent_Name',
                'Indonesia_Address',
                'Visa_Number',
                'Visa_Type',
                'Work_Field',
                'Japan_City',
                'Pathway_Label',
                'Program_Name',
                'Batch_Name',
                'Class_Name',
            ]);
        }

        $exactFilters = [
            'program' => 'Program_ID',
            'batch' => 'Batch_ID',
            'class' => 'Class_ID',
            'source' => 'Source_Type',
            'pathway' => 'Pathway',
            'visa_type' => 'Visa_Type',
            'work_field' => 'Work_Field',
            'region' => 'Work_Region',
            'departure_year' => 'Departure_Year',
        ];
        foreach ($exactFilters as $filter => $field) {
            if (! $this->filled($filters[$filter] ?? null)) {
                continue;
            }

            $needle = $this->normalized($filters[$filter]);
            $rows = $rows->filter(fn (array $row): bool => $this->normalized($row[$field] ?? null) === $needle);
        }

        return $rows->values();
    }

    /** @return array{total: int, tg: int, magang: int, engineer: int, total_regions: int, unknown_pathway: int, unknown_region: int, pathway_chart: array{labels: list<string>, values: list<int>}, region_chart: array{labels: list<string>, values: list<int>}} */
    public function analytics(Collection $rows): array
    {
        $tg = $rows->where('Pathway', self::PATHWAY_TG)->count();
        $magang = $rows->where('Pathway', self::PATHWAY_MAGANG)->count();
        $engineer = $rows->where('Pathway', self::PATHWAY_ENGINEER)->count();
        $regions = $rows
            ->filter(fn (array $row): bool => $this->filled($row['Work_Region'] ?? null))
            ->groupBy(fn (array $row): string => $this->normalized($row['Work_Region']))
            ->map(fn (Collection $group): array => [
                'label' => trim((string) ($group->first()['Work_Region'] ?? '')),
                'value' => $group->count(),
            ])
            ->sort(function (array $left, array $right): int {
                return $right['value'] <=> $left['value'] ?: strcasecmp($left['label'], $right['label']);
            })
            ->values();

        return [
            'total' => $rows->count(),
            'tg' => $tg,
            'magang' => $magang,
            'engineer' => $engineer,
            'total_regions' => $regions->count(),
            'unknown_pathway' => $rows->count() - $tg - $magang - $engineer,
            'unknown_region' => $rows->filter(fn (array $row): bool => ! $this->filled($row['Work_Region'] ?? null))->count(),
            'pathway_chart' => [
                'labels' => ['Tokutei Ginou (TG)', 'Magang', 'Engineer'],
                'values' => [$tg, $magang, $engineer],
            ],
            'region_chart' => [
                'labels' => $regions->pluck('label')->all(),
                'values' => $regions->pluck('value')->all(),
            ],
        ];
    }

    /** @param array<string, mixed> $row */
    public function decorateProfile(array $row): array
    {
        $row['Pathway'] = $this->canonicalPathway($row['Pathway'] ?? null);
        $row['Pathway_Label'] = $this->pathwayLabel($row['Pathway']);
        $row['Work_Region'] = trim((string) ($row['Japan_City'] ?? ''));

        return $row;
    }

    public function pathwayLabel(mixed $pathway): string
    {
        return AlumniPathway::label($pathway);
    }

    protected function rowsForStatus(mixed $status): Collection
    {
        $status = strtolower(trim((string) $status));
        if (! in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }

        $registry = $status === 'active'
            ? $this->alumniService->all()
            : $this->alumniService->allIncludingInactive()
                ->filter(fn (array $row): bool => $status === 'all' || ! SheetValue::isActive($row))
                ->values();

        if ($status !== 'inactive') {
            $registeredStudentIds = $registry->pluck('Student_ID')
                ->filter()
                ->map(fn ($id): string => $this->normalized($id))
                ->all();
            $legacy = $this->alumniService->legacyHistorical()->filter(function (array $row) use ($registeredStudentIds): bool {
                $studentId = $this->normalized($row['Student_ID'] ?? null);

                return $studentId === '' || ! in_array($studentId, $registeredStudentIds, true);
            });
            $registry = $registry->concat($legacy)->values();
        }

        return $this->enrich($registry);
    }

    protected function enrich(Collection $rows): Collection
    {
        $students = $this->safeCollection(fn () => $this->studentService->getAllStudents())
            ->keyBy(fn ($row): string => $this->normalized($row['Student_ID'] ?? null));
        $programs = $this->safeCollection(fn () => $this->programService->getAllPrograms())
            ->keyBy(fn ($row): string => $this->normalized($row['Program_ID'] ?? null));
        $batches = $this->safeCollection(fn () => $this->batchService->getAllBatches())
            ->keyBy(fn ($row): string => $this->normalized($row['Batch_ID'] ?? null));
        $classes = $this->safeCollection(fn () => $this->classService->getAllClasses())
            ->keyBy(fn ($row): string => $this->normalized($row['Class_ID'] ?? null));

        return $rows->map(function ($row) use ($students, $programs, $batches, $classes): array {
            $row = (array) $row;
            $student = $students->get($this->normalized($row['Student_ID'] ?? null), []);
            $programId = $row['Program_ID'] ?? ($student['Program_ID'] ?? '');
            $batchId = $row['Batch_ID'] ?? ($student['Batch_ID'] ?? '');
            $classId = $row['Class_ID'] ?? ($student['Class_ID'] ?? '');
            $program = $programs->get($this->normalized($programId), []);
            $batch = $batches->get($this->normalized($batchId), []);
            $class = $classes->get($this->normalized($classId), []);

            $row['Program_ID'] = $programId;
            $row['Batch_ID'] = $batchId;
            $row['Class_ID'] = $classId;
            $row['Program_Name'] = $program['Program_Name'] ?? '-';
            $row['Batch_Name'] = $batch['Batch_Name'] ?? '-';
            $row['Class_Name'] = $class['Class_Name'] ?? '-';
            $row['Source_Label'] = ! empty($row['Is_Legacy'])
                ? 'Legacy WMS'
                : (strtoupper((string) ($row['Source_Type'] ?? '')) === 'WMS' ? 'Dari Siswa WMS' : 'Input Manual');
            $row['Departure_Year'] = $this->filled($row['Departure_Date'] ?? null)
                ? substr((string) $row['Departure_Date'], 0, 4)
                : '';
            $row['Linked_Student'] = $student;

            return $this->decorateProfile($row);
        })->values();
    }

    protected function filterOptions(Collection $rows, string $field): Collection
    {
        return $rows->pluck($field)
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->unique(fn (string $value): string => $this->normalized($value))
            ->sort(fn (string $left, string $right): int => strcasecmp($left, $right))
            ->values();
    }

    protected function relationOptions(Collection $rows, string $idField, string $labelField): Collection
    {
        return $rows
            ->filter(fn (array $row): bool => $this->filled($row[$idField] ?? null))
            ->unique(fn (array $row): string => $this->normalized($row[$idField]))
            ->map(fn (array $row): array => [
                $idField => trim((string) $row[$idField]),
                $labelField => trim((string) ($row[$labelField] ?? '')) ?: trim((string) $row[$idField]),
            ])
            ->sort(fn (array $left, array $right): int => strcasecmp($left[$labelField], $right[$labelField]))
            ->values();
    }

    protected function canonicalPathway(mixed $value): ?string
    {
        return AlumniPathway::normalize($value);
    }

    protected function normalized(mixed $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    protected function filled(mixed $value): bool
    {
        return trim((string) $value) !== '';
    }

    protected function safeCollection(callable $resolver): Collection
    {
        try {
            return collect($resolver());
        } catch (Throwable $e) {
            Log::notice('Alumni directory reference data unavailable', ['exception' => get_class($e)]);

            return collect();
        }
    }
}
