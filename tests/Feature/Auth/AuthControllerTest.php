<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
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
            ->assertStatus(Response::HTTP_CREATED); // user registered

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

        $response = $this->postJson(route('auth.login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $token = $response->json('token');

        $this->assertNotNull($token);

        $this->withToken($token)
                ->postJson(route('auth.logout'))
                ->assertStatus(Response::HTTP_OK);

        auth()->guard('sanctum')->forgetUser();

        $this->assertNull(auth()->user());
    }

    public function test_user_can_verify_email(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->get($verificationUrl)->assertRedirect();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_cannot_varify_email_with_wrong_link(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $wrongUrl = str($verificationUrl)->replace('signature=', 'signature=wrongprefix');

        $this->get($wrongUrl)->assertClientError();

        $this->assertNull($user->refresh()->email_verified_at);
    }
}
