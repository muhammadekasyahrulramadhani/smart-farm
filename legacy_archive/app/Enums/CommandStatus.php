<?php

namespace App\Enums;

enum CommandStatus: string
{
    case PENDING = 'PENDING';
    case SENT = 'SENT';
    case ACKNOWLEDGED = 'ACKNOWLEDGED';
    case SUCCESS = 'SUCCESS';
    case FAILED = 'FAILED';
    case TIMEOUT = 'TIMEOUT';
}
