<?php

declare(strict_types=1);

namespace App\Enums;

enum ExamQuestionType: string
{
    case Mcq = 'mcq';
    case Matching = 'matching';
    case EssayPaper = 'essay_paper';

    public function label(): string
    {
        return match ($this) {
            self::Mcq => 'Pilihan ganda',
            self::Matching => 'Menjodohkan',
            self::EssayPaper => 'Essay kertas',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $type): array => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
