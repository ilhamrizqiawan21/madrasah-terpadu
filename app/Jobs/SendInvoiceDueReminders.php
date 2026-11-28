<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\Feature;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Notifications\InvoiceDueReminderNotification;
use App\Services\Features\FeatureFlags;
use App\Services\Notifications\GuardianNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Pengingat jatuh tempo tagihan H-3 dan H+1 pada pukul 07.00 WITA (T-609).
 * Maksimal satu notifikasi per tagihan per jenis.
 */
final class SendInvoiceDueReminders implements ShouldQueue
{
    use Queueable;

    public function handle(FeatureFlags $features, GuardianNotifier $notifier): int
    {
        if (! $features->enabled(Feature::Billing)) {
            return 0;
        }

        $today = CarbonImmutable::now('Asia/Makassar')->startOfDay();
        $targets = [
            'before_due' => $today->addDays(3)->toDateString(),
            'overdue' => $today->subDay()->toDateString(),
        ];

        $sentCount = 0;

        foreach ($targets as $kind => $dueDate) {
            Invoice::query()
                ->with('student')
                ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value])
                ->whereDate('due_date', $dueDate)
                ->orderBy('id')
                ->chunkById(200, function (Collection $invoices) use ($kind, $notifier, &$sentCount): void {
                    foreach ($invoices as $invoice) {
                        $remaining = $invoice->remaining();
                        if ($remaining <= 0) {
                            continue;
                        }

                        $inserted = DB::transaction(static fn (): int => DB::table('invoice_reminders')->insertOrIgnore([
                            'invoice_id' => $invoice->id,
                            'kind' => $kind,
                            'sent_at' => now(),
                        ]));

                        if ($inserted !== 1) {
                            continue;
                        }

                        $notifier->notifyStudentGuardians(
                            $invoice->student,
                            new InvoiceDueReminderNotification(
                                invoiceId: $invoice->id,
                                studentName: $invoice->student->nickname ?? $invoice->student->name,
                                description: $invoice->description,
                                remaining: $remaining,
                                dueDateLabel: $invoice->due_date->translatedFormat('d M Y'),
                                kind: $kind,
                            ),
                        );

                        $sentCount++;
                    }
                });
        }

        return $sentCount;
    }
}
