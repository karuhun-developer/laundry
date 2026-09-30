<?php

namespace App\Enums;

enum OrderComplaintStatus: string
{
    case New = 'new';
    case Handling = 'handling';
    case Resolved = 'resolved';
}
