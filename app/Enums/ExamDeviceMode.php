<?php

declare(strict_types=1);

namespace App\Enums;

enum ExamDeviceMode: string
{
    case Lab = 'lab';
    case Tablet = 'tablet';
    case Phone = 'phone';

    public function label(): string
    {
        return match ($this) {
            self::Lab => 'Lab komputer',
            self::Tablet => 'Tablet sekolah',
            self::Phone => 'HP orang tua',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $mode): array => ['value' => $mode->value, 'label' => $mode->label()],
            self::cases(),
        );
    }
}
