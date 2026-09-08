<?php

namespace App\Enums;

/**
 * Why a single feed row, or a whole run, failed. The type is what makes a wall of
 * errors triageable: a transport failure is our problem, a rejected row usually
 * means the supplier changed its export.
 */
enum SupplierSyncErrorType: string
{
    /** Download, authentication or remote-path failure. Aborts the run. */
    case Transport = 'transport';

    /** The feed could not be parsed at all: bad XML, unusable format. */
    case Parse = 'parse';

    /** The row parsed but carries no usable identity, so it was skipped. */
    case Rejected = 'rejected';

    /** Field mapping produced something the importer could not use. */
    case Mapping = 'mapping';

    /** The row was valid but writing it failed: constraint, deadlock, timeout. */
    case Persistence = 'persistence';

    public function label(): string
    {
        return match ($this) {
            self::Transport => 'Transport',
            self::Parse => 'Parsare',
            self::Rejected => 'Rând respins',
            self::Mapping => 'Mapare',
            self::Persistence => 'Scriere',
        };
    }
}
