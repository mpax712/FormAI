<?php

namespace App\Domain\QuestionBank\Enums;

enum QuestionType: string
{
    case Essay = 'essay';
    case SingleChoice = 'single_choice';
    case MultipleChoice = 'multiple_choice';
    case Context = 'context';
}
