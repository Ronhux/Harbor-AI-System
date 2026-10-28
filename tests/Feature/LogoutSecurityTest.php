<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LogoutSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_revokes_only_the_current_token_without_policy_acceptance(): void
    {
        $user = User::create(['first_name' => 'Synthetic', 'last_name' => 'Logout', 'email' => Str::uuid().'@example.test', 'user_type' => 'Buyer', 'password_hash' => bcrypt('test-only')]);
        $current = $user->createToken('current');
        $other = $user->createToken('other-device');
        $this->withHeader('Authorization', 'Bearer '.$current->plainTextToken)->postJson('/api/logout')->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $current->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $other->accessToken->id]);
        $this->assertDatabaseCount('policy_acceptances', 0);
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/logout')->assertUnauthorized();
    }
}
