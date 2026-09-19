<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function testUserCanLoginWithValidCredentials()
    {
        $user = $this->createCustomerUser([
            'email' => 'jane@example.com',
            'password' => 'correct-password',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Authorization');
        $response->assertJsonFragment(['email' => 'jane@example.com']);
    }

    public function testLoginFailsWithWrongPassword()
    {
        $this->createCustomerUser([
            'email' => 'jane@example.com',
            'password' => 'correct-password',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
        $response->assertHeaderMissing('Authorization');
        // the login page shows this text to the user
        $response->assertJsonPath('message', 'Incorrect email or password.');
        $response->assertJsonPath('error', 'login_error');
    }

    public function testLoginFailsForNonexistentUser()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'whatever',
        ]);

        $response->assertStatus(401);
    }

    public function testAuthenticatedUserCanFetchOwnProfile()
    {
        $user = $this->createCustomerUser(['email' => 'jane@example.com']);

        $response = $this->actingAs($user, 'api')->getJson('/api/auth/user');

        $response->assertStatus(200);
        $response->assertJsonFragment(['email' => 'jane@example.com']);
    }

    public function testUnauthenticatedUserCannotFetchProfile()
    {
        $response = $this->getJson('/api/auth/user');

        $response->assertStatus(401);
    }

    public function testUserCanLogout()
    {
        $user = $this->createCustomerUser();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/logout');

        $response->assertStatus(200);
        $this->assertSame(0, $user->tokens()->count());
    }
}
