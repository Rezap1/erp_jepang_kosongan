<?php

namespace App\Services\Academic;

use App\Exceptions\DuplicatePrimaryKeyException;
use App\Helpers\GradeHelper;
use App\Interfaces\GoogleSheets\AssessmentRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;
use App\Interfaces\GoogleSheets\ScoreRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use App\Support\Academic\TeacherScopeResolver;
use App\Support\ActorIdentity;
use Exception;

class ScoreService
{
    protected $repository;

    protected $enterpriseEvent;

    protected $assessmentRepository;

    protected $studentRepository;

    protected $assessmentConfigService;

    public function __construct(
        ScoreRepositoryInterface $repository,
        EnterpriseEventService $enterpriseEvent,
        AssessmentRepositoryInterface $assessmentRepository,
        StudentRepositoryInterface $studentRepository,
        ?AssessmentConfigService $assessmentConfigService = null
    ) {
        $this->repository = $repository;
        $this->enterpriseEvent = $enterpriseEvent;
        $this->assessmentRepository = $assessmentRepository;
        $this->studentRepository = $studentRepository;
        $this->assessmentConfigService = $assessmentConfigService;
    }

    public function getAll()
    {
        $scores = $this->repository->fetchAll();

        return $scores->map(function ($item) {
            $item['Parsed_Details'] = $this->parseEvaluationDetails($item);

            return $item;
        });
    }

    public function getById($id)
    {
        $score = $this->repository->findById($id);
        if ($score) {
            $score['Parsed_Details'] = $this->parseEvaluationDetails($score);
        }

        return $score;
    }

    public function generateId()
    {
        return $this->repository->generateNewId('SCR', 6);
    }

