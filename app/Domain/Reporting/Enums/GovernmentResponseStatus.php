<?php

namespace App\Domain\Reporting\Enums;

enum GovernmentResponseStatus: string
{
    case Requested = 'requested';
    case Received = 'received';
    case Overdue = 'overdue';
}
