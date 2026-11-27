<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Enums\StudentCardKind;
use App\Models\Student;
use App\Models\StudentCard;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Pengelolaan kartu QR & RFID siswa (T-501, ADR-008, ADR-022).
 *
 * Token QR = 32 byte acak (64 hex); UID RFID dinormalisasi (huruf besar tanpa pemisah).
 * Database hanya menyimpan hash SHA-256. Paling banyak satu kartu aktif per siswa.
 */
final class StudentCardService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public static function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    public static function normalizeRfidUid(string $rawUid): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $rawUid));
    }

    /**
     * Menerbitkan kartu QR baru (token mentah dikembalikan sekali untuk cetak/tampil).
     *
     * @return array{card: StudentCard, token: string}
     */
    public function issueQr(Student $student, User $actor): array
    {
        $rawToken = bin2hex(random_bytes(32));
        $hash = self::hashToken($rawToken);

        $card = DB::transaction(function () use ($student, $actor, $hash): StudentCard {
            Student::query()->whereKey($student->id)->lockForUpdate()->firstOrFail();

            $revokedCount = $this->revokeActiveCards($student);

            $card = StudentCard::query()->create([
                'student_id' => $student->id,
                'kind' => StudentCardKind::Qr,
                'token_hash' => $hash,
                'issued_at' => now(),
                'revoked_at' => null,
                'issued_by' => $actor->id,
            ]);

            $this->auditLogger->record(
                action: 'student_card_issued',
                subject: $student,
                after: [
                    'card_id' => $card->id,
                    'kind' => StudentCardKind::Qr->value,
                    'revoked_previous' => $revokedCount,
                ],
                userId: $actor->id,
            );

            return $card;
        });

        return [
            'card' => $card,
            'token' => $rawToken,
        ];
    }

    /**
     * Mendaftarkan kartu RFID lama dengan UID yang dinormalisasi dan di-hash SHA-256.
     */
    public function registerRfid(Student $student, string $rawUid, User $actor): StudentCard
    {
        $normalized = self::normalizeRfidUid($rawUid);

        if (strlen($normalized) < 4 || strlen($normalized) > 32) {
            throw new DomainException('UID RFID harus terdiri dari 4–32 karakter alfanumerik.');
        }

        $hash = self::hashToken($normalized);

        return DB::transaction(function () use ($student, $actor, $hash): StudentCard {
            Student::query()->whereKey($student->id)->lockForUpdate()->firstOrFail();

            $duplicateExists = StudentCard::query()
                ->where('token_hash', $hash)
                ->exists();

            if ($duplicateExists) {
                throw new DomainException('UID kartu RFID sudah terdaftar pada kartu lain.');
            }

            $revokedCount = $this->revokeActiveCards($student);

            $card = StudentCard::query()->create([
                'student_id' => $student->id,
                'kind' => StudentCardKind::Rfid,
                'token_hash' => $hash,
                'issued_at' => now(),
                'revoked_at' => null,
                'issued_by' => $actor->id,
            ]);

            $this->auditLogger->record(
                action: 'student_card_issued',
                subject: $student,
                after: [
                    'card_id' => $card->id,
                    'kind' => StudentCardKind::Rfid->value,
                    'revoked_previous' => $revokedCount,
                ],
                userId: $actor->id,
            );

            return $card;
        });
    }

    public function revokeActive(Student $student, User $actor): int
    {
        return DB::transaction(function () use ($student, $actor): int {
            Student::query()->whereKey($student->id)->lockForUpdate()->firstOrFail();

            $revokedCount = $this->revokeActiveCards($student);

            if ($revokedCount > 0) {
                $this->auditLogger->record(
                    action: 'student_card_revoked',
                    subject: $student,
                    after: ['revoked_count' => $revokedCount],
                    userId: $actor->id,
                );
            }

            return $revokedCount;
        });
    }

    /**
     * Mencari kartu aktif dari input mentah scanner (token QR atau UID RFID).
     */
    public function findActiveByRawToken(string $rawInput): ?StudentCard
    {
        $trimmed = trim($rawInput);

        if ($trimmed === '') {
            return null;
        }

        $exactHash = self::hashToken($trimmed);

        $card = StudentCard::query()
            ->active()
            ->where('token_hash', $exactHash)
            ->first();

        if ($card !== null) {
            return $card;
        }

        $normalized = self::normalizeRfidUid($trimmed);

        if ($normalized === '' || $normalized === $trimmed) {
            return null;
        }

        return StudentCard::query()
            ->active()
            ->where('kind', StudentCardKind::Rfid->value)
            ->where('token_hash', self::hashToken($normalized))
            ->first();
    }

    private function revokeActiveCards(Student $student): int
    {
        return StudentCard::query()
            ->where('student_id', $student->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }
}
