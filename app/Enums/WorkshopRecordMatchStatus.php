<?php

namespace App\Enums;

/**
 * The outcome of matching one source record (an ONRC company, an OSM point) against what the
 * registry already holds. Ambiguous is a real answer and stays for a person to settle; it is
 * never resolved by picking the first candidate.
 */
enum WorkshopRecordMatchStatus: string
{
    case Matched = 'matched';
    case Probable = 'probable';
    case Ambiguous = 'ambiguous';
    case Unmatched = 'unmatched';
    case Rejected = 'rejected';
}
