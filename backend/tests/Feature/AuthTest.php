<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\WelcomeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_as_player_and_receives_welcome_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', [
            'username' => 'speedy_runner',
            'email' => 'speedy@example.com',
            'password' => 'secretPassword123',
            'role' => 'player',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user.username', 'speedy_runner')
            ->assertJsonPath('user.role', 'player')
            ->assertJsonStructure(['token']);

        $this->assertDatabaseHas('users', ['email' => 'speedy@example.com']);

        Notification::assertSentTo(
            User::where('email', 'speedy@example.com')->first(),
            WelcomeNotification::class
        );
    }

    public function test_user_can_login_with_username_or_email(): void
    {
        $user = User::factory()->create([
            'username' => 'testgamer',
            'email' => 'gamer@test.com',
            'password' => Hash::make('mypassword123'),
        ]);

        // Login with username
        $res1 = $this->postJson('/api/auth/login', [
            'login' => 'testgamer',
            'password' => 'mypassword123',
        ]);
        $res1->assertStatus(200)->assertJsonStructure(['token']);

        // Login with email
        $res2 = $this->postJson('/api/auth/login', [
            'login' => 'gamer@test.com',
            'password' => 'mypassword123',
        ]);
        $res2->assertStatus(200)->assertJsonStructure(['token']);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct_pass')]);

        $response = $this->postJson('/api/auth/login', [
            'login' => $user->email,
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['login']);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/auth/logout');

        $response->assertStatus(200);
        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_user_can_request_password_reset_and_reset_password(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'recover@example.com']);

        // 1. Request reset link
        $res = $this->postJson('/api/auth/forgot-password', ['email' => 'recover@example.com']);
        $res->assertStatus(200);

        Notification::assertSentTo($user, ResetPasswordNotification::class);

        // 2. Generate a token to test the reset endpoint
        $token = Password::broker()->createToken($user);

        // 3. Reset password
        $resetRes = $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'recover@example.com',
            'password' => 'BrandNewPassword123!',
            'password_confirmation' => 'BrandNewPassword123!',
        ]);

        $resetRes->assertStatus(200);
        $this->assertTrue(Hash::check('BrandNewPassword123!', $user->fresh()->password));
    }
}