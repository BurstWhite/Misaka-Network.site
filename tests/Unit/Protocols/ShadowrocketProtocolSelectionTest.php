<?php

namespace Tests\Unit\Protocols;

use App\Http\Controllers\V1\Client\ClientController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ShadowrocketProtocolSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_shadowrocket_flag_selects_the_shadowrocket_response_format(): void
    {
        $request = Request::create('/s/test?flag=shadowrocket', 'GET');
        $response = app(ClientController::class)->doSubscribe($request, [
            'u' => 0,
            'd' => 0,
            'transfer_enable' => 0,
            'expired_at' => null,
        ], []);

        $decoded = base64_decode($response->getContent(), true);

        $this->assertNotFalse($decoded);
        $this->assertStringStartsWith('STATUS=', $decoded);
        $this->assertStringContainsString('Expires:N/A', $decoded);
    }
}
