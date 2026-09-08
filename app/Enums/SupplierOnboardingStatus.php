<?php

namespace App\Enums;

/**
 * Commercial lifecycle of a supplier relationship.
 *
 * This is independent of Supplier::$is_active, which only says whether the
 * technical feed may run. A supplier can be Live commercially while its feed is
 * paused, and can sit at Contacted for months with no credentials at all.
 */
enum SupplierOnboardingStatus: string
{
    case NotStarted = 'not_started';
    case Contacted = 'contacted';
    case ApplicationSent = 'application_sent';
    case DocsRequested = 'docs_requested';
    case SampleReceived = 'sample_received';
    case Approved = 'approved';
    case Contracted = 'contracted';
    case Live = 'live';
    case OnHold = 'on_hold';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Necontactat',
            self::Contacted => 'Contactat',
            self::ApplicationSent => 'Cerere trimisă',
            self::DocsRequested => 'Așteptăm documente',
            self::SampleReceived => 'Mostră primită',
            self::Approved => 'Cont aprobat',
            self::Contracted => 'Contractat',
            self::Live => 'Live',
            self::OnHold => 'În așteptare',
            self::Rejected => 'Respins',
        };
    }

    /** Whether an adapter may realistically be built for this supplier. */
    public function allowsIntegrationWork(): bool
    {
        return in_array($this, [self::SampleReceived, self::Approved, self::Contracted, self::Live], true);
    }

    public function isClosed(): bool
    {
        return $this === self::Rejected;
    }
}
