<?php

namespace Tests\Feature;

use App\Interfaces\GoogleSheets\AssessmentRepositoryInterface;
use App\Services\Academic\AssessmentConfigService;
use App\Services\Academic\AttendanceService;
use App\Services\Academic\ScheduleService;
use App\Services\Academic\ScoreService;
use App\Services\Academic\SubjectService;
use App\Services\Attendance\AttendanceRequestService;
use App\Services\Core\AssignmentService;
use App\Services\Core\BatchService;
use App\Services\Core\ClassService;
use App\Services\Core\NotificationService;
use App\Services\Core\RoleService;
use App\Services\Core\StudentService;
use App\Services\Core\SystemSettingService;
use App\Services\Core\TeacherService;
use App\Support\Academic\TeacherScopeResolver;
use Carbon\Carbon;
use Illuminate\Auth\GenericUser;
use Mockery;
use Tests\TestCase;

class H861TeacherScorePdfFilteringTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-12 10:00:00');

        $this->app->instance(RoleService::class, $this->mockService(RoleService::class, [
            'getRoleById' => ['Role_ID' => 'ROLE-TEACHER-H861', 'Role_Name' => 'TEACHER', 'Is_Active' => 'TRUE'],
        ]));
        $this->app->instance(TeacherService::class, $this->mockService(TeacherService::class, [
            'getAllTeachers' => collect([['Teacher_ID' => 'TCH-A', 'User_ID' => 'USR-TCH-A', 'Full_Name' => 'Budi Pengajar']]),
        ]));
        $this->app->instance(ClassService::class, $this->mockService(ClassService::class, [
            'getAllClasses' => collect([
                ['Class_ID' => 'CLS-A', 'Class_Name' => 'Kelas Sakura'],
                ['Class_ID' => 'CLS-B', 'Class_Name' => 'Kelas Fuji'],
                ['Class_ID' => 'CLS-C', 'Class_Name' => 'Kelas Asing'],
            ]),
        ]));
        $this->app->instance(ScheduleService::class, $this->mockService(ScheduleService::class, [
            'getAll' => collect([
                ['Schedule_ID' => 'SCH-A', 'Teacher_ID' => 'TCH-A', 'Class_ID' => 'CLS-A', 'Subject_ID' => 'SUB-JP'],
                ['Schedule_ID' => 'SCH-B', 'Teacher_ID' => 'TCH-A', 'Class_ID' => 'CLS-B', 'Subject_ID' => 'SUB-MATH'],
                ['Schedule_ID' => 'SCH-C', 'Teacher_ID' => 'TCH-B', 'Class_ID' => 'CLS-C', 'Subject_ID' => 'SUB-X'],
            ]),
        ]));
        $this->app->instance(StudentService::class, $this->mockService(StudentService::class, [
            'getAllStudents' => collect([
                ['Student_ID' => 'STU-A', 'Student_Number' => 'NIS-001', 'Full_Name' => 'Aiko Tanaka', 'Class_ID' => 'CLS-A'],
                ['Student_ID' => 'STU-EMPTY', 'Student_Number' => 'NIS-002', 'Full_Name' => 'Emi Kosong', 'Class_ID' => 'CLS-A'],
                ['Student_ID' => 'STU-B', 'Student_Number' => 'NIS-003', 'Full_Name' => 'Beni Fuji', 'Class_ID' => 'CLS-B'],
                ['Student_ID' => 'STU-B2', 'Student_Number' => 'NIS-004', 'Full_Name' => 'Citra Fuji', 'Class_ID' => 'CLS-B'],
                ['Student_ID' => 'STU-F', 'Student_Number' => 'NIS-999', 'Full_Name' => 'Siswa Asing', 'Class_ID' => 'CLS-C'],
            ]),
        ]));
        $this->app->instance(SubjectService::class, $this->mockService(SubjectService::class, [
            'getAll' => collect([
                ['Subject_ID' => 'SUB-JP', 'Subject_Name' => 'Bahasa Jepang'],
                ['Subject_ID' => 'SUB-MATH', 'Subject_Name' => 'Matematika'],
                ['Subject_ID' => 'SUB-X', 'Subject_Name' => 'Rahasia'],
            ]),
        ]));
        $this->app->instance(AssignmentService::class, $this->mockService(AssignmentService::class, [
            'getAll' => collect([['Assignment_ID' => 'ASG-B', 'Title' => 'Latihan Aljabar']]),
        ]));

        $scope = [
            'teacher_id' => 'TCH-A',
            'schedule_ids' => ['SCH-A', 'SCH-B'],
            'class_ids' => ['CLS-A', 'CLS-B'],
            'managed_class_ids' => ['CLS-A', 'CLS-B'],
            'teaching_class_ids' => ['CLS-A', 'CLS-B'],
            'subject_ids' => ['SUB-JP', 'SUB-MATH'],
            'student_ids' => ['STU-A', 'STU-EMPTY', 'STU-B', 'STU-B2'],
            'managed_student_ids' => ['STU-A', 'STU-EMPTY', 'STU-B', 'STU-B2'],
            'teaching_student_ids' => ['STU-A', 'STU-EMPTY', 'STU-B', 'STU-B2'],
            'assessment_ids' => ['ASM-A', 'ASM-A-OLD'],
            'students_by_class' => ['CLS-A' => ['STU-A', 'STU-EMPTY'], 'CLS-B' => ['STU-B', 'STU-B2']],
            'managed_students_by_class' => ['CLS-A' => ['STU-A', 'STU-EMPTY'], 'CLS-B' => ['STU-B', 'STU-B2']],
            'teaching_students_by_class' => ['CLS-A' => ['STU-A', 'STU-EMPTY'], 'CLS-B' => ['STU-B', 'STU-B2']],
            'schedule_student_ids' => ['SCH-A' => ['STU-A', 'STU-EMPTY'], 'SCH-B' => ['STU-B', 'STU-B2']],
            'classes' => collect([
                ['Class_ID' => 'CLS-A', 'Class_Name' => 'Kelas Sakura'],
                ['Class_ID' => 'CLS-B', 'Class_Name' => 'Kelas Fuji'],
            ]),
        ];
        $scores = collect([
            ['Score_ID' => 'SCR-A-NOW', 'Schedule_ID' => 'SCH-A', 'Student_ID' => 'STU-A', 'Assessment_ID' => 'ASM-A', 'Assessment_Category' => 'UJIAN_BAB', 'Assessment_Date' => '2026-09-11', 'Score' => 90, 'Grade' => 'A', 'Status' => 'PUBLISHED', 'Evaluation_Details' => '{"pronunciation":4,"notes":"Catatan A sekarang"}'],
            ['Score_ID' => 'SCR-A-OLD', 'Schedule_ID' => 'SCH-A', 'Student_ID' => 'STU-A', 'Assessment_ID' => 'ASM-A-OLD', 'Assessment_Category' => 'LANGUAGE', 'Date' => '2026-09-10', 'Score' => 82, 'Grade' => 'B', 'Status' => 'PASS', 'Evaluation_Details' => '{"grammar":3}'],
            ['Score_ID' => 'SCR-B-NOW', 'Schedule_ID' => 'SCH-B', 'Student_ID' => 'STU-B', 'Assignment_ID' => 'ASG-B', 'Assessment_Category' => 'LANGUAGE', 'Created_At' => '2026-09-11 08:00:00', 'Score_Value' => 77, 'Grade' => 'B', 'Status' => 'COMPLETED', 'Evaluation_Details' => '{"reasoning":"Penjelasan sangat panjang untuk memastikan detail tetap dibungkus dan terbaca di dalam halaman laporan tanpa menampilkan JSON mentah.","notes":"Perlu latihan lanjutan"}'],
            ['Score_ID' => 'SCR-CROSS', 'Teacher_ID' => 'TCH-A', 'Schedule_ID' => 'SCH-C', 'Student_ID' => 'STU-A', 'Assessment_Category' => 'UJIAN_BAB', 'Assessment_Date' => '2026-09-11', 'Score' => 100, 'Evaluation_Details' => '{"secret":"JANGAN-BOCOR-CROSS"}'],
            ['Score_ID' => 'SCR-TEACHER-ONLY', 'Teacher_ID' => 'TCH-A', 'Student_ID' => 'STU-A', 'Assessment_Category' => 'UJIAN_BAB', 'Assessment_Date' => '2026-09-11', 'Score' => 100, 'Evaluation_Details' => '{"secret":"JANGAN-BOCOR-TEACHER-ONLY"}'],
            ['Score_ID' => 'SCR-FOREIGN', 'Schedule_ID' => 'SCH-C', 'Student_ID' => 'STU-F', 'Assessment_Category' => 'UJIAN_BAB', 'Assessment_Date' => '2026-09-11', 'Score' => 100, 'Evaluation_Details' => '{"secret":"JANGAN-BOCOR-ASING"}'],
        ]);
        $scoreService = Mockery::mock(ScoreService::class);
        $scoreService->shouldReceive('getTeacherScoreScope')->zeroOrMoreTimes()->andReturn($scope);
        $scoreService->shouldReceive('getAll')->zeroOrMoreTimes()->andReturn($scores);
        $scoreService->shouldReceive('isScoreInTeacherScope')->zeroOrMoreTimes()->andReturnUsing(
            function (array $score, string $teacherId, array $resolvedScope): bool {
                $studentId = trim((string) ($score['Student_ID'] ?? ''));
                if (! in_array($studentId, $resolvedScope['student_ids'] ?? [], true)) {
                    return false;
                }

                $scoreTeacherId = trim((string) ($score['Teacher_ID'] ?? ''));
                if ($scoreTeacherId !== '') {
                    return $scoreTeacherId === $teacherId;
                }

                $scheduleId = trim((string) ($score['Schedule_ID'] ?? ''));
                if ($scheduleId !== '') {
                    return in_array($scheduleId, $resolvedScope['schedule_ids'] ?? [], true)
                        && in_array($studentId, $resolvedScope['schedule_student_ids'][$scheduleId] ?? [], true);
                }

                $assessmentId = trim((string) ($score['Assessment_ID'] ?? $score['Assignment_ID'] ?? ''));

                return $assessmentId !== '' && in_array($assessmentId, $resolvedScope['assessment_ids'] ?? [], true);
            }
        );
        $scoreService->shouldReceive('parseEvaluationDetails')->zeroOrMoreTimes()->andReturnUsing(
            fn (array $score) => json_decode((string) ($score['Evaluation_Details'] ?? ''), true) ?: []
        );
        $this->app->instance(ScoreService::class, $scoreService);

        $config = Mockery::mock(AssessmentConfigService::class);
        $config->shouldReceive('getActiveCategories')->zeroOrMoreTimes()->andReturn([
            ['Category_ID' => 'UJIAN_BAB', 'Category_Name' => 'Ujian Bab'],
            ['Category_ID' => 'LANGUAGE', 'Category_Name' => 'Kemampuan Bahasa'],
        ]);
        $config->shouldReceive('categoryLabel')->zeroOrMoreTimes()->andReturnUsing(fn ($id) => match ($id) {
            'UJIAN_BAB' => 'Ujian Bab', 'LANGUAGE' => 'Kemampuan Bahasa', default => (string) $id,
        });
        $this->app->instance(AssessmentConfigService::class, $config);

        $this->app->instance(AssessmentRepositoryInterface::class, $this->mockService(AssessmentRepositoryInterface::class, [
            'getAll' => collect([
                ['Assessment_ID' => 'ASM-A', 'Title' => 'Ujian Bab 5'],
                ['Assessment_ID' => 'ASM-A-OLD', 'Title' => 'Percakapan Lama'],
            ]),
        ]));
        $this->app->instance(AttendanceService::class, Mockery::mock(AttendanceService::class));
        $this->app->instance(AttendanceRequestService::class, Mockery::mock(AttendanceRequestService::class));
        $this->app->instance(BatchService::class, Mockery::mock(BatchService::class));
        $scopeResolver = Mockery::mock(TeacherScopeResolver::class);
        $scopeResolver->shouldReceive('resolveForUser')->zeroOrMoreTimes()->andReturn($scope);
        $scopeResolver->shouldReceive('resolveForTeacherId')->zeroOrMoreTimes()->with('TCH-A')->andReturn($scope);
        $this->app->instance(TeacherScopeResolver::class, $scopeResolver);
        $this->app->instance(SystemSettingService::class, $this->mockService(SystemSettingService::class, [
            'getThemeTokens' => [],
            'getCompanyProfile' => [],
        ]));
        $this->app->instance(NotificationService::class, $this->mockService(NotificationService::class, [
            'summarizeForUser' => ['unreadCount' => 0, 'recent' => collect()],
        ]));

        $this->actingAs(new GenericUser([
            'id' => 'USR-TCH-A', 'User_ID' => 'USR-TCH-A', 'Role_ID' => 'ROLE-TEACHER-H861',
            'Profile_Photo' => 'https://example.invalid/avatar.png',
        ]));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    public function test_score_page_exposes_explicit_authorized_report_workflow(): void
    {
        $this->get(route('teacher.workspace.scores'))
            ->assertOk()
            ->assertSee('name="mode"', false)
            ->assertSee('name="student_id"', false)
            ->assertSee('name="class_id"', false)
            ->assertSee('name="date"', false)
            ->assertSee('Aiko Tanaka')
            ->assertSee('Kelas Sakura')
            ->assertDontSee('Siswa Asing')
            ->assertDontSee('Kelas Asing');
    }

    public function test_penilaian_switches_authorized_classes_without_cross_class_score_rows(): void
    {
        $this->get(route('teacher.workspace.scores', ['class' => 'CLS-A']))
            ->assertOk()
            ->assertSee('Kelas Sakura')
            ->assertSee('SCR-A-NOW')
            ->assertDontSee('SCR-B-NOW');

        $this->get(route('teacher.workspace.scores', ['class' => 'CLS-B']))
            ->assertOk()
            ->assertSee('Kelas Fuji')
            ->assertSee('SCR-B-NOW')
            ->assertDontSee('SCR-A-NOW');
    }

    public function test_penilaian_rejects_forged_class_and_preserves_category_semantics(): void
    {
        $this->get(route('teacher.workspace.scores', ['class' => 'CLS-C']))->assertForbidden();
        $this->get(route('teacher.workspace.scores', ['class' => 'FORGED']))->assertForbidden();

        $this->get(route('teacher.workspace.scores', ['class' => 'CLS-A', 'category' => 'ujian_bab']))
            ->assertOk()
            ->assertSee('Ujian Bab')
            ->assertSee('90')
            ->assertSee('Lihat Detail')
            ->assertSee('Edit')
            ->assertDontSee('SCR-A-OLD');

        $this->get(route('teacher.workspace.scores', ['class' => 'CLS-A', 'category' => 'assessment']))
            ->assertOk()
            ->assertSee('Kemampuan Bahasa')
            ->assertSee('SCR-A-OLD')
            ->assertDontSee('SCR-A-NOW');
    }

    public function test_student_pdf_is_filtered_across_dates_and_has_safe_human_filename(): void
    {
        $response = $this->get(route('teacher.workspace.reports.scores-pdf', ['mode' => 'student', 'student_id' => 'STU-A']));

        $response->assertOk();
        $this->assertStringContainsString('LAPORAN-NILAI-Aiko-Tanaka-20260912.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_class_pdf_requires_a_date_and_has_safe_human_filename(): void
    {
        $this->from(route('teacher.workspace.scores'))
            ->get(route('teacher.workspace.reports.scores-pdf', ['mode' => 'class', 'class_id' => 'CLS-B']))
            ->assertRedirect(route('teacher.workspace.scores'))
            ->assertSessionHasErrors(['date' => 'Tanggal wajib dipilih untuk laporan nilai satu kelas.']);

        $response = $this->get(route('teacher.workspace.reports.scores-pdf', ['mode' => 'class', 'class_id' => 'CLS-B', 'date' => '2026-09-11']));
        $response->assertOk();
        $this->assertStringContainsString('LAPORAN-NILAI-Kelas-Fuji-20260911.pdf', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_student_mode_requires_student_and_rejects_forged_or_foreign_ids(): void
    {
        $this->from(route('teacher.workspace.scores'))
            ->get(route('teacher.workspace.reports.scores-pdf', ['mode' => 'student']))
            ->assertSessionHasErrors(['student_id' => 'Siswa wajib dipilih untuk laporan nilai per siswa.']);

        $this->get(route('teacher.workspace.reports.scores-pdf', ['mode' => 'student', 'student_id' => 'STU-FORGED']))->assertForbidden();
        $this->get(route('teacher.workspace.reports.scores-pdf', ['mode' => 'student', 'student_id' => 'STU-F']))->assertForbidden();
    }

    public function test_class_mode_rejects_forged_and_other_teacher_classes(): void
    {
        $query = ['mode' => 'class', 'date' => '2026-09-11'];
        $this->get(route('teacher.workspace.reports.scores-pdf', $query + ['class_id' => 'CLS-FORGED']))->assertForbidden();
        $this->get(route('teacher.workspace.reports.scores-pdf', $query + ['class_id' => 'CLS-C']))->assertForbidden();
    }

    public function test_unknown_mode_and_cross_mode_parameters_fail_closed(): void
    {
        $this->from(route('teacher.workspace.scores'))
            ->get(route('teacher.workspace.reports.scores-pdf', ['mode' => 'all']))
            ->assertSessionHasErrors(['mode' => 'Jenis laporan tidak valid.']);

        $this->from(route('teacher.workspace.scores'))
            ->get(route('teacher.workspace.reports.scores-pdf', ['mode' => 'student', 'student_id' => 'STU-A', 'date' => '2026-09-11']))
            ->assertSessionHasErrors('date');
    }

    public function test_student_print_includes_all_own_dates_and_excludes_other_or_cross_scope_scores(): void
    {
        $this->get(route('teacher.workspace.reports.scores-print', ['mode' => 'student', 'student_id' => 'STU-A']))
            ->assertOk()
            ->assertSee('Aiko Tanaka')
            ->assertSee('Ujian Bab 5')
            ->assertSee('Percakapan Lama')
            ->assertSee('Ujian Bab')
            ->assertSee('Baik')
            ->assertSee('Ruang Lingkup Guru')
            ->assertSee('Kelas Sakura | Bahasa Jepang')
            ->assertDontSee('Latihan Aljabar')
            ->assertDontSee('JANGAN-BOCOR-CROSS')
            ->assertDontSee('JANGAN-BOCOR-TEACHER-ONLY')
            ->assertDontSee('JANGAN-BOCOR-ASING')
            ->assertDontSee('Evaluation_Details');
    }

    public function test_class_print_uses_exact_date_and_shows_roster_member_without_score(): void
    {
        $this->get(route('teacher.workspace.reports.scores-print', ['mode' => 'class', 'class_id' => 'CLS-B', 'date' => '2026-09-11']))
            ->assertOk()
            ->assertSee('Kelas Fuji')
            ->assertSee('11 Sep 2026')
            ->assertSee('Beni Fuji')
            ->assertSee('Citra Fuji')
            ->assertSee('Belum ada nilai')
            ->assertSee('Penjelasan sangat panjang')
            ->assertDontSee('Aiko Tanaka')
            ->assertDontSee('Percakapan Lama');
    }

    public function test_mode_specific_empty_states_are_exact(): void
    {
        $this->get(route('teacher.workspace.reports.scores-print', ['mode' => 'student', 'student_id' => 'STU-EMPTY']))
            ->assertOk()->assertSee('Belum ada data nilai untuk siswa ini.');

        $this->get(route('teacher.workspace.reports.scores-print', ['mode' => 'class', 'class_id' => 'CLS-B', 'date' => '2026-09-09']))
            ->assertOk()->assertSee('Belum ada data nilai pada tanggal yang dipilih.');
    }

    private function mockService(string $class, array $methodReturns)
    {
        $mock = Mockery::mock($class);
        foreach ($methodReturns as $method => $returnValue) {
            $mock->shouldReceive($method)->zeroOrMoreTimes()->andReturn($returnValue);
        }

        return $mock;
    }
}
