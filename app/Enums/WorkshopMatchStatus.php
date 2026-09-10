<?php

namespace App\Enums;

/** A pair of workshops that may be the same place. */
enum WorkshopMatchStatus: string
{
    case Pending = 'pending';
    case AutoMerged = 'auto_merged';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
}
