<?php

namespace App\Enums;

/**
 * Where the claim that a workshop offers a service comes from. These are never merged: RAR having
 * authorised a transmission repair and a website advertising one are different statements.
 */
enum WorkshopEvidenceType: string
{
    case RarAuthorization = 'rar_authorization';
    case Osm = 'osm';
    case Website = 'website';
    case Manual = 'manual';
    case Inferred = 'inferred';

    public function label(): string
    {
        return match ($this) {
            self::RarAuthorization => 'autorizat RAR',
            self::Osm => 'etichetat OSM',
            self::Website => 'declarat pe site',
            self::Manual => 'introdus manual',
            self::Inferred => 'dedus',
        };
    }
}
