<?php

namespace App\Domain\Activities\Enums;

enum ActivityStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';
    case Grading = 'grading';
    case ReviewReady = 'review_ready';
    case Released = 'released';
}
