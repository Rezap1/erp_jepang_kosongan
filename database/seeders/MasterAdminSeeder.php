<?php

namespace Database\Seeders;

use App\Interfaces\GoogleSheets\UserRepositoryInterface;
use App\Services\Core\RoleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class MasterAdminSeeder extends Seeder
{
    public function run(UserRepositoryInterface $users, RoleService $roles): void
    {
        $password = (string) env('MASTER_ADMIN_PASSWORD', '');

        if ($password === '') {
            throw new RuntimeException('MASTER_ADMIN_PASSWORD wajib diisi sebelum menjalankan MasterAdminSeeder.');
        }

        $existingByEmail = $users->findByEmail('admin@localhost.test');
        $existingByUsername = collect($users->fetchAll())->first(function (array $user): bool {
            return strcasecmp(trim((string) ($user['Username'] ?? '')), 'masteradmin') === 0;
        });

        if ($existingByEmail || $existingByUsername) {
            $this->command?->warn('Master admin sudah ada; akun dan password lama tidak diubah.');

            return;
        }

        $administratorRole = collect($roles->getAllRoles())->first(function (array $role): bool {
            return strtoupper(trim((string) ($role['Role_Name'] ?? ''))) === 'ADMINISTRATOR'
                && ! in_array(strtoupper(trim((string) ($role['Is_Active'] ?? 'TRUE'))), ['FALSE', '0', 'INACTIVE', 'DISABLED'], true);
        });

        if (! $administratorRole) {
            throw new RuntimeException('Role ADMINISTRATOR aktif tidak ditemukan; seeder tidak membuat role duplikat.');
        }

        $userId = $users->generateNewId('USR', 6);
        $timestamp = now()->toDateTimeString();

        $users->create([
            'User_ID' => $userId,
            'Username' => 'masteradmin',
            'Password' => Hash::make($password),
            'Full_Name' => 'Master Administrator',
            'Email' => 'admin@localhost.test',
            'Employee_ID' => '',
            'Role_ID' => $administratorRole['Role_ID'],
            'Is_Active' => 'TRUE',
            'Last_Login' => '',
            'Failed_Login' => '0',
            'Last_Password_Change' => $timestamp,
            'Created_At' => $timestamp,
            'Updated_At' => $timestamp,
            'Created_By' => 'SYSTEM',
            'Updated_By' => 'SYSTEM',
            'Notes' => 'Akun bootstrap lokal ERP LPK Jepang',
            'Phone_Number' => '',
        ]);

        $this->command?->info("Master admin {$userId} berhasil dibuat tanpa mengubah akun yang sudah ada.");
    }
}
