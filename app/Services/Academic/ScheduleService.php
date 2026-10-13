<?php

namespace App\Services\Academic;

use App\Exceptions\ScheduleConflictException;
use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;
use App\Support\Academic\AcademicSheetMapper;

class ScheduleService
{
    protected $repository;

    public function __construct(ScheduleRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getAll()
    {
        return $this->repository->fetchAll();
    }

    public function getById($id)
    {
        return $this->repository->findById($id);
    }

    public function generateId()
    {
        return $this->repository->generateNewId('SCH', 6);
    }

    public function validateSchedule(array $data, $ignoreId = null)
    {
        if (isset($data['Start_Time']) && isset($data['End_Time'])) {
            if (strtotime($data['Start_Time']) >= strtotime($data['End_Time'])) {
                throw new ScheduleConflictException('Waktu mulai harus sebelum waktu selesai.');
            }
        }

        $all = $this->getAll()->map(fn ($item) => AcademicSheetMapper::normalizeScheduleRow((array) $item))
            ->filter(function ($item) use ($ignoreId) {
                return ($item['Is_Active'] ?? 'TRUE') === 'TRUE' && ($item['Schedule_ID'] ?? '') !== $ignoreId;
            });

        foreach ($all as $item) {
            if (($item['Academic_Year_ID'] ?? '') === ($data['Academic_Year_ID'] ?? '')
                && $this->canonicalDay($item['Day_Of_Week'] ?? '') === $this->canonicalDay($data['Day_Of_Week'] ?? '')) {
                // Check time overlap
                $start1 = strtotime($data['Start_Time']);
                $end1 = strtotime($data['End_Time']);
                $start2 = strtotime($item['Start_Time']);
                $end2 = strtotime($item['End_Time']);

                if ($start1 < $end2 && $end1 > $start2) {
                    if ($item['Teacher_ID'] === ($data['Teacher_ID'] ?? '')) {
                        throw new ScheduleConflictException('Jadwal pengajar bentrok dengan jadwal lain.');
                    }
                    if ($item['Class_ID'] === ($data['Class_ID'] ?? '')) {
                        throw new ScheduleConflictException('Jadwal kelas bentrok dengan jadwal lain.');
                    }
                    if (! empty($data['Room']) && $item['Room'] === $data['Room']) {
                        throw new ScheduleConflictException('Ruangan sedang digunakan pada waktu tersebut.');
                    }
                }
            }
        }
    }

    public function create(array $data)
    {
        $data = $this->normalizePayload($data);
        if (trim((string) ($data['Academic_Year_ID'] ?? '')) === '') {
            throw new \InvalidArgumentException('Tahun ajaran wajib dipilih dari master tahun ajaran.');
        }
        $this->validateSchedule($data);

        if (! isset($data['Schedule_ID'])) {
            $data['Schedule_ID'] = $this->generateId();
        }
        $data['Is_Active'] = $data['Is_Active'] ?? 'TRUE';
        $data['Created_At'] = now()->toDateTimeString();

        $result = $this->repository->create($data);
        $this->repository->clearCache();
        $this->assertReadBackMatches($data['Schedule_ID'], $data);

        return $result;
    }

    public function update($id, array $data)
    {
        return $this->runTransaction(function () use ($id, $data) {
            $existing = method_exists($this->repository, 'lockById')
                ? $this->repository->lockById($id)
                : $this->getById($id);
            if (! $existing) {
                throw new \RuntimeException("Jadwal '{$id}' tidak ditemukan.");
            }

            unset($data['Schedule_ID'], $data['id']);
            $data = $this->normalizePayload($data);
            $merged = array_merge(AcademicSheetMapper::normalizeScheduleRow((array) $existing), $data);
            $this->validateSchedule($merged, $id);

            $data['Updated_At'] = now()->toDateTimeString();
            $result = $this->repository->update($id, $data);
            $this->repository->clearCache();
            $this->assertReadBackMatches($id, $data);

            return $result;
        });
    }

    public function delete($id)
    {
        $result = $this->repository->delete($id);
        $this->repository->clearCache();

        return $result;
    }

    private function normalizePayload(array $data): array
    {
        foreach (['Class_ID', 'Subject_ID', 'Teacher_ID', 'Academic_Year_ID', 'Day_Of_Week', 'Room', 'Is_Active'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = trim((string) $data[$field]);
            }
        }

        foreach (['Start_Time', 'End_Time'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = AcademicSheetMapper::timeForStorage($data[$field]);
            }
        }

        return $data;
    }

    private function runTransaction(\Closure $callback): mixed
    {
        if (method_exists($this->repository, 'transaction')) {
            return $this->repository->transaction($callback);
        }

        return $callback();
    }

    private function canonicalDay(mixed $day): string
    {
        return match (strtolower(trim((string) $day))) {
            'monday', 'senin' => 'monday',
            'tuesday', 'selasa' => 'tuesday',
            'wednesday', 'rabu' => 'wednesday',
            'thursday', 'kamis' => 'thursday',
            'friday', 'jumat' => 'friday',
            'saturday', 'sabtu' => 'saturday',
            'sunday', 'minggu' => 'sunday',
            default => strtolower(trim((string) $day)),
        };
    }

    private function assertReadBackMatches(string $id, array $expected): void
    {
        $fresh = $this->repository->findById($id);
        if (! $fresh) {
            throw new \RuntimeException("Jadwal '{$id}' tidak dapat diverifikasi setelah disimpan.");
        }

        $fresh = AcademicSheetMapper::normalizeScheduleRow((array) $fresh);
        foreach (['Class_ID', 'Subject_ID', 'Teacher_ID', 'Academic_Year_ID', 'Day_Of_Week', 'Start_Time', 'End_Time', 'Room', 'Is_Active'] as $field) {
            if (! array_key_exists($field, $expected)) {
                continue;
            }

            if ((string) ($fresh[$field] ?? '') !== (string) $expected[$field]) {
                throw new \RuntimeException("Jadwal '{$id}' gagal diverifikasi: {$field} tidak sesuai setelah disimpan.");
            }
        }
    }
}
