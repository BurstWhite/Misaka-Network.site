<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\AuthService;
use App\Utils\Helper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_token_records_and_returns_real_client_details(): void
    {
        $user = User::query()->create([
            'email' => 'session@example.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'uuid' => Helper::guid(true),
            'token' => Helper::guid(true),
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        $request = Request::create('/api/v1/passport/auth/login', 'POST', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.42',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Version/18.5 Safari/605.1.15',
        ]);

        (new AuthService($user))->generateAuthData($request);
        $token = $user->tokens()->firstOrFail();
        $legacyToken = $user->createToken('legacy-session')->accessToken;
        $duplicateToken = $user->createToken('duplicate-session')->accessToken;
        $duplicateToken->forceFill([
            'ip_address' => '203.0.113.42',
            'user_agent' => 'Mozilla/5.0 (Macintosh) AppleWebKit/537.36 Chrome/126.0 Safari/537.36',
        ])->save();
        $user->withAccessToken($token);
        $sessions = (new AuthService($user))->getSessions($request);

        $this->assertSame('203.0.113.42', $token->ip_address);
        $this->assertFalse($user->tokens()->whereKey($legacyToken->id)->exists());
        $this->assertTrue($user->tokens()->whereKey($duplicateToken->id)->exists());
        $this->assertCount(1, $sessions);
        $this->assertSame('Safari · macOS', $sessions[0]['device']);
        $this->assertSame('203.0.113.42', $sessions[0]['ip']);
        $this->assertTrue($sessions[0]['current']);
        $this->assertArrayNotHasKey('token', $sessions[0]);
    }
}
