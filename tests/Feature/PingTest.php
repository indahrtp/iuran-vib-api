<?php

namespace Tests\Feature;

use Tests\TestCase;

class PingTest extends TestCase
{
    public function test_ping_returns_ok(): void
    {
        $this->getJson('/api/v1/ping')
            ->assertOk()
            ->assertJsonPath('data.status', 'ok');
    }

    public function test_protected_route_requires_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }
}