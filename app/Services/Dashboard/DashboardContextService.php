<?php

namespace App\Services\Dashboard;

use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\DepartmentRepositoryInterface;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Interfaces\GoogleSheets\PositionRepositoryInterface;
use App\Interfaces\GoogleSheets\ProgramRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Core\RoleService;
use App\Support\Academic\TeacherIdentityResolver;
use App\Support\Presentation\IndonesianPresentation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class DashboardContextService
{
    public function getContext()
    {
        $now = Carbon::now('Asia/Jakarta');
        $hour = $now->hour;

        if ($hour >= 5 && $hour < 11) {
            $greeting = 'Selamat pagi';
            $greeting_icon = '👋';
        } elseif ($hour >= 11 && $hour < 15) {
            $greeting = 'Selamat siang';
            $greeting_icon = '☀️';
        } elseif ($hour >= 15 && $hour < 18) {
            $greeting = 'Selamat sore';
            $greeting_icon = '🌤️';
        } else {
            $greeting = 'Selamat malam';
            $greeting_icon = '🌙';
        }

        $dateFormatted = IndonesianPresentation::date($now, 'l, j F Y');

        $user = Auth::user();
        if (! $user) {
            return [
                'time' => $now->format('H:i:s'),
                'date' => $dateFormatted,
                'greeting' => $greeting,
                'greeting_icon' => $greeting_icon,
                'timezone' => 'Asia/Jakarta',
                'user_name' => 'Tamu',
                'username' => 'anonim',
                'role' => 'GUEST',
                'user_id' => null,
                'timestamp' => $now->timestamp,
            ];
        }

        $dynamicContext = [
            'time' => $now->format('H:i:s'),
            'date' => $dateFormatted,
            'greeting' => $greeting,
            'greeting_icon' => $greeting_icon,
            'timezone' => 'Asia/Jakarta',
            'timestamp' => $now->timestamp,
        ];

        $userId = $user->User_ID ?? 'unknown';
        $roleId = $user->Role_ID ?? 'none';
        $updatedAt = $user->Updated_At ?? $user->updated_at ?? '';
        $cacheKey = "dashboard_context_user_{$userId}_{$roleId}_".md5((string) $updatedAt);
        $ttl = (int) config('cache.wms.reference', 300);

        $staticContext = Cache::remember($cacheKey, $ttl, function () use ($user) {
            $roleName = 'USER';
            if (isset($user->Role_ID)) {
                $roleService = app(RoleService::class);
                $roleData = $roleService->getRoleById($user->Role_ID);
                $roleName = $roleData['Role_Name'] ?? $user->Role ?? 'USER';
            }

            $context = [
                'user_name' => $user->Full_Name ?? 'Pengguna tidak dikenal',
                'username' => $user->Username ?? 'unknown',
                'role' => strtoupper($roleName),
                'user_id' => $user->User_ID ?? null,
            ];

            // Enrich context based on Role
            if (strcasecmp($context['role'], 'STUDENT') === 0) {
                $studentRepo = app(StudentRepositoryInterface::class);
                $students = $studentRepo->fetchAll();
                $student = collect($students)->firstWhere('User_ID', $context['user_id']);
                if ($student) {
                    $context['student_id'] = $student['Student_ID'] ?? null;
                    $context['enrollment_status'] = $student['Enrollment_Status'] ?? 'Aktif';

                    $batchRepo = app(BatchRepositoryInterface::class);
                    $batch = collect($batchRepo->fetchAll())->firstWhere('Batch_ID', $student['Batch_ID'] ?? '');
                    $context['batch'] = $batch['Batch_Name'] ?? $student['Batch_ID'] ?? null;

                    $programRepo = app(ProgramRepositoryInterface::class);
                    $program = collect($programRepo->fetchAll())->firstWhere('Program_ID', $student['Program_ID'] ?? '');
                    $context['program'] = $program['Program_Name'] ?? $student['Program_ID'] ?? null;

                    $classRepo = app(ClassRepositoryInterface::class);
                    $class = collect($classRepo->fetchAll())->firstWhere('Class_ID', $student['Class_ID'] ?? '');
                    $context['class'] = $class['Class_Name'] ?? $student['Class_ID'] ?? null;
                }
            } elseif (in_array(strtoupper($context['role']), ['HR', 'FINANCE', 'MARKETING', 'ACADEMIC', 'DIRECTOR', 'ADMINISTRATOR'])) {
                $employeeRepo = app(EmployeeRepositoryInterface::class);
                $employees = $employeeRepo->fetchAll();
                $employee = collect($employees)->firstWhere('User_ID', $context['user_id']);
                if ($employee) {
                    $context['employee_id'] = $employee['Employee_ID'] ?? null;

                    $deptRepo = app(DepartmentRepositoryInterface::class);
                    $dept = collect($deptRepo->fetchAll())->firstWhere('Department_ID', $employee['Department_ID'] ?? '');
                    $context['department'] = $dept['Department_Name'] ?? $employee['Department_ID'] ?? null;

                    $posRepo = app(PositionRepositoryInterface::class);
                    $pos = collect($posRepo->fetchAll())->firstWhere('Position_ID', $employee['Position_ID'] ?? '');
                    $context['position'] = $pos['Position_Name'] ?? $employee['Position_ID'] ?? null;
                }
            }

            return $context;
        });

        // Teacher identity is deliberately resolved outside the persistent
        // context cache so master-data relationship changes apply on the next
        // request and stale direct User_ID mappings cannot survive in cache.
        if (strcasecmp((string) ($staticContext['role'] ?? ''), 'TEACHER') === 0) {
            unset($staticContext['teacher_id'], $staticContext['teacher_name']);
            $identity = app(TeacherIdentityResolver::class)->resolveForUser($user);
            if ($identity !== null) {
                $staticContext['teacher_id'] = $identity['teacher_id'];
                $staticContext['teacher_name'] = $identity['teacher']['Full_Name'] ?? $staticContext['user_name'];
            }
        }

        return array_merge($staticContext, $dynamicContext);
    }
}
