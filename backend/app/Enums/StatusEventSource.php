<?php

namespace App\Enums;

enum StatusEventSource: string
{
    case Manual = 'manual';
    case Gmail = 'gmail';
    case Claude = 'claude';
}
