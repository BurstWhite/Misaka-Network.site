<?php

namespace Tests\Feature\Passport;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterRouteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        admin_setting(['stop_register' => 1]);
    }

    public function test_v1_register_endpoint_returns_not_found_when_registration_is_disabled(): void
    {
        $this->postJson('/api/v1/passport/auth/register', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ])->assertNotFound();
    }

    public function test_v2_register_endpoint_returns_not_found_when_registration_is_disabled(): void
    {
        $this->postJson('/api/v2/passport/auth/register', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ])->assertNotFound();
    }
}
