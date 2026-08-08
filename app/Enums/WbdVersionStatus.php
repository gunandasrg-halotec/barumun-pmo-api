<?php

namespace App\Enums;

enum WbdVersionStatus: string
{
    case DRAFT = 'DRAFT';
    case PENDING_DIRECTOR_APPROVAL = 'PENDING_DIRECTOR_APPROVAL';
    case FINAL_APPROVED = 'FINAL_APPROVED';
    case REJECTED = 'REJECTED';
    case SUPERSEDED = 'SUPERSEDED';
    // Terminal state khusus revisi baseline in-place: perubahannya sudah diputuskan Direksi
    // (minimal 1 item disetujui) dan diterapkan ke baseline — bukan versi aktif tersendiri.
    case MERGED = 'MERGED';
}
