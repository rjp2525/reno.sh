<?php

namespace App\Enums;

enum ProjectType: string
{
    case PERSONAL = 'personal';
    case PROFESSIONAL = 'professional';
    case OPEN_SOURCE = 'open_source';
    case OTHER = 'other';
    case CASE_STUDY = 'case_study';
}
