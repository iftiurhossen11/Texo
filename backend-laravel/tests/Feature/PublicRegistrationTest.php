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

    public function test_configured_owner_email_requires_setup_code_to_register_as_owner(): void
    {
        config([
            'services.owner_emails' => ['owner@example.com'],
            'services.owner_setup_code' => 'a-strong-owner-setup-code',
        ]);

        $this->postJson('/api/register', [
            'name' => 'Texo Owner',
            'email' => 'Owner@Example.com',
            'password' => 'a-unique-password-123',
            'password_confirmation' => 'a-unique-password-123',
            'role' => 'admin',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['owner_setup_code']);

        $this->postJson('/api/register', [
            'name' => 'Texo Owner',
            'email' => 'Owner@Example.com',
            'password' => 'a-unique-password-123',
            'password_confirmation' => 'a-unique-password-123',
            'role' => 'admin',
            'owner_setup_code' => 'a-strong-owner-setup-code',
        ])->assertCreated()
            ->assertJsonPath('user.role', 'owner');
    }

    public function test_existing_configured_owner_account_requires_setup_code_to_be_promoted_on_login(): void
    {
        config([
            'services.owner_emails' => ['owner@example.com'],
            'services.owner_setup_code' => 'a-strong-owner-setup-code',
        ]);
        User::query()->create([
            'name' => 'Texo Owner',
            'email' => 'owner@example.com',
            'password' => 'a-unique-password-123',
            'role' => 'employee',
        ]);

        $this->postJson('/api/login', [
            'email' => 'owner@example.com',
            'password' => 'a-unique-password-123',
        ])->assertOk()
            ->assertJsonPath('user.role', 'employee');

        $this->postJson('/api/login', [
            'email' => 'owner@example.com',
            'password' => 'a-unique-password-123',
            'owner_setup_code' => 'a-strong-owner-setup-code',
        ])->assertOk()
            ->assertJsonPath('user.role', 'owner');

        $employee = User::query()->create([
            'name' => 'Regular User',
            'email' => 'employee@example.com',
            'password' => 'a-unique-password-123',
            'role' => 'employee',
        ]);
        $this->postJson('/api/login', [
            'email' => $employee->email,
            'password' => 'a-unique-password-123',
            'owner_setup_code' => 'a-strong-owner-setup-code',
        ])->assertOk()
            ->assertJsonPath('user.role', 'employee');
    }
}
