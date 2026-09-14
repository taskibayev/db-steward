<?php

namespace App\Tests\Support;

use App\Connection\ClientDatabaseConnector;
use App\Connection\ClientDatabaseCredentials;
use App\Connection\ConnectionTestResult;

final class FakeClientDatabaseConnector implements ClientDatabaseConnector
{
    public ?ClientDatabaseCredentials $lastCredentials = null;
    private ConnectionTestResult $result;

    public function __construct()
    {
        $this->result = ConnectionTestResult::success('8.4.7', 'client_app');
    }

    public function setResult(ConnectionTestResult $result): void
    {
        $this->result = $result;
    }

    public function test(ClientDatabaseCredentials $credentials): ConnectionTestResult
    {
        $this->lastCredentials = $credentials;

        return $this->result;
    }
}
