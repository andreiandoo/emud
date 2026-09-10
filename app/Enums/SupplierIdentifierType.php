<?php

namespace App\Enums;

/**
 * Kinds of identity a supplier article can carry, strongest first.
 *
 * EAN, UPC and GTIN-14 are one type here: they are the same number system, a UPC-A
 * is an EAN-13 with a leading zero, and treating them separately is exactly how two
 * feeds describing the same box end up unmatched.
 */
enum SupplierIdentifierType: string
{
    case TecDocArticle = 'TECDOC_ARTICLE';
    case Gtin = 'GTIN';
    case Mpn = 'MPN';
    case Oe = 'OE';
    case Iam = 'IAM';
    case CrossReference = 'CROSS_REFERENCE';
    case Supersession = 'SUPERSESSION';
    case SupplierSku = 'SUPPLIER_SKU';

    /**
     * Schemes the canonical catalogue stores the same kind of number under.
     *
     * @return list<string>
     */
    public function catalogSchemes(): array
    {
        return match ($this) {
            self::TecDocArticle => ['TECDOC', 'TECDOC_ARTICLE'],
            self::Gtin => ['EAN', 'EAN_GTIN', 'GTIN', 'UPC'],
            self::Mpn => ['MPN'],
            self::Oe => ['OE', 'OEM'],
            self::Iam, self::CrossReference => ['IAM', 'OE', 'OEM'],
            self::Supersession => ['MPN', 'IAM'],
            self::SupplierSku => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::TecDocArticle => 'TecDoc',
            self::Gtin => 'EAN/GTIN',
            self::Mpn => 'MPN',
            self::Oe => 'OE',
            self::Iam => 'IAM',
            self::CrossReference => 'Referință',
            self::Supersession => 'Înlocuit de',
            self::SupplierSku => 'SKU furnizor',
        };
    }
}
