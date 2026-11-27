<?php

declare(strict_types=1);

namespace App\Enums;

enum StudentCardKind: string
{
    case Qr = 'qr';
    case Rfid = 'rfid';

    public function label(): string
    {
        return match ($this) {
            self::Qr => 'Kartu QR',
            self::Rfid => 'Kartu RFID',
        };
    }
}
