<?php

namespace Tests\Unit;

use App\Exceptions\ScheduleConflictException;
use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Http\Requests\UpdateSubjectRequest;
use App\Interfaces\GoogleSheets\AcademicYearRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;
use App\Interfaces\GoogleSheets\SubjectRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Models\User;
use App\Services\Academic\ScheduleService;
use App\Services\Academic\SubjectService;
use App\Services\Core\EnterpriseEventService;
use App\Services\Core\ProgramService;
use App\Support\Academic\AcademicSheetMapper;
use App\Support\Academic\AcademicYearResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Mockery;
use Tests\TestCase;

class AcademicPersistenceIntegrityTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    public function test_production_academic_year_schema_normalizes_to_schedule_contract(): void
    {
        $row = AcademicSheetMapper::normalizeAcademicYearRow([
            'Academic_Year_ID' => 'AY-2026',
            'Name' => '2026/2027',
            'Semester' => 'Ganjil',
            'Is_Active' => 'TRUE',
        ]);

        $this->assertSame('AY-2026', $row['Academic_Year_ID']);
        $this->assertSame('2026/2027', $row['Name']);
        $this->assertSame('Ganjil', $row['Semester']);
        $this->assertSame('TRUE', $row['Is_Active']);
    }

    public function test_current_academic_year_follows_real_world_academic_calendar(): void
    {
        $ganjil = AcademicYearResolver::current(Carbon::parse('2026-09-05 01:13:00', config('app.timezone')));
        $genap = AcademicYearResolver::current(Carbon::parse('2027-02-01 08:00:00', config('app.timezone')));

        $this->assertSame('ACY-2026-2027-GANJIL', $ganjil['Academic_Year_ID']);
        $this->assertSame('2026/2027', $ganjil['Name']);
        $this->assertSame('Ganjil', $ganjil['Semester']);
        $this->assertSame('2026-07-01', $ganjil['Start_Date']);
        $this->assertSame('2027-06-30', $ganjil['End_Date']);

        $this->assertSame('ACY-2026-2027-GENAP', $genap['Academic_Year_ID']);
        $this->assertSame('2026/2027', $genap['Name']);
        $this->assertSame('Genap', $genap['Semester']);
    }

    public function test_schedule_create_rejects_missing_academic_year_master_reference(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-05 01:13:00', config('app.timezone')));
        $this->bindScheduleReferenceRules(academicYear: null);

        $request = $this->makeRequest(StoreScheduleRequest::class, [
            'Class_ID' => 'CLS-001',
            'Subject_ID' => 'SUB-001',
            'Teacher_ID' => 'TCH-001',
            'Academic_Year_ID' => '',
            'Day_Of_Week' => ['Monday'],
            'Start_Time' => '07:30',
            'End_Time' => '17:00',
            'Room' => 'Ruangan 1',
        ], 'POST');

        $validator = $this->preparedValidator($request);

        $this->assertFalse($validator->passes());
        $this->assertSame('Tahun ajaran wajib dipilih.', $validator->errors()->first('Academic_Year_ID'));
    }

    public function test_schedule_update_unchanged_values_preserves_references_room_day_and_times(): void
    {
        $repo = new H839ScheduleMemoryRepository([[
            'Schedule_ID' => 'SCH-001',
            'Class_ID' => 'CLS-001',
            'Subject_ID' => 'SUB-001',
            'Teacher_ID' => 'TCH-001',
            'Academic_Year_ID' => 'AY-001',
            'Day_Of_Week' => 'Monday',
            'Start_Time' => '09:00',
            'End_Time' => '10:00',
            'Room' => 'Ruangan 1',
            'Is_Active' => 'TRUE',
        ]]);

        $service = new ScheduleService($repo);
        $service->update('SCH-001', [
            'Class_ID' => 'CLS-001',
            'Subject_ID' => 'SUB-001',
            'Teacher_ID' => 'TCH-001',
            'Academic_Year_ID' => 'AY-001',
            'Day_Of_Week' => 'Monday',
            'Start_Time' => '09:00:00',
            'End_Time' => '10:00:00',
        ]);

        $fresh = $repo->findById('SCH-001');

        $this->assertSame('CLS-001', $fresh['Class_ID']);
        $this->assertSame('SUB-001', $fresh['Subject_ID']);
        $this->assertSame('TCH-001', $fresh['Teacher_ID']);
        $this->assertSame('AY-001', $fresh['Academic_Year_ID']);
        $this->assertSame('Monday', $fresh['Day_Of_Week']);
        $this->assertSame('09:00', $fresh['Start_Time']);
        $this->assertSame('10:00', $fresh['End_Time']);
        $this->assertSame('Ruangan 1', $fresh['Room']);
    }

    public function test_schedule_room_create_and_update_are_verified_on_fresh_read_back(): void
    {
        $repo = new H839ScheduleMemoryRepository;
        $service = new ScheduleService($repo);

        $service->create([
            'Class_ID' => 'CLS-001',
            'Subject_ID' => 'SUB-001',
            'Teacher_ID' => 'TCH-001',
            'Academic_Year_ID' => 'AY-001',
            'Day_Of_Week' => 'Monday',
            'Start_Time' => '09:00',
            'End_Time' => '10:00',
            'Room' => 'Ruangan 1',
        ]);

        $this->assertSame('Ruangan 1', $repo->findById('SCH000001')['Room']);

        $service->update('SCH000001', [
            'Class_ID' => 'CLS-001',
            'Subject_ID' => 'SUB-001',
            'Teacher_ID' => 'TCH-001',
            'Academic_Year_ID' => 'AY-001',
            'Day_Of_Week' => 'Monday',
            'Start_Time' => '09:00',
            'End_Time' => '10:00',
            'Room' => 'Ruangan 2',
        ]);

        $this->assertSame('Ruangan 2', $repo->findById('SCH000001')['Room']);
    }

    public function test_schedule_update_rolls_back_when_read_back_verification_fails(): void
    {
        $repo = new H839ScheduleMemoryRepository([[
            'Schedule_ID' => 'SCH-ROLLBACK',
            'Class_ID' => 'CLS-001',
            'Subject_ID' => 'SUB-001',
            'Teacher_ID' => 'TCH-001',
            'Academic_Year_ID' => 'AY-001',
            'Day_Of_Week' => 'Monday',
            'Start_Time' => '09:00',
            'End_Time' => '10:00',
            'Room' => 'Ruangan Lama',
            'Is_Active' => 'TRUE',
        ]]);
        $repo->corruptReadBackAfterUpdate = true;

        try {
            (new ScheduleService($repo))->update('SCH-ROLLBACK', [
                'Class_ID' => 'CLS-001',
                'Subject_ID' => 'SUB-001',
                'Teacher_ID' => 'TCH-001',
                'Academic_Year_ID' => 'AY-001',
                'Day_Of_Week' => 'Monday',
                'Start_Time' => '09:00',
                'End_Time' => '10:00',
                'Room' => 'Ruangan Baru',
            ]);
            $this->fail('Verifikasi read-back seharusnya gagal.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('gagal diverifikasi', $e->getMessage());
        }

        $this->assertSame('Ruangan Lama', $repo->rows[0]['Room']);
    }

    public function test_schedule_conflict_normalizes_indonesian_and_english_day_names(): void
    {
        $repo = new H839ScheduleMemoryRepository([[
            'Schedule_ID' => 'SCH-EXISTING',
            'Class_ID' => 'CLS-001',
            'Subject_ID' => 'SUB-001',
            'Teacher_ID' => 'TCH-001',
            'Academic_Year_ID' => 'AY-001',
            'Day_Of_Week' => 'Senin',
            'Start_Time' => '09:00',
            'End_Time' => '10:00',
            'Is_Active' => 'TRUE',
        ]]);

        $this->expectException(ScheduleConflictException::class);
        $this->expectExceptionMessage('Jadwal pengajar bentrok');

        (new ScheduleService($repo))->validateSchedule([
            'Class_ID' => 'CLS-002',
            'Teacher_ID' => 'TCH-001',
            'Academic_Year_ID' => 'AY-001',
            'Day_Of_Week' => 'Monday',
            'Start_Time' => '09:30',
            'End_Time' => '10:30',
        ]);
    }

    public function test_schedule_update_ignores_forged_primary_id(): void
    {
        $repo = new H839ScheduleMemoryRepository([[
            'Schedule_ID' => 'SCH-001',
            'Class_ID' => 'CLS-001',
            'Subject_ID' => 'SUB-001',
            'Teacher_ID' => 'TCH-001',
            'Academic_Year_ID' => 'AY-001',
            'Day_Of_Week' => 'Monday',
            'Start_Time' => '09:00',
            'End_Time' => '10:00',
            'Room' => 'Ruangan 1',
            'Is_Active' => 'TRUE',
        ]]);

        (new ScheduleService($repo))->update('SCH-001', [
            'Schedule_ID' => 'SCH-FORGED',
            'Class_ID' => 'CLS-001',
            'Subject_ID' => 'SUB-001',
            'Teacher_ID' => 'TCH-001',
            'Academic_Year_ID' => 'AY-001',
            'Day_Of_Week' => 'Monday',
            'Start_Time' => '09:00',
            'End_Time' => '10:00',
            'Room' => 'Ruangan 2',
        ]);

        $this->assertNull($repo->findById('SCH-FORGED'));
        $this->assertSame('Ruangan 2', $repo->findById('SCH-001')['Room']);
    }

    public function test_schedule_validation_accepts_valid_academic_year_and_rejects_invalid_one(): void
    {
        $this->bindScheduleReferenceRules(academicYear: ['Academic_Year_ID' => 'AY-001', 'Is_Active' => 'TRUE']);

        $valid = $this->validator($this->makeUpdateRequest(UpdateScheduleRequest::class, [
            'Class_ID' => 'CLS-001',
            'Subject_ID' => 'SUB-001',
            'Teacher_ID' => 'TCH-001',
            'Academic_Year_ID' => 'AY-001',
            'Day_Of_Week' => 'Monday',
            'Start_Time' => '09:00',
            'End_Time' => '10:00',
        ], 'SCH-001'));

        $this->assertTrue($valid->passes(), var_export($valid->errors()->toArray(), true));

        $this->bindScheduleReferenceRules(academicYear: null);
        $invalid = $this->validator($this->makeUpdateRequest(UpdateScheduleRequest::class, [
            'Class_ID' => 'CLS-001',
            'Subject_ID' => 'SUB-001',
            'Teacher_ID' => 'TCH-001',
            'Academic_Year_ID' => 'AY-MISSING',
            'Day_Of_Week' => 'Monday',
            'Start_Time' => '09:00',
            'End_Time' => '10:00',
        ], 'SCH-001'));

        $this->assertFalse($invalid->passes());
        $this->assertSame('Tahun ajaran tidak ditemukan.', $invalid->errors()->first('Academic_Year_ID'));
    }

    public function test_schedule_validation_errors_are_human_readable(): void
    {
        $this->bindScheduleReferenceRules();

        $validator = $this->validator($this->makeUpdateRequest(UpdateScheduleRequest::class, [
            'Class_ID' => '',
            'Subject_ID' => 'SUB-001',
            'Teacher_ID' => 'TCH-001',
            'Academic_Year_ID' => '',
            'Day_Of_Week' => 'Nonday',
            'Start_Time' => '10:00',
            'End_Time' => '09:00',
        ], 'SCH-001'));

        $this->assertFalse($validator->passes());
        $messages = implode(' ', $validator->errors()->all());
        $this->assertStringContainsString('Kelas wajib dipilih.', $messages);
        $this->assertStringContainsString('Tahun ajaran wajib dipilih.', $messages);
        $this->assertStringContainsString('Hari yang dipilih tidak valid.', $messages);
        $this->assertStringContainsString('Waktu selesai harus setelah waktu mulai.', $messages);
        $this->assertStringNotContainsString('academic year id field is required', strtolower($messages));
    }

    public function test_subject_update_unchanged_and_description_only_are_valid(): void
    {
        $repo = $this->subjectRepository();
        $service = $this->subjectService($repo);

        $service->update('SUB-001', [
            'Subject_Code' => 'JP-01',
            'Subject_Name' => 'Bahasa Jepang',
            'Program_ID' => 'PRG-001',
            'Credit' => '3',
            'Duration' => '90',
            'Is_Active' => 'TRUE',
        ]);
        $service->update('SUB-001', [
            'Subject_Code' => 'JP-01',
            'Subject_Name' => 'Bahasa Jepang',
            'Program_ID' => 'PRG-001',
            'Description' => 'Deskripsi baru',
        ]);

        $fresh = $repo->findById('SUB-001');

        $this->assertSame('JP-01', $fresh['Subject_Code']);
        $this->assertSame('PRG-001', $fresh['Program_ID']);
        $this->assertSame('3', $fresh['Credit']);
        $this->assertSame('90', $fresh['Duration']);
        $this->assertSame('TRUE', $fresh['Is_Active']);
        $this->assertSame('Deskripsi baru', $fresh['Description']);
    }

    public function test_subject_sks_duration_status_and_program_are_preserved_or_updated_without_zeroing(): void
    {
        $repo = $this->subjectRepository();
        $service = $this->subjectService($repo);

        $service->update('SUB-001', [
            'Subject_Code' => 'JP-01',
            'Subject_Name' => 'Bahasa Jepang',
            'Program_ID' => 'PRG-001',
            'Credit' => '4',
        ]);

        $this->assertSame('4', $repo->findById('SUB-001')['Credit']);
        $this->assertSame('90', $repo->findById('SUB-001')['Duration']);
        $this->assertSame('TRUE', $repo->findById('SUB-001')['Is_Active']);

        $service->update('SUB-001', [
            'Subject_Code' => 'JP-01',
            'Subject_Name' => 'Bahasa Jepang',
            'Program_ID' => 'PRG-001',
            'Duration' => '120',
        ]);

        $fresh = $repo->findById('SUB-001');
        $this->assertSame('4', $fresh['Credit']);
        $this->assertSame('120', $fresh['Duration']);
        $this->assertSame('PRG-001', $fresh['Program_ID']);
    }

    public function test_subject_create_persists_sks_and_duration_and_duplicate_code_denied(): void
    {
        $repo = $this->subjectRepository();
        $service = $this->subjectService($repo);

        $service->create([
            'Subject_Code' => 'JP-03',
            'Subject_Name' => 'Percakapan',
            'Program_ID' => 'PRG-001',
            'Credit' => '3',
            'Duration' => '90',
        ]);

        $created = $repo->findById('SUB000003');
        $this->assertSame('3', $created['Credit']);
        $this->assertSame('90', $created['Duration']);

        $this->expectExceptionMessage('Kode materi sudah digunakan.');
        $service->update('SUB000003', [
            'Subject_Code' => 'JP-02',
            'Subject_Name' => 'Percakapan',
            'Program_ID' => 'PRG-001',
        ]);
    }

    public function test_subject_validation_errors_are_human_readable_and_status_can_be_omitted(): void
    {
        $programService = Mockery::mock(ProgramService::class);
        $programService->shouldReceive('getProgramById')->with('PRG-001')->andReturn(['Program_ID' => 'PRG-001', 'Is_Active' => 'TRUE']);
        $this->app->instance(ProgramService::class, $programService);

        $valid = $this->validator($this->makeUpdateRequest(UpdateSubjectRequest::class, [
            'Subject_Code' => 'JP-01',
            'Subject_Name' => 'Bahasa Jepang',
            'Program_ID' => 'PRG-001',
            'Credit' => '3',
            'Duration' => '90',
        ], 'SUB-001'));

        $this->assertTrue($valid->passes(), var_export($valid->errors()->toArray(), true));

        $invalid = $this->validator($this->makeUpdateRequest(UpdateSubjectRequest::class, [
            'Subject_Code' => '',
            'Subject_Name' => '',
            'Program_ID' => '',
            'Credit' => 'abc',
            'Duration' => '-1',
        ], 'SUB-001'));

        $this->assertFalse($invalid->passes());
        $messages = implode(' ', $invalid->errors()->all());
        $this->assertStringContainsString('Kode materi wajib diisi.', $messages);
        $this->assertStringContainsString('Program wajib dipilih.', $messages);
        $this->assertStringContainsString('SKS harus berupa angka yang valid.', $messages);
        $this->assertStringContainsString('Durasi harus lebih dari 0 menit.', $messages);
    }

    public function test_display_renders_persisted_room_sks_duration_and_preselected_academic_year(): void
    {
        View::share('errors', new ViewErrorBag);

        $scheduleHtml = view('academic.schedules.index', [
            'schedules' => collect([[
                'Schedule_ID' => 'SCH-001',
                'Class_Name' => 'Kelas A',
                'Subject_Name' => 'Bahasa Jepang',
                'Teacher_Name' => 'Sensei A',
                'Day_Of_Week' => 'Monday',
                'Start_Time' => '09:00',
                'End_Time' => '10:00',
                'Room' => 'Ruangan 1',
            ]]),
            'scheduleGroups' => collect(),
        ])->render();

        $this->assertStringContainsString('Ruangan 1', $scheduleHtml);
        $this->assertStringNotContainsString('No Room', $scheduleHtml);

        $subjectHtml = view('academic.subjects.index', [
            'subjects' => collect([[
                'Subject_ID' => 'SUB-001',
                'Subject_Code' => 'JP-01',
                'Subject_Name' => 'Bahasa Jepang',
                'Program_Name' => 'Reguler',
                'Credit' => '3',
                'Duration' => '90',
                'Is_Active' => 'TRUE',
            ]]),
        ])->render();

        $this->assertStringContainsString('3 SKS', $subjectHtml);
        $this->assertStringContainsString('90 Menit', $subjectHtml);

        $editHtml = view('academic.schedules.edit', [
            'schedule' => [
                'Schedule_ID' => 'SCH-001',
                'Class_ID' => 'CLS-001',
                'Subject_ID' => 'SUB-001',
                'Teacher_ID' => 'TCH-001',
                'Academic_Year_ID' => 'AY-001',
                'Day_Of_Week' => 'Monday',
                'Start_Time' => '09:00:00',
                'End_Time' => '10:00:00',
                'Room' => 'Ruangan 1',
            ],
            'classes' => [['Class_ID' => 'CLS-001', 'Class_Name' => 'Kelas A']],
            'subjects' => [['Subject_ID' => 'SUB-001', 'Subject_Name' => 'Bahasa Jepang', 'Subject_Code' => 'JP-01']],
            'teachers' => [['Teacher_ID' => 'TCH-001', 'Full_Name' => 'Sensei A']],
            'academicYears' => [['Academic_Year_ID' => 'AY-001', 'Name' => '2026/2027', 'Semester' => 'Ganjil']],
            'currentTeacherId' => '',
        ])->render();

        $this->assertStringContainsString('2026/2027 - Ganjil', $editHtml);
        $this->assertStringContainsString('name="Academic_Year_ID" value="AY-001"', $editHtml);
        $this->assertStringContainsString('value="09:00"', $editHtml);
        $this->assertStringContainsString('value="10:00"', $editHtml);

        $automaticAcademicYear = AcademicYearResolver::current(Carbon::parse('2026-09-05 01:13:00', config('app.timezone')));
        $createHtml = view('academic.schedules.create', [
            'classes' => [['Class_ID' => 'CLS-001', 'Class_Name' => 'Kelas A']],
            'subjects' => [['Subject_ID' => 'SUB-001', 'Subject_Name' => 'Bahasa Jepang', 'Subject_Code' => 'JP-01']],
            'teachers' => [['Teacher_ID' => 'TCH-001', 'Full_Name' => 'Sensei A']],
            'academicYears' => [$automaticAcademicYear],
            'currentTeacherId' => '',
        ])->render();

        $this->assertStringContainsString('2026/2027 - Ganjil', $createHtml);
        $this->assertStringContainsString('name="Academic_Year_ID" value="ACY-2026-2027-GANJIL"', $createHtml);
    }

    private function subjectRepository(): H839SubjectMemoryRepository
    {
        return new H839SubjectMemoryRepository([
            [
                'Subject_ID' => 'SUB-001',
                'Subject_Code' => 'JP-01',
                'Subject_Name' => 'Bahasa Jepang',
                'Program_ID' => 'PRG-001',
                'Credit' => '3',
                'Duration' => '90',
                'Description' => 'Awal',
                'Is_Active' => 'TRUE',
            ],
            [
                'Subject_ID' => 'SUB-002',
                'Subject_Code' => 'JP-02',
                'Subject_Name' => 'Kanji',
                'Program_ID' => 'PRG-001',
                'Credit' => '2',
                'Duration' => '60',
                'Is_Active' => 'TRUE',
            ],
        ]);
    }

    private function subjectService(H839SubjectMemoryRepository $repo): SubjectService
    {
        $user = new User;
        $user->User_ID = 'USR-H839';
        Auth::login($user);

        $scheduleRepo = new H839ScheduleMemoryRepository;
        $events = Mockery::mock(EnterpriseEventService::class);
        $events->shouldReceive('dispatch')->zeroOrMoreTimes()->andReturn(true);

        return new SubjectService($repo, $scheduleRepo, $events);
    }

    private function bindScheduleReferenceRules(?array $academicYear = ['Academic_Year_ID' => 'AY-001', 'Is_Active' => 'TRUE']): void
    {
        $classRepo = Mockery::mock(ClassRepositoryInterface::class);
        $classRepo->shouldReceive('findById')->andReturn(['Class_ID' => 'CLS-001', 'Is_Active' => 'TRUE']);
        $this->app->instance(ClassRepositoryInterface::class, $classRepo);

        $subjectRepo = Mockery::mock(SubjectRepositoryInterface::class);
        $subjectRepo->shouldReceive('findById')->andReturn(['Subject_ID' => 'SUB-001', 'Is_Active' => 'TRUE']);
        $this->app->instance(SubjectRepositoryInterface::class, $subjectRepo);

        $teacherRepo = Mockery::mock(TeacherRepositoryInterface::class);
        $teacherRepo->shouldReceive('findById')->andReturn(['Teacher_ID' => 'TCH-001', 'Is_Active' => 'TRUE']);
        $this->app->instance(TeacherRepositoryInterface::class, $teacherRepo);

        $academicYearRepo = Mockery::mock(AcademicYearRepositoryInterface::class);
        $academicYearRepo->shouldReceive('findById')->andReturn($academicYear);
        $this->app->instance(AcademicYearRepositoryInterface::class, $academicYearRepo);
    }

    /**
     * @param  class-string<FormRequest>  $requestClass
     */
    private function makeUpdateRequest(string $requestClass, array $payload, string $id): FormRequest
    {
        $request = $requestClass::create('/test/'.$id, 'PUT', $payload);
        $request->setContainer($this->app);

        $route = new Route(['PUT'], '/test/{id}', []);
        $route->bind($request);
        $route->setParameter('id', $id);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    /**
     * @param  class-string<FormRequest>  $requestClass
     */
    private function makeRequest(string $requestClass, array $payload, string $method): FormRequest
    {
        $request = $requestClass::create('/test', $method, $payload);
        $request->setContainer($this->app);

        return $request;
    }

    private function validator(FormRequest $request): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($request->all(), $request->rules(), $request->messages());
    }

    private function preparedValidator(FormRequest $request): \Illuminate\Contracts\Validation\Validator
    {
        $method = new \ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        return $this->validator($request);
    }
}

