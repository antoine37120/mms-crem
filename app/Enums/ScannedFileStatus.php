<?php

namespace App\Enums;

enum ScannedFileStatus: string
{
    case ORPHAN = 'orphan';
    case ASSOCIATED = 'associated';
}
