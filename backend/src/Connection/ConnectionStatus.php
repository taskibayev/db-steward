<?php

namespace App\Connection;

enum ConnectionStatus: string
{
    case Untested = 'untested';
    case Reachable = 'reachable';
    case Unreachable = 'unreachable';
}
