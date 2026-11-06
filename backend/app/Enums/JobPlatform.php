<?php

namespace App\Enums;

enum JobPlatform: string
{
    case LinkedIn = 'linkedin';
    case Naukri = 'naukri';
    case Indeed = 'indeed';
    case Instahyre = 'instahyre';
    case Wellfound = 'wellfound';
    case Foundit = 'foundit';
    case CareerPage = 'career_page';
    case Other = 'other';
}
