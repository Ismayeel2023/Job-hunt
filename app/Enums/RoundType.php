<?php

namespace App\Enums;

enum RoundType: string
{
    case Aptitude = 'aptitude';
    case Coding = 'coding';
    case Technical = 'technical';
    case Hr = 'hr';
    case Managerial = 'managerial';
    case GroupDiscussion = 'group_discussion';
}
