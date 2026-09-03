<?php

namespace App\Domain\Submissions\Enums;

enum SubmissionStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Processing = 'processing';
    case Reviewed = 'reviewed';
    case Released = 'released';
}
