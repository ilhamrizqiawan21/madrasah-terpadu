<?php

declare(strict_types=1);

namespace App\Enums;

enum ExamViolationKind: string
{
    case TabHidden = 'tab_hidden';
    case FullscreenExit = 'fullscreen_exit';
    case CopyPaste = 'copy_paste';
    case Takeover = 'takeover';

    public function label(): string
    {
        return match ($this) {
            self::TabHidden => 'Pindah tab atau aplikasi',
            self::FullscreenExit => 'Keluar layar penuh',
            self::CopyPaste => 'Salin atau tempel',
            self::Takeover => 'Ambil alih perangkat',
        };
    }
}
