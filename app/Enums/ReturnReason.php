<?php

namespace App\Enums;

enum ReturnReason: string
{
    case Withdrawal = 'withdrawal';
    case WrongPart = 'wrong_part';
    case DoesNotFit = 'does_not_fit';
    case Damaged = 'damaged';
    case Defective = 'defective';
    case NotAsDescribed = 'not_as_described';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Withdrawal => 'Retragere în 14 zile',
            self::WrongPart => 'Piesă greșită',
            self::DoesNotFit => 'Nu se potrivește pe mașină',
            self::Damaged => 'Deteriorată la transport',
            self::Defective => 'Defectă',
            self::NotAsDescribed => 'Diferită de descriere',
            self::Other => 'Alt motiv',
        };
    }
}
