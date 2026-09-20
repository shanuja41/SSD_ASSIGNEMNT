<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Tests\TestCase;

class StudentTenantValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_validation_rejects_a_section_from_another_class(): void
    {
        DB::table('schools')->insert([
            'name' => 'Test School',
            'slug' => 'test-school',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('classes')->insert([
            ['school_id' => 1, 'name' => 'Class 1', 'created_at' => now(), 'updated_at' => now()],
            ['school_id' => 1, 'name' => 'Class 2', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('sections')->insert([
            'school_id' => 1,
            'class_id' => 2,
            'name' => 'A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $validator = Validator::make(
            [
                'class_id' => 1,
                'section_id' => 1,
            ],
            [
                'class_id' => [
                    'required',
                    Rule::exists('classes', 'id')->where(fn ($query) => $query->where('school_id', 1)),
                ],
                'section_id' => [
                    'nullable',
                    Rule::exists('sections', 'id')->where(fn ($query) => $query
                        ->where('school_id', 1)
                        ->where('class_id', 1)),
                ],
            ]
        );

        $this->assertFalse(
            $validator->passes(),
            'A section from another class must be rejected.'
        );
    }
}
