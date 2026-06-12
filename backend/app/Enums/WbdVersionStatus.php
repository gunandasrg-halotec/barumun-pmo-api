<?php

namespace App\Enums;

enum WbdVersionStatus: string
{
    case DRAFT = 'DRAFT';
    case PENDING_DIRECTOR_APPROVAL = 'PENDING_DIRECTOR_APPROVAL';
    case FINAL_APPROVED = 'FINAL_APPROVED';
    case REJECTED = 'REJECTED';
    case SUPERSEDED = 'SUPERSEDED';
}
