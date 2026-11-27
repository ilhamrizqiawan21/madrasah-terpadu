<?php

declare(strict_types=1);

namespace App\Enums;

enum MutationType: string
{
    case In = 'in';
    case Out = 'out';
    case Graduated = 'graduated';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Mutasi masuk',
            self::Out => 'Mutasi keluar',
            self::Graduated => 'Lulus',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
