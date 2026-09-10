<?php

namespace App\Enums;

enum WorkshopImportStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::CompletedWithErrors, self::Failed, self::Cancelled], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'în așteptare',
            self::Running => 'în curs',
            self::Completed => 'finalizat',
            self::CompletedWithErrors => 'finalizat cu erori',
            self::Failed => 'eșuat',
            self::Cancelled => 'anulat',
        };
    }
}