    public function parseEvaluationDetails($score): array
    {
        $detailsRaw = $score['Evaluation_Details'] ?? null;
        if (! empty($detailsRaw) && is_string($detailsRaw)) {
            $decoded = json_decode($detailsRaw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $category = strtolower($score['Assessment_Category'] ?? 'general');

        return [
            'category' => $category,
            'notes' => $score['Notes'] ?? $score['Remarks'] ?? '',
        ];
    }

    public function processEvaluationDetails(array $data): array
    {
        $category = strtoupper(trim($data['Assessment_Category'] ?? 'GENERAL'));

        $categoryConfig = null;
        $isNumericCategory = false;
        if ($this->assessmentConfigService) {
            $categoryConfig = $this->assessmentConfigService->getCategoryConfig($category);
            if (! $categoryConfig) {
                throw new Exception('Kategori penilaian tidak terdaftar pada MASTER_ASSESSMENT_CONFIG.');
            }
            $isNumericCategory = $this->assessmentConfigService->isNumericCategory($category);
        }

        $details = ['category' => strtolower($category)];
        $notes = $data['Notes'] ?? $data['Remarks'] ?? '';
        $details['notes'] = $notes;

        $reservedKeys = [
            '_token', 'Student_ID', 'Assessment_Category', 'Date', 'Notes', 'Remarks',
            '_method', 'Score_ID', 'Score_Value', 'Score', 'Subject_ID', 'Assessment_ID', 'Assignment_ID', 'Schedule_ID', 'Class_ID', 'Assessment_Date', 'Teacher_ID', 'Parsed_Details',
            'Evaluation_Details', 'Grade', 'Status', 'Created_At', 'Updated_At', 'Is_Active', 'Date',
        ];
        $reservedKeysNormalized = array_map(fn ($key) => strtolower((string) $key), $reservedKeys);

        foreach ($data as $key => $val) {
            if (! in_array(strtolower((string) $key), $reservedKeysNormalized, true) && $val !== '' && $val !== null) {
                // Parse numeric strings if they look like numbers
                if (is_numeric($val)) {
                    $details[$key] = strpos((string) $val, '.') !== false ? (float) $val : (int) $val;
                } else {
                    $details[$key] = $val;
                }
            }
        }

        if ($this->assessmentConfigService) {
            $configuredAspects = $this->assessmentConfigService->getAspects($category);
            if (! empty($configuredAspects)) {
                $aspectPayload = collect($details)
                    ->except(['category', 'notes', 'subject_id'])
                    ->toArray();
                if (! $this->assessmentConfigService->validateAspectPayload($category, $aspectPayload)) {
                    throw new Exception('Aspek penilaian tidak valid atau belum lengkap.');
                }
            }
        }

        // If Score_Value or Score is passed, use it. Otherwise leave it null for aspectual assessments.
        $scoreVal = null;
        if (isset($data['Score_Value']) && $data['Score_Value'] !== '') {
            $scoreVal = (float) $data['Score_Value'];
        } elseif (isset($data['Score']) && $data['Score'] !== '') {
            $scoreVal = (float) $data['Score'];
        }

        if ($isNumericCategory || ($category === 'GENERAL' && empty(array_diff(array_keys($details), ['category', 'notes'])))) {
            $rawScore = $data['Score_Value'] ?? $data['Score'] ?? null;
            if ($rawScore === null || $rawScore === '') {
                throw new Exception($category === 'UJIAN_BAB' ? 'Nilai Ujian Bab harus berupa angka.' : 'Nilai harus berupa angka.');
            }
            if (! is_int($rawScore) && ! is_float($rawScore) && ! is_string($rawScore)) {
                throw new Exception($category === 'UJIAN_BAB' ? 'Nilai Ujian Bab harus berupa angka.' : 'Nilai harus berupa angka.');
            }
            $rawScore = trim((string) $rawScore);
            if ($rawScore === '' || ! is_numeric($rawScore) || ! is_finite((float) $rawScore)) {
                throw new Exception($category === 'UJIAN_BAB' ? 'Nilai Ujian Bab harus berupa angka.' : 'Nilai harus berupa angka.');
            }
            $scoreVal = (float) $rawScore;
            if ($scoreVal < 0) {
                throw new Exception($category === 'UJIAN_BAB' ? 'Nilai Ujian Bab minimal 0.' : 'Nilai minimal 0.');
            }
            if ($scoreVal > 100) {
                throw new Exception($category === 'UJIAN_BAB' ? 'Nilai Ujian Bab maksimal 100.' : 'Nilai maksimal 100.');
            }
            $details['subject_id'] = $data['Subject_ID'] ?? '';
        }

        return [
            'category' => $category,
            'score_value' => $scoreVal,
            'evaluation_details' => json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
    }

    public function create(array $data)
    {
        $this->validateDependencies($data);
        $evalResult = $this->processEvaluationDetails($data);

        if (! isset($data['Score_ID'])) {
            $data['Score_ID'] = $this->generateId();
        }

        $scoreVal = $evalResult['score_value'];

        $data['Assessment_Category'] = $evalResult['category'];
        // MASTER_SCORE stores the legacy Assignment_ID column. Preserve the
        // caller's assessment reference without relying on a non-existent
        // Assessment_ID header.
        if (empty($data['Assignment_ID']) && ! empty($data['Assessment_ID'])) {
            $data['Assignment_ID'] = $data['Assessment_ID'];
        }
        $data['Score'] = $scoreVal ?? '';
        $data['Score_Value'] = $scoreVal ?? '';
        $data['Evaluation_Details'] = $evalResult['evaluation_details'];

        if ($scoreVal !== null) {
            $gradeResult = GradeHelper::calculate($scoreVal);
            $data['Grade'] = $gradeResult['grade'];
            $data['Status'] = $gradeResult['pass'] ? 'PASS' : 'FAIL';
        } else {
            $data['Grade'] = '';
            $data['Status'] = 'COMPLETED';
        }

        $data['Remarks'] = $data['Notes'] ?? ($data['Remarks'] ?? '');
        $data['Created_At'] = now()->toDateTimeString();

        // Score_ID is the durable idempotency key. The repository also checks
        // the primary key while holding its sheet write lock; a retry that
        // reaches this service after the first append is treated as a replay
        // only when the persisted business identity is identical.
        $existing = method_exists($this->repository, 'findByIdFresh')
            ? $this->repository->findByIdFresh($data['Score_ID'])
            : null;
        if ($existing) {
            if ($this->sameSubmission($existing, $data)) {
                return $existing;
            }
            throw new Exception('ID penilaian sudah digunakan untuk data lain.');
        }

        try {
            $res = $this->repository->create($data);
        } catch (DuplicatePrimaryKeyException $e) {
            $persisted = method_exists($this->repository, 'findByIdFresh')
                ? $this->repository->findByIdFresh($data['Score_ID'])
                : null;
            if (! $persisted || ! $this->sameSubmission($persisted, $data)) {
                throw new Exception('ID penilaian idempotent bertabrakan dengan data lain.', 0, $e);
            }

            return $persisted;
        }
        $this->repository->clearCache();

        // Preserve the authoritative persisted row after the database write.
        // response is not sufficient evidence that the intended row is
        // durable, so verify the persisted row before emitting side effects.
        $persisted = $this->readBackPersisted($data['Score_ID']);
        $this->assertPersistedIdentity($persisted, $data);

        // Notify Student & Dispatch Event
        $this->enterpriseEvent->dispatch(
            'ACADEMIC',
            'PUBLISH',
            'SCORE',
            $data['Score_ID'],
            ActorIdentity::required(),
            ['ACADEMIC'],
            [$data['Student_ID']],
            $persisted
        );

        return $persisted ?: $res;
    }

    public function update($id, array $data)
    {
        $this->validateDependencies($data);

        $existing = $this->getById($id);
        if (! $existing) {
            throw new Exception('Record nilai tidak ditemukan.');
        }

        // Merge existing category if not passed
        if (empty($data['Assessment_Category']) && ! empty($existing['Assessment_Category'])) {
            $data['Assessment_Category'] = $existing['Assessment_Category'];
        }

        $evalResult = $this->processEvaluationDetails(array_merge($existing, $data));
        $scoreVal = $evalResult['score_value'];

        $data['Assessment_Category'] = $evalResult['category'];
        if (empty($data['Assignment_ID']) && ! empty($data['Assessment_ID'])) {
            $data['Assignment_ID'] = $data['Assessment_ID'];
        }
        $data['Score'] = $scoreVal ?? '';
        $data['Score_Value'] = $scoreVal ?? '';
        $data['Evaluation_Details'] = $evalResult['evaluation_details'];

        if ($scoreVal !== null) {
            $gradeResult = GradeHelper::calculate($scoreVal);
            $data['Grade'] = $gradeResult['grade'];
            $data['Status'] = $gradeResult['pass'] ? 'PASS' : 'FAIL';
        } else {
            $data['Grade'] = '';
            $data['Status'] = 'COMPLETED';
        }

        $data['Remarks'] = $data['Notes'] ?? ($data['Remarks'] ?? ($existing['Remarks'] ?? ''));
        $data['Updated_At'] = now()->toDateTimeString();

        $res = $this->repository->update($id, $data);
        $this->repository->clearCache();

        $persisted = $this->readBackPersisted($id);
        $this->assertPersistedIdentity($persisted, array_merge($existing, $data));

        $studentId = $persisted['Student_ID'] ?? ($existing['Student_ID'] ?? null);

        $this->enterpriseEvent->dispatch(
            'ACADEMIC',
            'UPDATE',
            'SCORE',
            $id,
            ActorIdentity::required(),
            ['ACADEMIC'],
            $studentId ? [$studentId] : [],
            $persisted
        );

        return $persisted ?: $res;
    }

    public function delete($id)
    {
        $score = $this->getById($id);
        $res = $this->repository->delete($id);
        $this->repository->clearCache();

        $studentId = $score['Student_ID'] ?? null;

        $this->enterpriseEvent->dispatch(
            'ACADEMIC',
            'DELETE',
            'SCORE',
            $id,
            ActorIdentity::required(),
            ['ACADEMIC'],
            $studentId ? [$studentId] : [],
            []
        );

        return $res;
    }

    protected function validateDependencies(array $data)
    {
        if (isset($data['Student_ID'])) {
            $student = $this->studentRepository->findById($data['Student_ID']);
            if (! $student) {
                throw new Exception('Siswa tidak valid.');
            }
        }
    }

    private function readBackPersisted(string $scoreId): ?array
    {
        $persisted = null;
        if (method_exists($this->repository, 'findByIdFresh')) {
            $persisted = $this->repository->findByIdFresh($scoreId);
        } else {
            $persisted = $this->repository->findById($scoreId);
        }

        if (! $persisted || ! is_array($persisted)) {
            throw new Exception('Nilai tersimpan tidak dapat diverifikasi dari sumber data.');
        }

        return $persisted;
    }

    private function assertPersistedIdentity(?array $persisted, array $expected): void
    {
        foreach (['Score_ID', 'Student_ID', 'Schedule_ID', 'Assignment_ID', 'Assessment_Category', 'Evaluation_Details'] as $field) {
            $expectedValue = trim((string) ($expected[$field] ?? ($field === 'Assignment_ID' ? ($expected['Assessment_ID'] ?? '') : '')));
            if ($expectedValue === '') {
                continue;
            }
            $persistedValue = trim((string) ($persisted[$field] ?? ($field === 'Assignment_ID' ? ($persisted['Assessment_ID'] ?? '') : '')));
            if ($field === 'Assessment_Category') {
                $expectedValue = strtoupper($expectedValue);
                $persistedValue = strtoupper($persistedValue);
            }
            if ($persistedValue !== $expectedValue) {
                throw new Exception('Verifikasi persistence nilai gagal.');
            }
        }
    }

    private function sameSubmission(array $existing, array $expected): bool
    {
        foreach (['Student_ID', 'Schedule_ID', 'Assignment_ID', 'Assessment_Category', 'Evaluation_Details'] as $field) {
            $left = trim((string) ($existing[$field] ?? ($field === 'Assignment_ID' ? ($existing['Assessment_ID'] ?? '') : '')));
            $right = trim((string) ($expected[$field] ?? ($field === 'Assignment_ID' ? ($expected['Assessment_ID'] ?? '') : '')));
            if ($field === 'Assessment_Category') {
                $left = strtoupper($left);
                $right = strtoupper($right);
            }
            if ($left !== $right) {
                return false;
            }
        }

        return true;
    }

    /**
     * H8.21: Get students within a teacher's scope.
     * Teacher -> Schedule -> Class_ID -> current MASTER_STUDENT.Class_ID
     */
    public function getStudentsInTeacherScope($teacherId): array
    {
        $scope = $this->getTeacherScoreScope((string) $teacherId);

        return collect($scope['students'] ?? [])
            ->whereIn('Student_ID', $scope['teaching_student_ids'] ?? [])
            ->values()
            ->toArray();
    }

    /**
     * H8.21: Check if a student is within a teacher's scope.
     */
    public function isStudentInTeacherScope($studentId, $teacherId): bool
    {
        $scopedStudents = $this->getStudentsInTeacherScope($teacherId);

        return collect($scopedStudents)->contains('Student_ID', $studentId);
    }

    /**
     * Return the complete, server-resolved teaching scope used by score
     * authorization. Client-supplied class/schedule/student identifiers are
     * never treated as ownership evidence.
     */
    public function getTeacherScoreScope(string $teacherId): array
    {
        $resolver = new TeacherScopeResolver(
            app(TeacherRepositoryInterface::class),
            app(ScheduleRepositoryInterface::class),
            app(ClassRepositoryInterface::class),
            $this->studentRepository,
            $this->assessmentRepository
        );

        return $resolver->resolveForTeacherId($teacherId);
    }

    public function isAssessmentInTeacherScope($assessmentId, string $teacherId): bool
    {
        $assessmentId = trim((string) $assessmentId);

        return $assessmentId !== '' && in_array($assessmentId, $this->getTeacherScoreScope($teacherId)['assessment_ids'], true);
    }

    public function isScoreInTeacherScope(array $score, string $teacherId, ?array $scope = null): bool
    {
        $scope ??= $this->getTeacherScoreScope($teacherId);
        $studentId = trim((string) ($score['Student_ID'] ?? ''));
        if ($studentId === '' || ! in_array($studentId, $scope['teaching_student_ids'] ?? [], true)) {
            return false;
        }

        $scoreTeacherId = trim((string) ($score['Teacher_ID'] ?? ''));
        if ($scoreTeacherId !== '') {
            return $scoreTeacherId === trim($teacherId);
        }

        // MASTER_SCORE has no durable Teacher_ID/Assessment_ID columns. A
        // score is therefore authorized by its persisted Schedule_ID (or the
        // legacy Assignment_ID mapped into the assessment scope), never by a
        // client-provided teacher identifier.
        $scheduleId = trim((string) ($score['Schedule_ID'] ?? ''));
        if ($scheduleId !== '') {
            return in_array($scheduleId, $scope['schedule_ids'], true)
                && $this->isStudentInSchedule($studentId, $scheduleId, $scope);
        }

        $assessmentId = trim((string) ($score['Assessment_ID'] ?? $score['Assignment_ID'] ?? ''));

        return $assessmentId !== '' && in_array($assessmentId, $scope['assessment_ids'], true);
    }

    public function isStudentInSchedule(string $studentId, string $scheduleId, ?array $scope = null): bool
    {
        if ($scope === null) {
            return false;
        }

        return in_array($studentId, $scope['schedule_student_ids'][$scheduleId] ?? [], true);
    }
}
