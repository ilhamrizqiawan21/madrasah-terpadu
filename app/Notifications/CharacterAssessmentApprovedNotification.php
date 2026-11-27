<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;

/**
 * Wali diberi tahu saat penilaian karakter anak disetujui kepala unit (ADR-029, T-1204).
 */
final class CharacterAssessmentApprovedNotification extends GuardianNotification
{
    public function __construct(
        private readonly int $reviewId,
        private readonly int $studentId,
        private readonly string $studentName,
        private readonly string $semesterLabel,
    ) {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Behavior;
    }

    public function title(object $notifiable): string
    {
        return 'Penilaian karakter terbit';
    }

    public function body(object $notifiable): string
    {
        return "Penilaian karakter {$this->studentName} semester {$this->semesterLabel} sudah bisa dilihat.";
    }

    public function url(object $notifiable): string
    {
        return route('guardian.students.character.index', ['student' => $this->studentId], false);
    }

    public function tag(object $notifiable): string
    {
        return "character-approved-{$this->reviewId}-{$this->studentId}";
    }
}
