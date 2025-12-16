<?php

namespace App\Enums;

enum MediaVariationType: string
{
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case IMAGE = 'image';
    case DATA = 'data';
    case DOCUMENT = 'document';
}
