<?php

namespace App\Enums;

enum MediaVariationStatus: string
{
    case READY = 'ready';
    case PROCESSING = 'processing';
    case FAILED = 'failed';
}
