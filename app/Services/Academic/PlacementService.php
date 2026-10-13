<?php

namespace App\Services\Academic;

use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Core\ActivityLogService;
use Exception;
use Illuminate\Support\Facades\Log;

class PlacementService
{
    protected $studentRepo;

    protected $classRepo;

    protected $batchRepo;

    protected $activityLog;

    public function __construct(
        StudentRepositoryInterface $studentRepo,
        ClassRepositoryInterface $classRepo,
        BatchRepositoryInterface $batchRepo,
        ActivityLogService $activityLog
    ) {
        $this->studentRepo = $studentRepo;
        $this->classRepo = $classRepo;
        $this->batchRepo = $batchRepo;
        $this->activityLog = $activityLog;
    }

    public function placeStudent($studentId, $newBatchId, $newClassId, $actorId)
    {
        $oldPlacement = $this->runTransaction(function () use ($studentId, $newBatchId, $newClassId, $actorId): array {
            $student = $this->lockOrFind($this->studentRepo, $studentId);
            if (! $student) {
                throw new Exception('Siswa tidak ditemukan.');
            }

            $oldBatchId = $student['Batch_ID'] ?? null;
            $oldClassId = $student['Class_ID'] ?? null;

            if ($oldBatchId === $newBatchId && $oldClassId === $newClassId) {
                return ['oldBatchId' => $oldBatchId, 'oldClassId' => $oldClassId, 'changed' => false];
            }

            if (! empty($newBatchId)) {
                $batch = $this->batchRepo->findById($newBatchId);
                if (! $batch || ($batch['Is_Active'] ?? 'TRUE') === 'FALSE') {
                    throw new Exception('Angkatan (Batch) tidak valid atau sedang tidak aktif.');
                }
            }

            $lockedClasses = [];
            $classIdsToLock = array_values(array_unique(array_filter([$oldClassId, $newClassId])));
            sort($classIdsToLock, SORT_STRING);
            foreach ($classIdsToLock as $classId) {
                $lockedClasses[$classId] = $this->lockOrFind($this->classRepo, $classId);
            }

            $newClass = $newClassId ? ($lockedClasses[$newClassId] ?? null) : null;
            if (! empty($newClassId)) {
                if (! $newClass || ($newClass['Is_Active'] ?? 'TRUE') === 'FALSE') {
                    throw new Exception('Kelas tidak valid atau sedang tidak aktif.');
                }
                if (! empty($newBatchId) && ($newClass['Batch_ID'] ?? null) !== $newBatchId) {
                    throw new Exception('Kelas yang dipilih tidak berada pada Batch siswa.');
                }

                $capacity = (int) ($newClass['Capacity'] ?? 0);
                $currentStudentCount = (int) ($newClass['Current_Student'] ?? 0);
                if ($oldClassId !== $newClassId && $currentStudentCount >= $capacity) {
                    throw new Exception('Kelas sudah mencapai kapasitas maksimum.');
                }
            }

            if (! empty($oldClassId) && $oldClassId !== $newClassId && ! empty($lockedClasses[$oldClassId])) {
                $oldCurrent = max(0, (int) ($lockedClasses[$oldClassId]['Current_Student'] ?? 0) - 1);
                $this->classRepo->update($oldClassId, [
                    'Current_Student' => $oldCurrent,
                    'Updated_At' => now()->toDateTimeString(),
                ]);
            }

            if (! empty($newClassId) && $oldClassId !== $newClassId) {
                $this->classRepo->update($newClassId, [
                    'Current_Student' => (int) ($newClass['Current_Student'] ?? 0) + 1,
                    'Updated_At' => now()->toDateTimeString(),
                ]);
            }

            $this->studentRepo->update($studentId, [
                'Batch_ID' => $newBatchId,
                'Class_ID' => $newClassId,
                'Updated_At' => now()->toDateTimeString(),
                'Updated_By' => $actorId,
            ]);

            $persisted = $this->studentRepo->findById($studentId);
            if (! $persisted
                || (string) ($persisted['Batch_ID'] ?? '') !== (string) $newBatchId
                || (string) ($persisted['Class_ID'] ?? '') !== (string) $newClassId) {
                throw new Exception('Perpindahan siswa gagal diverifikasi; seluruh perubahan dibatalkan.');
            }

            return ['oldBatchId' => $oldBatchId, 'oldClassId' => $oldClassId, 'changed' => true];
        });

        $oldBatchId = $oldPlacement['oldBatchId'];
        $oldClassId = $oldPlacement['oldClassId'];
        if (! $oldPlacement['changed']) {
            return true;
        }

        // Clear Targeted Caches
        $this->studentRepo->clearCache();
        $this->classRepo->clearCache();
        $this->batchRepo->clearCache();

        // 12. Audit Log
        $action = empty($oldClassId) ? 'PLACEMENT_CREATE' : (! empty($newClassId) ? 'PLACEMENT_MOVE' : 'PLACEMENT_REMOVE');
        try {
            $this->activityLog->log(
                'ACADEMIC',
                $action,
                "Siswa {$studentId} dipindahkan dari Kelas {$oldClassId} ke {$newClassId}",
                $actorId,
                [
                    'Student_ID' => $studentId,
                    'Old_Class_ID' => $oldClassId,
                    'New_Class_ID' => $newClassId,
                    'Old_Batch_ID' => $oldBatchId,
                    'New_Batch_ID' => $newBatchId,
                    'Actor' => $actorId,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Student placement audit log failed after persistence', [
                'student_id' => $studentId,
                'exception' => $e::class,
            ]);
        }

        return true;
    }

    private function runTransaction(\Closure $callback): mixed
    {
        if (method_exists($this->studentRepo, 'transaction')) {
            return $this->studentRepo->transaction($callback);
        }

        return $callback();
    }

    private function lockOrFind(object $repository, string $id): ?array
    {
        if (method_exists($repository, 'lockById')) {
            return $repository->lockById($id);
        }

        return $repository->findById($id);
    }
}
