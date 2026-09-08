<?php

namespace App\Enums;

enum SyncStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Failed = 'failed';

    /**
     * The feed downloaded and parsed, but returned far fewer records than the
     * supplier's own baseline. Imported rows are kept, yet the run is not treated
     * as a healthy full picture: nothing is retired and the supplier's last
     * successful sync timestamp is left untouched.
     */
    case AbortedGuard = 'aborted_guard';

    public function isHealthy(): bool
    {
        return $this === self::Completed;
    }

    public function needsAttention(): bool
    {
        return in_array($this, [self::Failed, self::AbortedGuard, self::CompletedWithErrors], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'În așteptare',
            self::Running => 'Rulează',
            self::Completed => 'Finalizat',
            self::CompletedWithErrors => 'Finalizat cu erori',
            self::Failed => 'Eșuat',
            self::AbortedGuard => 'Oprit de gardă',
        };
    }
}
