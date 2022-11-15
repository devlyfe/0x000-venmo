<?php

namespace App\Enums;

enum VenmoStatus: string
{
    case DIE = 'DIE';
    case LIVE = 'Live';
    case ERROR = 'Error';
    case UNKNOWN = 'Unknown';
}
