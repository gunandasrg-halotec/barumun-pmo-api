<?php

namespace App\Enums;

enum RoleName: string
{
    case ADMINISTRATOR_SISTEM = 'Administrator Sistem';
    case PROJECT_MANAGER = 'Manajer Kebun';
    case DIREKSI = 'Direksi';
    case FINANCE = 'Finance';
    case ADMIN_PROYEK = 'Admin Proyek';
}
