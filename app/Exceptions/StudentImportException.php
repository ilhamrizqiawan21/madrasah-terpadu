<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Berkas impor siswa yang ditolak seluruhnya. Pesan siap ditampilkan ke pengguna.
 */
final class StudentImportException extends RuntimeException
{
    public static function unreadable(): self
    {
        return new self('Berkas tidak bisa dibaca. Simpan ulang sebagai .xlsx dari template lalu unggah lagi.');
    }

    /**
     * @param  list<string>  $columns
     */
    public static function missingColumns(array $columns): self
    {
        return new self('Kolom '.implode(', ', $columns).' tidak ditemukan. Unduh template lalu salin data ke dalamnya.');
    }

    public static function empty(): self
    {
        return new self('Berkas tidak berisi baris data.');
    }

    public static function tooManyRows(int $rows, int $max): self
    {
        return new self(sprintf(
            'Berkas berisi %s baris data. Maksimal %s baris per impor; pecah berkas lalu impor bertahap.',
            number_format($rows, 0, ',', '.'),
            number_format($max, 0, ',', '.'),
        ));
    }
}
