<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class InputValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that invalid phone and email inputs are properly rejected after applying strict validation rules.
     * This proves that CWE-20 (Improper Input Validation) has been remediated.
     */
    public function test_strict_validation_rules_reject_invalid_phone_and_email(): void
    {
        $rules = [
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'email' => 'nullable|email:rfc|max:150',
            'guardian.phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'guardian.email' => 'nullable|email:rfc|max:150',
        ];

        $invalidPayload = [
            'phone' => 'abc!!123#$%',
            'email' => 'notanemail',
            'guardian' => [
                'phone' => 'INVALID_PHONE_NUMBER',
                'email' => 'bademail@',
            ],
        ];

        $validator = Validator::make($invalidPayload, $rules);

        // Assert that validation fails
        $this->assertTrue(
            $validator->fails(),
            'Validation should fail when invalid phone and email inputs are provided.'
        );

        // Assert specific validation error keys
        $errors = $validator->errors()->toArray();
        $this->assertArrayHasKey('phone', $errors, 'Validation error expected for phone.');
        $this->assertArrayHasKey('email', $errors, 'Validation error expected for email.');
        $this->assertArrayHasKey('guardian.phone', $errors, 'Validation error expected for guardian.phone.');
        $this->assertArrayHasKey('guardian.email', $errors, 'Validation error expected for guardian.email.');
    }

    /**
     * Test that valid phone and email formats pass strict validation rules.
     */
    public function test_strict_validation_rules_accept_valid_phone_and_email(): void
    {
        $rules = [
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'email' => 'nullable|email:rfc|max:150',
            'guardian.phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'guardian.email' => 'nullable|email:rfc|max:150',
        ];

        $validPayload = [
            'phone' => '+1 (555) 019-2834',
            'email' => 'student@example.com',
            'guardian' => [
                'phone' => '0761234567',
                'email' => 'guardian@example.com',
            ],
        ];

        $validator = Validator::make($validPayload, $rules);

        $this->assertTrue(
            $validator->passes(),
            'Valid phone numbers and RFC-compliant emails should pass validation.'
        );
    }
}
