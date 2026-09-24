<?php

namespace Tests\Feature;

use App\Models\LessonPlan;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LessonPlanAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private SchoolClass $class;
    private Subject $subject;
    private LessonPlan $lessonPlan;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'school-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'principal', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $this->school = School::create([
            'name'     => 'Test School',
            'slug'     => 'test-school',
            'status'   => 'active',
            'timezone' => 'UTC',
        ]);

        $this->class = SchoolClass::create([
            'school_id' => $this->school->id,
            'name'      => 'Class 10',
        ]);

        $this->subject = Subject::create([
            'school_id' => $this->school->id,
            'class_id'  => $this->class->id,
            'name'      => 'Mathematics',
            'code'      => 'MATH101',
        ]);

        $this->lessonPlan = LessonPlan::create([
            'school_id'  => $this->school->id,
            'class_id'   => $this->class->id,
            'subject_id' => $this->subject->id,
            'title'      => 'Quadratic Equations',
            'week_start' => now()->toDateString(),
            'status'     => 'submitted',
        ]);
    }

    public function test_teacher_cannot_review_lesson_plan(): void
    {
        $teacher = User::factory()->create([
            'school_id' => $this->school->id,
        ]);
        $teacher->assignRole('teacher');

        $response = $this->actingAs($teacher)->put(
            route('school.homework.lesson-plans.review', $this->lessonPlan),
            ['action' => 'approved', 'reviewer_feedback' => 'Teacher trying to self-approve']
        );

        $response->assertStatus(403);
        $this->assertEquals('submitted', $this->lessonPlan->fresh()->status);
    }

    public function test_principal_can_review_lesson_plan(): void
    {
        $principal = User::factory()->create([
            'school_id' => $this->school->id,
        ]);
        $principal->assignRole('principal');

        $response = $this->actingAs($principal)->put(
            route('school.homework.lesson-plans.review', $this->lessonPlan),
            ['action' => 'approved', 'reviewer_feedback' => 'Approved by principal']
        );

        $response->assertSessionHas('success');
        $this->assertEquals('approved', $this->lessonPlan->fresh()->status);
        $this->assertEquals($principal->id, $this->lessonPlan->fresh()->reviewed_by);
    }

    public function test_cross_tenant_review_is_forbidden(): void
    {
        $otherSchool = School::create([
            'name'     => 'Other School',
            'slug'     => 'other-school',
            'status'   => 'active',
            'timezone' => 'UTC',
        ]);

        $otherAdmin = User::factory()->create([
            'school_id' => $otherSchool->id,
        ]);
        $otherAdmin->assignRole('school-admin');

        $response = $this->actingAs($otherAdmin)->put(
            route('school.homework.lesson-plans.review', $this->lessonPlan),
            ['action' => 'approved']
        );

        $this->assertTrue(
            in_array($response->getStatusCode(), [403, 404]),
            'Cross-tenant access must be rejected with 403 or 404'
        );
    }
}
