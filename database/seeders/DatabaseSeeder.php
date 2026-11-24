<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $users = [
            [
                'name' => 'Admin',
                'email' => '184240014@std.ulbi.ac.id',
                'role' => 'admin',
                'department' => null,
            ],
            [
                'name' => 'Pemohon MTC',
                'email' => 'pemohon.mtc@hse-permit.test',
                'role' => 'pemohon',
                'department' => 'MTC',
            ],
            [
                'name' => 'Pemohon GA',
                'email' => 'pemohon.ga@hse-permit.test',
                'role' => 'pemohon',
                'department' => 'GA',
            ],
            [
                'name' => 'Pemohon PE',
                'email' => 'pemohon.pe@hse-permit.test',
                'role' => 'pemohon',
                'department' => 'PE',
            ],
            [
                'name' => 'Pemohon IT',
                'email' => 'pemohon.it@hse-permit.test',
                'role' => 'pemohon',
                'department' => 'IT',
            ],
            [
                'name' => 'Foreman HSE',
                'email' => 'foreman.hse@hse-permit.test',
                'role' => 'foreman_hse',
                'department' => 'HSE',
            ],
            [
                'name' => 'Spv HSE',
                'email' => 'spv.hse@hse-permit.test',
                'role' => 'spv_hse',
                'department' => 'HSE',
            ],
            [
                'name' => 'Ka Dept GA',
                'email' => 'ka.dept.ga@hse-permit.test',
                'role' => 'ka_dept_ga',
                'department' => 'GA',
            ],
            [
                'name' => 'GM HRGA',
                'email' => 'gm.hrga@hse-permit.test',
                'role' => 'gm',
                'department' => 'GA',
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => 'Password123',
                    'role' => $user['role'],
                    'department' => $user['department'],
                ]
            );
        }
    }
}
