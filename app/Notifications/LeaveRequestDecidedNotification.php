<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\LeaveRequestStatus;
use App\Enums\LeaveType;
use App\Enums\NotificationCategory;

final class LeaveRequestDecidedNotification extends GuardianNotification
{
    public function __construct(
        private readonly int $leaveRequestId,
        private readonly int $studentId,
        private readonly string $studentName,
        private readonly LeaveType $type,
        private readonly string $dateRangeLabel,
        private readonly LeaveRequestStatus $decision,
        private readonly ?string $note = null,
    ) {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Attendance;
    }

    public function title(object $notifiable): string
    {
        return $this->decision === LeaveRequestStatus::Approved
            ? "Pengajuan {$this->type->label()} Disetujui"
            : "Pengajuan {$this->type->label()} Ditolak";
    }

    public function body(object $notifiable): string
    {
        $statusText = $this->decision === LeaveRequestStatus::Approved ? 'disetujui' : 'ditolak';
        $noteSuffix = $this->note !== null && $this->note !== '' ? " Catatan: {$this->note}" : '';

        return "Pengajuan {$this->type->label()} untuk {$this->studentName} ({$this->dateRangeLabel}) telah {$statusText}.{$noteSuffix}";
    }

    public function url(object $notifiable): string
    {
        return "/wali/anak/{$this->studentId}/izin";
    }

    public function tag(object $notifiable): string
    {
        return "leave-decision-{$this->leaveRequestId}";
    }
}
