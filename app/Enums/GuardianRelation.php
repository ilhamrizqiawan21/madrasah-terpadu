<?php

declare(strict_types=1);

namespace App\Enums;

enum GuardianRelation: string
{
    case Father = 'ayah';
    case Mother = 'ibu';
    case Guardian = 'wali';

    public function label(): string
    {
        return match ($this) {
            self::Father => 'Ayah',
            self::Mother => 'Ibu',
            self::Guardian => 'Wali',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $relation): array => ['value' => $relation->value, 'label' => $relation->label()],
            self::cases(),
        );
    }
}
