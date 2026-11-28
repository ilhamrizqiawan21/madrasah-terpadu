<?php

declare(strict_types=1);

namespace App\Enums;

enum AlumniActivity: string
{
    case Study = 'study';
    case Employed = 'employed';
    case Entrepreneur = 'entrepreneur';
    case JobSeeking = 'job_seeking';

    public function label(): string
    {
        return match ($this) {
            self::Study => 'Melanjutkan studi',
            self::Employed => 'Bekerja',
            self::Entrepreneur => 'Berwirausaha',
            self::JobSeeking => 'Mencari pekerjaan',
        };
    }
}
