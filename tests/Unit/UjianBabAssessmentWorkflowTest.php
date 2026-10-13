<?php

namespace Tests\Unit;

use App\Http\Controllers\Academic\StudentWorkspaceController;
use App\Http\Controllers\Academic\TeacherWorkspaceController;
use App\Http\Requests\StoreScoreRequest;
use App\Http\Requests\UpdateScoreRequest;
use App\Interfaces\GoogleSheets\AssessmentConfigRepositoryInterface;
use App\Interfaces\GoogleSheets\AssessmentRepositoryInterface;
use App\Interfaces\GoogleSheets\ScoreRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Academic\AssessmentConfigService;
use App\Services\Academic\AttendanceService;
use App\Services\Academic\ScheduleService;
use App\Services\Academic\ScoreService;
use App\Services\Academic\SubjectService;
use App\Services\Attendance\AttendanceRequestService;
use App\Services\Core\AssignmentService;
use App\Services\Core\BatchService;
use App\Services\Core\ClassService;
use App\Services\Core\EnterpriseEventService;
use App\Services\Core\StudentService;
use App\Services\Core\TeacherService;
use App\Support\Academic\TeacherScopeResolver;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class UjianBabAssessmentWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('assessment_configs');
    }

    protected function tearDown(): void
    {
        Cache::forget('assessment_configs');
        Mockery::close();
        parent::tearDown();
    }

    public function test_built_in_ujian_bab_category_is_active_numeric_and_human_labeled(): void
    {
        $service = $this->assessmentConfigService([
            ['Category_ID' => 'LANGUAGE', 'Category_Name' => 'Language', 'Is_Active' => 'TRUE', 'Aspects_JSON' => json_encode([
                ['id' => 'speaking', 'label' => 'Speaking'],
            ])],
        ]);

        $config = $service->getCategoryConfig('ujian_bab');

        $this->assertNotNull($config);
        $this->assertSame('UJIAN_BAB', $config['Category_ID']);
        $this->assertSame('Ujian Bab', $service->categoryLabel('UJIAN_BAB'));
        $this->assertTrue($service->isNumericCategory('ujian_bab'));
        $this->assertSame([], $service->getAspects('UJIAN_BAB'));
    }

    public function test_ujian_bab_accepts_zero_midpoint_and_maximum_scores(): void
    {
        $service = $this->scoreProcessingService();

        foreach ([0, 50, 100] as $score) {
            $result = $service->processEvaluationDetails([
                'Assessment_Category' => 'UJIAN_BAB',
                'Score' => (string) $score,
                'Subject_ID' => 'SUB-A',
                'Notes' => 'Catatan uji',
            ]);
            $details = json_decode($result['evaluation_details'], true);

            $this->assertSame('UJIAN_BAB', $result['category']);
            $this->assertSame((float) $score, $result['score_value']);
            $this->assertSame('ujian_bab', $details['category']);
            $this->assertSame('SUB-A', $details['subject_id']);
            $this->assertSame('Catatan uji', $details['notes']);
        }
    }

    public function test_ujian_bab_rejects_scores_outside_range_and_malformed_values(): void
    {
        $service = $this->scoreProcessingService();

        foreach ([
            '-1' => 'Nilai Ujian Bab minimal 0.',
            '101' => 'Nilai Ujian Bab maksimal 100.',
            'abc' => 'Nilai Ujian Bab harus berupa angka.',
            '' => 'Nilai Ujian Bab harus berupa angka.',
        ] as $score => $message) {
            try {
                $service->processEvaluationDetails([
                    'Assessment_Category' => 'UJIAN_BAB',
                    'Score' => $score,
                ]);
                $this->fail("Score {$score} seharusnya ditolak.");
            } catch (\Exception $e) {
                $this->assertSame($message, $e->getMessage());
            }
        }
    }

    public function test_score_requests_enforce_ujian_bab_numeric_payload_and_range(): void
    {
        $this->app->instance(AssessmentConfigService::class, $this->assessmentConfigService());

        foreach ([StoreScoreRequest::class, UpdateScoreRequest::class] as $requestClass) {
            $this->assertTrue($this->validatorFor($requestClass, [
                'Student_ID' => 'STU-A',
                'Assessment_Category' => 'UJIAN_BAB',
                'Score_Value' => '0',
            ])->passes());

            foreach ([['', 'Score_Value'], ['-1', 'Score_Value'], ['101', 'Score_Value'], ['not-a-number', 'Score_Value']] as [$value, $field]) {
                $validator = $this->validatorFor($requestClass, [
                    'Student_ID' => 'STU-A',
                    'Assessment_Category' => 'UJIAN_BAB',
                    $field => $value,
                ]);

                $this->assertFalse($validator->passes());
                $this->assertArrayHasKey($field, $validator->errors()->toArray());
            }
        }
    }

    public function test_teacher_create_form_renders_ujian_bab_numeric_input(): void
    {
        $configService = $this->assessmentConfigService();

        $html = view('academic.teacher.scores-create', [
            'classes' => collect([]),
            'students' => collect([
                ['Student_ID' => 'STU-A', 'Full_Name' => 'Andi', 'Class_ID' => 'CLS-A', 'Class_Name' => 'Kelas A'],
            ]),
            'schedules' => collect([
                ['Schedule_ID' => 'SCH-A', 'Class_ID' => 'CLS-A', 'Subject_ID' => 'SUB-A', 'label' => 'Bahasa - Kelas A'],
            ]),
            'studentsByClass' => ['CLS-A' => ['STU-A']],
            'studentsBySchedule' => ['SCH-A' => ['STU-A']],
            'teacherId' => 'TCH-A',
            'assessmentConfigs' => $configService->getActiveCategories(),
        ])->render();

        $this->assertStringContainsString('Ujian Bab', $html);
        $this->assertStringContainsString('Nilai Ujian Bab', $html);
        $this->assertStringContainsString('name="Score"', $html);
        $this->assertStringContainsString('min="0"', $html);
        $this->assertStringContainsString('max="100"', $html);
        $this->assertStringContainsString('!hasAspects && !isNumeric', $html);
    }

    public function test_teacher_history_and_student_progress_render_ujian_bab_as_numeric_score(): void
    {
        $configService = $this->assessmentConfigService();
        $configMap = collect($configService->getActiveCategories())->keyBy('Category_ID')->toArray();
        $score = [
            'Score_ID' => 'SCR-UJIAN',
            'Student_ID' => 'STU-A',
            'Student_Name' => 'Andi',
            'Assessment_Category' => 'UJIAN_BAB',
            'Score' => '90',
            'Score_Value' => '90',
            'Evaluation_Details' => json_encode(['category' => 'ujian_bab', 'notes' => 'Mantap', 'subject_id' => 'SUB-A']),
            'Created_At' => '2026-09-08 08:00:00',
        ];

        $teacherHtml = view('academic.teacher.scores', [
            'scores' => collect([$score]),
            'teacherId' => 'TCH-A',
            'assessmentConfigs' => $configMap,
        ])->render();
        $studentHtml = view('academic.student.progress', [
            'progress' => ['gpa' => 90, 'attendance' => 0, 'total_assessments' => 1],
            'studentId' => 'STU-A',
            'myScores' => collect([$score]),
            'myAttendances' => collect([]),
            'assessmentConfigs' => $configMap,
        ])->render();

        $this->assertStringContainsString('Ujian Bab', $teacherHtml);
        $this->assertStringContainsString('90', $teacherHtml);
        $this->assertStringNotContainsString('UJIAN_BAB', $teacherHtml);
        $this->assertStringContainsString('Penilaian Ujian Bab', $studentHtml);
        $this->assertStringContainsString('90', $studentHtml);
        $this->assertStringNotContainsString('Lihat Detail Penilaian Aspektual', $studentHtml);
    }

    public function test_student_progress_average_includes_ujian_bab_score_even_when_details_exist(): void
    {
        $this->actingAs(new GenericUser(['id' => 'USR-STU', 'User_ID' => 'USR-STU', 'Role' => 'STUDENT']));

        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect([
            ['Student_ID' => 'STU-A', 'User_ID' => 'USR-STU', 'Class_ID' => 'CLS-A'],
        ]));

        $scoreService = Mockery::mock(ScoreService::class);
        $scoreService->shouldReceive('getAll')->once()->andReturn(collect([
            [
                'Score_ID' => 'SCR-UJIAN',
                'Student_ID' => 'STU-A',
                'Assessment_Category' => 'UJIAN_BAB',
                'Score' => '80',
                'Score_Value' => '80',
                'Evaluation_Details' => json_encode(['category' => 'ujian_bab', 'subject_id' => 'SUB-A']),
            ],
            [
                'Score_ID' => 'SCR-ASPECT',
                'Student_ID' => 'STU-A',
                'Assessment_Category' => 'LANGUAGE',
                'Score' => '',
                'Evaluation_Details' => json_encode(['category' => 'language', 'speaking' => 5]),
            ],
        ]));

        $attendanceService = Mockery::mock(AttendanceService::class);
        $attendanceService->shouldReceive('getAll')->once()->andReturn(collect());

        $attendanceRequestService = Mockery::mock(AttendanceRequestService::class);
        $attendanceRequestService->shouldReceive('getStudentRequests')->once()->with('STU-A')->andReturn(collect());

        $controller = new StudentWorkspaceController(
            $studentRepo,
            Mockery::mock(ScheduleService::class),
            Mockery::mock(SubjectService::class),
            $scoreService,
            $attendanceService,
            $attendanceRequestService,
            $this->assessmentConfigService([
                ['Category_ID' => 'LANGUAGE', 'Category_Name' => 'Language', 'Is_Active' => 'TRUE', 'Aspects_JSON' => json_encode([
                    ['id' => 'speaking', 'label' => 'Speaking'],
                ])],
            ])
        );

        $view = $controller->progress();

        $this->assertSame(80.0, $view->getData()['progress']['gpa']);
    }

    public function test_teacher_can_create_ujian_bab_with_server_resolved_schedule_identity(): void
    {
        $this->actingAs(new GenericUser(['id' => 'USR-T', 'User_ID' => 'USR-T', 'Role' => 'TEACHER']));
        $scope = $this->teacherScope();

        $scoreService = Mockery::mock(ScoreService::class);
        $scoreService->shouldReceive('getTeacherScoreScope')->once()->with('TCH-A')->andReturn($scope);
        $scoreService->shouldReceive('isStudentInSchedule')->once()->with('STU-A', 'SCH-A', $scope)->andReturn(true);
        $scoreService->shouldReceive('create')->once()->with(Mockery::on(function (array $data): bool {
            return $data['Teacher_ID'] === 'TCH-A'
                && $data['Student_ID'] === 'STU-A'
                && $data['Schedule_ID'] === 'SCH-A'
                && $data['Class_ID'] === 'CLS-A'
                && $data['Subject_ID'] === 'SUB-A'
                && $data['Assessment_Category'] === 'UJIAN_BAB'
                && $data['Score'] === '100';
        }))->andReturn(['Score_ID' => 'SCR-UJIAN']);

        $controller = $this->teacherController($scoreService, $this->scheduleService(), $this->assessmentConfigService());
        $response = $controller->scoresStore(Request::create('/teacher/scores', 'POST', [
            'Student_ID' => 'STU-A',
            'Schedule_ID' => 'SCH-A',
            'Class_ID' => 'FORGED-CLASS',
            'Subject_ID' => 'FORGED-SUBJECT',
            'Assessment_Category' => 'UJIAN_BAB',
            'Date' => '2026-09-08',
            'Score' => '100',
        ]));

        $this->assertSame(302, $response->getStatusCode());
    }

    public function test_teacher_can_edit_ujian_bab_without_trusting_forged_identity_fields(): void
    {
        $this->actingAs(new GenericUser(['id' => 'USR-T', 'User_ID' => 'USR-T', 'Role' => 'TEACHER']));
        $scope = $this->teacherScope();
        $existing = [
            'Score_ID' => 'SCR-UJIAN',
            'Student_ID' => 'STU-A',
            'Teacher_ID' => 'TCH-A',
            'Schedule_ID' => 'SCH-A',
            'Assessment_Category' => 'UJIAN_BAB',
            'Score' => '80',
            'Score_Value' => '80',
            'Evaluation_Details' => json_encode(['category' => 'ujian_bab', 'subject_id' => 'SUB-A']),
        ];

        $scoreService = Mockery::mock(ScoreService::class);
        $scoreService->shouldReceive('getById')->once()->with('SCR-UJIAN')->andReturn($existing);
        $scoreService->shouldReceive('getTeacherScoreScope')->once()->with('TCH-A')->andReturn($scope);
        $scoreService->shouldReceive('isScoreInTeacherScope')->once()->with($existing, 'TCH-A', $scope)->andReturn(true);
        $scoreService->shouldReceive('update')->once()->with('SCR-UJIAN', Mockery::on(function (array $data): bool {
            return $data['Student_ID'] === 'STU-A'
                && $data['Teacher_ID'] === 'TCH-A'
                && $data['Class_ID'] === 'CLS-A'
                && $data['Subject_ID'] === 'SUB-A'
                && $data['Assessment_Category'] === 'UJIAN_BAB'
                && $data['Score'] === '90'
                && ! array_key_exists('Schedule_ID', $data);
        }))->andReturn(['Score_ID' => 'SCR-UJIAN']);

        $controller = $this->teacherController($scoreService, $this->scheduleService(), $this->assessmentConfigService());
        $response = $controller->scoresUpdate(Request::create('/teacher/scores/SCR-UJIAN', 'PUT', [
            'Student_ID' => 'STU-FORGED',
            'Schedule_ID' => 'SCH-FORGED',
            'Class_ID' => 'CLS-FORGED',
            'Subject_ID' => 'SUB-FORGED',
            'Assessment_Category' => 'UJIAN_BAB',
            'Date' => '2026-09-08',
            'Score' => '90',
        ]), 'SCR-UJIAN');

        $this->assertSame(302, $response->getStatusCode());
    }

    public function test_teacher_cannot_create_ujian_bab_for_foreign_schedule(): void
    {
        $this->actingAs(new GenericUser(['id' => 'USR-T', 'User_ID' => 'USR-T', 'Role' => 'TEACHER']));

        $scoreService = Mockery::mock(ScoreService::class);
        $scoreService->shouldReceive('getTeacherScoreScope')->once()->with('TCH-A')->andReturn($this->teacherScope());
        $scoreService->shouldReceive('isStudentInSchedule')->never();
        $scoreService->shouldReceive('create')->never();

        $controller = $this->teacherController($scoreService, $this->scheduleService(), $this->assessmentConfigService());

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('scope pengajaran');

        $controller->scoresStore(Request::create('/teacher/scores', 'POST', [
            'Student_ID' => 'STU-A',
            'Schedule_ID' => 'SCH-FOREIGN',
            'Assessment_Category' => 'UJIAN_BAB',
            'Date' => '2026-09-08',
            'Score' => '75',
        ]));
    }

    private function assessmentConfigService(array $rows = []): AssessmentConfigService
    {
        Cache::forget('assessment_configs');
        $repository = Mockery::mock(AssessmentConfigRepositoryInterface::class);
        $repository->shouldReceive('getAll')->zeroOrMoreTimes()->andReturn($rows);
        $repository->shouldReceive('getByCategory')->zeroOrMoreTimes()->andReturn(null);

        return new AssessmentConfigService($repository);
    }

    private function scoreProcessingService(): ScoreService
    {
        return new ScoreService(
            Mockery::mock(ScoreRepositoryInterface::class),
            Mockery::mock(EnterpriseEventService::class),
            Mockery::mock(AssessmentRepositoryInterface::class),
            Mockery::mock(StudentRepositoryInterface::class),
            $this->assessmentConfigService()
        );
    }

    private function validatorFor(string $requestClass, array $payload)
    {
        $request = $requestClass::create('/scores', 'POST', $payload);
        $request->setContainer($this->app);
        $validator = Validator::make($request->all(), $request->rules(), method_exists($request, 'messages') ? $request->messages() : []);

        if (method_exists($request, 'withValidator')) {
            $request->withValidator($validator);
        }

        return $validator;
    }

    private function teacherScope(): array
    {
        return [
            'schedule_ids' => ['SCH-A'],
            'class_ids' => ['CLS-A'],
            'subject_ids' => ['SUB-A'],
            'student_ids' => ['STU-A'],
            'assessment_ids' => [],
            'schedule_student_ids' => ['SCH-A' => ['STU-A']],
        ];
    }

    private function scheduleService(): ScheduleService
    {
        return Mockery::mock(ScheduleService::class)
            ->shouldReceive('getById')->zeroOrMoreTimes()->with('SCH-A')->andReturn([
                'Schedule_ID' => 'SCH-A',
                'Teacher_ID' => 'TCH-A',
                'Class_ID' => 'CLS-A',
                'Subject_ID' => 'SUB-A',
            ])->getMock();
    }

    private function teacherController(
        ScoreService $scoreService,
        ScheduleService $scheduleService,
        AssessmentConfigService $assessmentConfigService
    ): TeacherWorkspaceController {
        $scopeResolver = Mockery::mock(TeacherScopeResolver::class);
        $scopeResolver->shouldReceive('resolveForUser')->zeroOrMoreTimes()->andReturn(array_merge(
            ['teacher_id' => 'TCH-A'],
            $this->teacherScope()
        ));
        $scopeResolver->shouldReceive('resolveForTeacherId')->zeroOrMoreTimes()->with('TCH-A')->andReturn(array_merge(
            ['teacher_id' => 'TCH-A'],
            $this->teacherScope()
        ));

        return new TeacherWorkspaceController(
            Mockery::mock(TeacherService::class)->shouldReceive('getAllTeachers')->zeroOrMoreTimes()->andReturn(collect([
                ['Teacher_ID' => 'TCH-A', 'User_ID' => 'USR-T'],
            ]))->getMock(),
            Mockery::mock(ClassService::class),
            $scheduleService,
            Mockery::mock(StudentService::class),
            Mockery::mock(AttendanceService::class),
            Mockery::mock(AttendanceRequestService::class),
            Mockery::mock(SubjectService::class),
            Mockery::mock(BatchService::class),
            Mockery::mock(AssignmentService::class),
            $scoreService,
            $assessmentConfigService,
            $scopeResolver
        );
    }
}
