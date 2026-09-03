<?php

namespace App\Domain\QuestionBank\Enums;

enum QuestionType: string
{
    case Essay = 'essay';
    case SingleChoice = 'single_choice';
}
