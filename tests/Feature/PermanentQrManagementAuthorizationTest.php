<?php

namespace Tests\Feature;

use App\Services\Academic\StudentQRAttendanceService;
use App\Services\Core\ActivityLogService;
use App\Services\Core\PermanentQrService;
use App\Services\Core\RoleService;
use App\Services\Core\SystemSettingService;
use App\Services\HR\QRAttendanceService;
use Illuminate\Auth\GenericUser;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PermanentQrManagementAuthorizationTest extends TestCase
{
    #[DataProvider('unauthorizedRoleProvider')]
    public function test_non_privileged_roles_cannot_access_or_forge_qr_management(
        string $roleId,
        string $roleName
    ): void {
        $this->bindControllerDependencies($roleId, $roleName);
        $this->actingAs($this->user("USR-{$roleName}", $roleId));

        $this->get('/attendance/qr')->assertForbidden();
        $this->patch('/attendance/qr/QR00001', $this->validEditPayload())->assertForbidden();
        $this->post('/attendance/qr/student-settings', [
            'WORK_START_TIME' => '06:30',
            'WORK_END_TIME' => '09:00',
        ])->assertForbidden();
    }

    public static function unauthorizedRoleProvider(): array
    {
        return [
            'student' => ['ROLE-STUDENT', 'STUDENT'],
            'teacher' => ['ROLE-TEACHER', 'TEACHER'],
            'hr' => ['ROLE-HR', 'HR'],
            'finance' => ['ROLE-FINANCE', 'FINANCE'],
            'marketing' => ['ROLE-MARKETING', 'MARKETING'],
            'academic' => ['ROLE-ACADEMIC', 'ACADEMIC'],
        ];
    }

    #[DataProvider('privilegedRoleProvider')]
    public function test_master_and_administrator_can_edit_qr_without_rotating_identifier(
        string $roleId,
        string $roleName
    ): void {
        [$qrService] = $this->bindControllerDependencies($roleId, $roleName);
        $qrService->shouldReceive('getQrById')->once()->with('QR00001')->andReturn([
            'QR_ID' => 'QR00001',
            'QR_TYPE' => 'STUDENT',
            'IDENTIFIER' => 'WMS-ATT-STU-UNCHANGED',
        ]);
        $qrService->shouldReceive('updateAvailability')
            ->once()
            ->with('QR00001', [
                'STATUS' => 'ACTIVE',
                'ACTIVE_FROM' => '2026-10-01 06:15:00',
                'ACTIVE_UNTIL' => '2027-10-01 20:45:00',
            ]);
        $this->actingAs($this->user("USR-{$roleName}", $roleId));

        $this->patch('/attendance/qr/QR00001', $this->validEditPayload())
            ->assertRedirect(route('attendance.qr.index'));
    }

    #[DataProvider('privilegedRoleProvider')]
    public function test_master_and_administrator_can_update_student_attendance_window(
        string $roleId,
        string $roleName
    ): void {
        [, $settings] = $this->bindControllerDependencies($roleId, $roleName);
        $settings->shouldReceive('prepareSettingsForUpdate')->once()->andReturn([
            ['WORK_START_TIME' => '06:30', 'WORK_END_TIME' => '09:00'],
            [],
        ]);
        $settings->shouldReceive('set')->once()->with('STUDENT_ATTENDANCE_START_TIME', '06:30', "USR-{$roleName}");
        $settings->shouldReceive('set')->once()->with('STUDENT_ATTENDANCE_END_TIME', '09:00', "USR-{$roleName}");
        $settings->shouldReceive('reloadCache')->once();
        $this->actingAs($this->user("USR-{$roleName}", $roleId));

        $this->post('/attendance/qr/student-settings', [
            'WORK_START_TIME' => '06:30',
            'WORK_END_TIME' => '09:00',
        ])
            ->assertRedirect(route('attendance.qr.index'));
    }

    public static function privilegedRoleProvider(): array
    {
        return [
            'administrator' => ['ROLE-ADMIN', 'ADMINISTRATOR'],
            'master' => ['ROLE-MASTER', 'MASTER'],
        ];
    }

    public function test_invalid_student_attendance_window_is_rejected_without_any_setting_write(): void
    {
        [, $settings] = $this->bindControllerDependencies('ROLE-MASTER', 'MASTER');
        $settings->shouldReceive('prepareSettingsForUpdate')->once()->andReturn([
            ['WORK_START_TIME' => '09:00', 'WORK_END_TIME' => '06:30'],
            ['Jam selesai absensi harus setelah jam mulai pada hari yang sama.'],
        ]);
        $settings->shouldNotReceive('set');
        $settings->shouldNotReceive('reloadCache');
        $this->actingAs($this->user('USR-MASTER', 'ROLE-MASTER'));

        $this->from('/attendance/qr')->post('/attendance/qr/student-settings', [
            'WORK_START_TIME' => '09:00',
            'WORK_END_TIME' => '06:30',
        ])
            ->assertRedirect('/attendance/qr')
            ->assertSessionHasErrors('attendance_settings');
    }

    private function bindControllerDependencies(string $roleId, string $roleName): array
    {
        $roleService = Mockery::mock(RoleService::class);
        $roleService->shouldReceive('getRoleById')->with($roleId)->andReturn([
            'Role_ID' => $roleId,
            'Role_Name' => $roleName,
            'Is_Active' => 'TRUE',
        ]);
        $this->app->instance(RoleService::class, $roleService);

        $qrService = Mockery::mock(PermanentQrService::class);
        $settingService = Mockery::mock(SystemSettingService::class);
        $this->app->instance(PermanentQrService::class, $qrService);
        $this->app->instance(SystemSettingService::class, $settingService);
        $this->app->instance(StudentQRAttendanceService::class, Mockery::mock(StudentQRAttendanceService::class));
        $this->app->instance(QRAttendanceService::class, Mockery::mock(QRAttendanceService::class));
        $this->app->instance(ActivityLogService::class, Mockery::mock(ActivityLogService::class));

        return [$qrService, $settingService];
    }

    private function validEditPayload(): array
    {
        return [
            'STATUS' => 'ACTIVE',
            'ACTIVE_FROM_DATE' => '2026-10-01',
            'ACTIVE_FROM_TIME' => '06:15',
            'ACTIVE_UNTIL_DATE' => '2027-10-01',
            'ACTIVE_UNTIL_TIME' => '20:45',
            'IDENTIFIER' => 'FORGED-ROTATION',
            'QR_TYPE' => 'EMPLOYEE',
        ];
    }

    private function user(string $userId, string $roleId): GenericUser
    {
        return new GenericUser(['id' => $userId, 'User_ID' => $userId, 'Role_ID' => $roleId]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