final class H839ScheduleMemoryRepository implements ScheduleRepositoryInterface
{
    public array $rows;

    public bool $corruptReadBackAfterUpdate = false;

    private int $updateCount = 0;

    public function __construct(array $rows = [])
    {
        $this->rows = array_values($rows);
    }

    public function fetchAll()
    {
        return collect($this->rows)->map(fn ($row) => AcademicSheetMapper::normalizeScheduleRow((array) $row));
    }

    public function findById(string $id)
    {
        $row = $this->fetchAll()->firstWhere('Schedule_ID', $id);
        if ($row && $this->corruptReadBackAfterUpdate && $this->updateCount > 0) {
            $row['Room'] = 'CORRUPTED';
        }

        return $row;
    }

    public function generateNewId(string $prefix, int $padding = 6): string
    {
        return $prefix.str_pad((string) (count($this->rows) + 1), $padding, '0', STR_PAD_LEFT);
    }

    public function create(array $data)
    {
        $this->rows[] = AcademicSheetMapper::normalizeScheduleRow($data);

        return true;
    }

    public function update(string $id, array $data)
    {
        foreach ($this->rows as $index => $row) {
            if (($row['Schedule_ID'] ?? '') === $id) {
                unset($data['Schedule_ID'], $data['id']);
                $this->rows[$index] = AcademicSheetMapper::normalizeScheduleRow(array_merge($row, $data));
                $this->updateCount++;

                return true;
            }
        }

        throw new \RuntimeException("Jadwal '{$id}' tidak ditemukan.");
    }

