<?php

namespace Tests\Unit\Support;

use App\Support\Broadcasting\ReverbClientConfig;
use Illuminate\Http\Request;
use Tests\TestCase;

class ReverbClientConfigTest extends TestCase
{
    public function test_loopback_page_uses_loopback_reverb_host(): void
    {
        config(['broadcasting.connections.reverb.client.host' => '192.168.0.28']);

        $this->app->instance('request', Request::create('http://127.0.0.1:8000/tournaments/1'));

        $this->assertSame('127.0.0.1', ReverbClientConfig::forWeb()['host']);
    }

    public function test_lan_page_keeps_configured_reverb_host(): void
    {
        config(['broadcasting.connections.reverb.client.host' => '192.168.0.28']);

        $this->app->instance('request', Request::create('http://192.168.0.28:8000/tournaments/1'));

        $this->assertSame('192.168.0.28', ReverbClientConfig::forWeb()['host']);
    }
}
