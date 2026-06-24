<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_user_can_register(): void
    {
        Notification::fake();

        $this->postJson(route('auth.register'), [
            'email' => $this->faker()->email(),
            'password' => $this->faker()->password(),
            'name' => $this->faker()->name(),
        ])
            ->assertStatus(200); // user registered

        Notification::assertSentTo(
            User::first(),
            VerifyEmail::class
        ); // email sent
    }

    public function test_user_cannot_login_without_verified_email(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
            'password' => 'password'
        ]);

        $this->postJson(route('auth.login'), [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertStatus(403);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create();

        $this->postJson(route('auth.login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(200);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->assertAuthenticatedAs($user);

        $this->postJson(route('auth.logout'))
            ->assertStatus(200);

        $this->assertGuest();
    }
}
