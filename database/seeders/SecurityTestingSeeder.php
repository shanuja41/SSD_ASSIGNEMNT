<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * SecurityTestingSeeder
 * ---------------------
 * Creates an isolated, clearly-labelled tenant (J/Vembadi Girls' High School)
 * with its own admin, academic structure and students, for authorised local
 * security testing of multi-tenant isolation / IDOR / broken access control
 * (e.g. via Burp Suite).
 *
 * This seeder is purely additive:
 *  - It does NOT touch Greenfield Academy or any of its existing records.
 *  - It never deletes or mutates data belonging to another school_id.
 *  - It is safe to run more than once (firstOrCreate / updateOrCreate throughout).
 *
 * Run with:
 *   php artisan db:seed --class=SecurityTestingSeeder
 */
class SecurityTestingSeeder extends Seeder
{
    public function run(): void
    {
        $school = $this->resolveVembadiSchool();

        $admin = $this->createSchoolAdmin($school);

        [$class, $section] = $this->createAcademicStructure($school);

        $students = $this->createStudents($school, $class, $section);

        $this->report($school, $admin, $class, $section, $students);
    }

    /**
     * Locate the existing J/Vembadi Girls' High School record.
     * This seeder must never create a duplicate school.
     */
    private function resolveVembadiSchool(): School
    {
        $school = School::withoutGlobalScopes()
            ->where('name', "J/Vembadi Girls' High School")
            ->first();

        if (! $school) {
            $this->command->error(
                "J/Vembadi Girls' High School was not found in the database. " .
                'This seeder expects the school to already exist — aborting without creating a duplicate.'
            );
            abort(1, 'Vembadi school not found.');
        }

        return $school;
    }

    /**
     * Create (or fetch) the test school-admin user scoped to Vembadi.
     */
    private function createSchoolAdmin(School $school): User
    {
        // Role already seeded by RolePermissionSeeder/AdminSeeder; firstOrCreate
        // keeps this seeder standalone-safe without creating duplicate roles.
        $role = Role::firstOrCreate(['name' => 'school-admin', 'guard_name' => 'web']);

        $admin = User::updateOrCreate(
            ['email' => 'vembadi.admin@test.com'],
            [
                'name'      => 'Vembadi Test Admin',
                'school_id' => $school->id,
                'password'  => Hash::make('password'),
                'status'    => 'active',
            ]
        );

        // syncRoles keeps this idempotent (no duplicate role pivot rows on re-run).
        $admin->syncRoles([$role->name]);

        return $admin;
    }

    /**
     * Create the minimal academic structure (year, class, section) that
     * Student requires, scoped strictly to Vembadi's own school_id.
     *
     * @return array{0: SchoolClass, 1: Section}
     */
    private function createAcademicStructure(School $school): array
    {
        AcademicYear::firstOrCreate(
            ['school_id' => $school->id, 'name' => '2026'],
            [
                'start_date' => '2026-01-01',
                'end_date'   => '2026-12-31',
                'is_current' => true,
            ]
        );

        $class = SchoolClass::firstOrCreate(
            ['school_id' => $school->id, 'name' => 'Class 10'],
            ['numeric_name' => 10, 'capacity' => 40]
        );

        $section = Section::firstOrCreate(
            ['school_id' => $school->id, 'class_id' => $class->id, 'name' => 'A'],
            ['capacity' => 40]
        );

        return [$class, $section];
    }

    /**
     * Create 3 clearly-identifiable Vembadi test students, each with its own
     * guardian record, for use as IDOR test targets.
     *
     * @return \Illuminate\Support\Collection<int, Student>
     */
    private function createStudents(School $school, SchoolClass $class, Section $section): \Illuminate\Support\Collection
    {
        $definitions = [
            [
                'admission_no' => 'VEM-IDOR-001',
                'last_name'    => 'Test Student One',
                'roll_no'      => 'VEM-01',
            ],
            [
                'admission_no' => 'VEM-IDOR-002',
                'last_name'    => 'Test Student Two',
                'roll_no'      => 'VEM-02',
            ],
            [
                'admission_no' => 'VEM-IDOR-003',
                'last_name'    => 'Test Student Three',
                'roll_no'      => 'VEM-03',
            ],
        ];

        $students = collect();

        foreach ($definitions as $def) {
            $guardian = Guardian::updateOrCreate(
                ['school_id' => $school->id, 'email' => strtolower(str_replace(' ', '', $def['admission_no'])) . '.guardian@test.com'],
                [
                    'name'       => 'Vembadi Guardian for ' . $def['last_name'],
                    'relation'   => 'Guardian',
                    'phone'      => '077' . str_pad((string) (1000000 + (int) substr($def['admission_no'], -3)), 7, '0', STR_PAD_LEFT),
                    'occupation' => 'Not Specified',
                    'address'    => 'Test Address, Jaffna',
                ]
            );

            $students->push(Student::updateOrCreate(
                ['school_id' => $school->id, 'admission_no' => $def['admission_no']],
                [
                    'class_id'       => $class->id,
                    'section_id'     => $section->id,
                    'guardian_id'    => $guardian->id,
                    'roll_no'        => $def['roll_no'],
                    'first_name'     => 'Vembadi',
                    'last_name'      => $def['last_name'],
                    'gender'         => 'female',
                    'date_of_birth'  => now()->subYears(15)->toDateString(),
                    'nationality'    => 'Sri Lankan',
                    'address'        => 'Test Address, Jaffna',
                    'category'       => 'general',
                    'status'         => 'active',
                    'admission_date' => now()->toDateString(),
                ]
            ));
        }

        return $students;
    }

    private function report(School $school, User $admin, SchoolClass $class, Section $section, \Illuminate\Support\Collection $students): void
    {
        $this->command->info('');
        $this->command->info('=== Security Testing Seed Data (J/Vembadi Girls\' High School) ===');
        $this->command->table(
            ['Record', 'ID', 'School ID', 'Role / Admission No'],
            [
                ['School', $school->id, $school->id, '-'],
                ['Vembadi Admin', $admin->id, $admin->school_id, 'school-admin'],
                ['Academic Class', $class->id, $class->school_id, 'Class 10'],
                ['Section', $section->id, $section->school_id, 'A'],
                ...$students->map(fn (Student $s) => [
                    "Student ({$s->last_name})", $s->id, $s->school_id, $s->admission_no,
                ])->all(),
            ]
        );
    }
}
