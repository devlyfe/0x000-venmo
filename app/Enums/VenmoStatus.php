<?php

namespace App\Enums;

enum VenmoStatus: string
{
    case DIE = 'DIE';
    case LIVE = 'Live';
    case ERROR = 'ERROR';
    case UNKNOWN = 'UNKNOWN';
}
