<?php

namespace App\Enums;

/**
 * The maintenance a Romanian owner actually tracks. Legal deadlines and wear items are both
 * here because an owner does not separate them: what matters is what falls due next.
 */
enum ServiceReminderType: string
{
    case Itp = 'itp';
    case Rar = 'rar';
    case Rca = 'rca';
    case Rovinieta = 'rovinieta';
    case OilAndFilter = 'oil_and_filter';
    case AirFilter = 'air_filter';
    case CabinFilter = 'cabin_filter';
    case FuelFilter = 'fuel_filter';
    case TimingBelt = 'timing_belt';
    case BrakeFluid = 'brake_fluid';
    case Coolant = 'coolant';
    case GearboxOil = 'gearbox_oil';
    case DifferentialOil = 'differential_oil';
    case Brakes = 'brakes';
    case Tyres = 'tyres';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Itp => 'ITP',
            self::Rar => 'Verificare RAR',
            self::Rca => 'Asigurare RCA',
            self::Rovinieta => 'Rovinietă',
            self::OilAndFilter => 'Ulei și filtru de ulei',
            self::AirFilter => 'Filtru de aer',
            self::CabinFilter => 'Filtru de habitaclu',
            self::FuelFilter => 'Filtru de combustibil',
            self::TimingBelt => 'Curea de distribuție',
            self::BrakeFluid => 'Lichid de frână',
            self::Coolant => 'Antigel',
            self::GearboxOil => 'Ulei cutie',
            self::DifferentialOil => 'Ulei diferențial',
            self::Brakes => 'Frâne',
            self::Tyres => 'Anvelope',
            self::Other => 'Altele',
        };
    }

    /** Whether the deadline is a legal one, which changes how firmly it is presented. */
    public function isLegal(): bool
    {
        return in_array($this, [self::Itp, self::Rar, self::Rca, self::Rovinieta], true);
    }

    /**
     * The deadlines every owner has, offered one click away on each car rather than hidden in a
     * dropdown of fifteen: the legal ones, and the oil change everyone forgets.
     *
     * @return list<self>
     */
    public static function essentials(): array
    {
        return [self::Itp, self::Rar, self::Rovinieta, self::Rca, self::OilAndFilter];
    }

    /** Months and kilometres a typical interval runs, used to propose the next due point. */
    public function defaultIntervalMonths(): ?int
    {
        return match ($this) {
            self::Itp, self::Rca, self::Rovinieta => 12,
            self::OilAndFilter, self::AirFilter, self::CabinFilter, self::FuelFilter => 12,
            self::BrakeFluid, self::Coolant, self::GearboxOil, self::DifferentialOil => 24,
            self::TimingBelt => 60,
            default => null,
        };
    }

    public function defaultIntervalKm(): ?int
    {
        return match ($this) {
            self::OilAndFilter => 10000,
            self::AirFilter, self::CabinFilter, self::FuelFilter => 20000,
            self::GearboxOil, self::DifferentialOil => 60000,
            self::TimingBelt => 90000,
            self::Brakes => 40000,
            default => null,
        };
    }

    /** Category slugs a due item should point at, so a reminder can offer the part to buy. */
    public function suggestedCategorySlugs(): array
    {
        return match ($this) {
            self::OilAndFilter => ['filtre-ulei', 'uleiuri-motor'],
            self::AirFilter => ['filtre-aer'],
            self::CabinFilter => ['filtre-habitaclu'],
            self::FuelFilter => ['filtre-combustibil'],
            self::TimingBelt => ['distributie'],
            self::BrakeFluid, self::Brakes => ['franare'],
            self::Coolant => ['antigel'],
            self::Tyres => ['anvelope'],
            default => [],
        };
    }
}