    public function softDelete(string $id)
    {
        return $this->update($id, ['Is_Active' => 'FALSE']);
    }

    public function clearCache(): void {}

    public function lockById(string $id)
    {
        return $this->findById($id);
    }

    public function transaction(\Closure $callback)
    {
        $snapshot = $this->rows;
        $updateCount = $this->updateCount;

        try {
            return $callback();
        } catch (\Throwable $e) {
            $this->rows = $snapshot;
            $this->updateCount = $updateCount;
            throw $e;
        }
    }
}

final class H839SubjectMemoryRepository implements SubjectRepositoryInterface
{
    public array $rows;

    public function __construct(array $rows = [])
    {
        $this->rows = array_values($rows);
    }

    public function fetchAll()
    {
        return collect($this->rows)->map(fn ($row) => AcademicSheetMapper::normalizeSubjectRow((array) $row));
    }

    public function findById(string $id)
    {
        return $this->fetchAll()->firstWhere('Subject_ID', $id);
    }

    public function generateNewId(string $prefix, int $padding = 6): string
    {
        return $prefix.str_pad((string) (count($this->rows) + 1), $padding, '0', STR_PAD_LEFT);
    }

    public function create(array $data)
    {
        $this->rows[] = AcademicSheetMapper::normalizeSubjectRow($data);

        return true;
    }

    public function update(string $id, array $data)
    {
        foreach ($this->rows as $index => $row) {
            if (($row['Subject_ID'] ?? '') === $id) {
                unset($data['Subject_ID'], $data['id']);
                $this->rows[$index] = AcademicSheetMapper::normalizeSubjectRow(array_merge($row, $data));

                return true;
            }
        }

        throw new \RuntimeException("Materi '{$id}' tidak ditemukan.");
    }

    public function softDelete(string $id)
    {
        return $this->update($id, ['Is_Active' => 'FALSE']);
    }

    public function clearCache(): void {}
}
