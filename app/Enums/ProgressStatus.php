<?php

namespace App\Enums;

enum ProgressStatus: string
{
    case DRAFT = 'DRAFT';
    case PENDING_PM_APPROVAL = 'PENDING_PM_APPROVAL';
    case PENDING_DIRECTOR_APPROVAL = 'PENDING_DIRECTOR_APPROVAL';
    case AUTO_APPROVED = 'AUTO_APPROVED';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';

    public function isOfficial(): bool
    {
        return in_array($this, [self::APPROVED, self::AUTO_APPROVED]);
    }
}
