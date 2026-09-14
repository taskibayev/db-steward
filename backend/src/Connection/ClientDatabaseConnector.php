<?php

namespace App\Connection;

interface ClientDatabaseConnector
{
    public function test(ClientDatabaseCredentials $credentials): ConnectionTestResult;
}
