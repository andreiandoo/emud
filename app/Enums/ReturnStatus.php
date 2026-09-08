<?php

namespace App\Enums;

enum ReturnStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Received = 'received';
    case Refunded = 'refunded';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Solicitat',
            self::Approved => 'Aprobat',
            self::Received => 'Primit',
            self::Refunded => 'Rambursat',
            self::Rejected => 'Respins',
            self::Cancelled => 'Anulat',
        };
    }

    public function pillClass(): string
    {
        return match ($this) {
            self::Requested => 'pill-info',
            self::Approved, self::Received => 'pill-warning',
            self::Refunded => 'pill-positive',
            self::Rejected, self::Cancelled => 'pill-danger',
        };
    }

    /** Nothing further happens to a return in these states. */
    public function isClosed(): bool
    {
        return in_array($this, [self::Refunded, self::Rejected, self::Cancelled], true);
    }

    /**
     * Transitions an operator may make from here. Arbitrary jumps are refused so a return
     * cannot be marked refunded without ever having been received.
     *
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Requested => [self::Approved, self::Rejected, self::Cancelled],
            self::Approved => [self::Received, self::Rejected, self::Cancelled],
            self::Received => [self::Refunded, self::Rejected],
            default => [],
        };
    }
}
