<?php

namespace App\Job;

enum JobStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case CancelRequested = 'cancel_requested';
    case Cancelled = 'cancelled';
}
