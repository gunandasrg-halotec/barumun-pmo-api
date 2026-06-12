<?php

namespace App\Enums;

enum CostStatus: string
{
    case DRAFT = 'DRAFT';
    case REVIEW = 'REVIEW';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
}
