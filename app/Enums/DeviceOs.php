<?php

namespace App\Enums;

enum DeviceOs: string
{
    case Windows = 'windows';
    case Macos = 'macos';
    case Linux = 'linux';
}
