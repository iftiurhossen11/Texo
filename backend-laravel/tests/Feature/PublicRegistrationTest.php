<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_an_employee_account_and_returns_a_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Texo User',
            'email' => 'NewUser@Example.com',
            'password' => 'a-unique-password-123',
            'password_confirmation' => 'a-unique-password-123',
            'role' => 'gm',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.email', 'newuser@example.com')
            ->assertJsonPath('user.role', 'employee')
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']]);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'role' => 'employee',
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_registration_requires_a_unique_email_confirmed_password_and_minimum_length(): void
    {
        User::query()->create([
            'name' => 'Existing User',
            'email' => 'taken@example.com',
            'password' => 'an-existing-password',
            'role' => 'employee',
        ]);

        $this->postJson('/api/register', [
            'name' => 'Texo User',
            'email' => 'TAKEN@example.com',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }
}
