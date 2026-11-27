<?php

declare(strict_types=1);

namespace App\Services\Students;

use GdImage;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Mengecilkan foto siswa dengan GD (ekstensi wajib, tanpa dependensi baru):
 * sisi terpanjang paling besar 600 px, lalu JPEG dengan kualitas diturunkan
 * bertahap sampai ukurannya ≤ 300 KB. Hasil selalu JPEG baru, sehingga metadata
 * (EXIF/GPS) dan muatan tersembunyi di berkas asli ikut terbuang.
 */
final class StudentPhotoProcessor
{
    public const MAX_SIDE = 600;

    public const MAX_BYTES = 300 * 1024;

    private const QUALITIES = [85, 75, 65, 55, 45, 35];

    public function toJpeg(UploadedFile $file): string
    {
        $binary = file_get_contents($file->getRealPath());
        $source = $binary === false ? false : @imagecreatefromstring($binary);

        if (! $source instanceof GdImage) {
            throw new RuntimeException('Foto tidak dapat dibaca sebagai gambar.');
        }

        $image = $this->resize($source);

        foreach (self::QUALITIES as $quality) {
            $jpeg = $this->encode($image, $quality);

            if (strlen($jpeg) <= self::MAX_BYTES) {
                return $jpeg;
            }
        }

        // 600 px pada kualitas terendah praktis selalu di bawah batas; ini jaring pengaman.
        throw new RuntimeException('Foto tidak dapat dikecilkan sampai 300 KB.');
    }

    private function resize(GdImage $source): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::MAX_SIDE / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        if (! $target instanceof GdImage) {
            throw new RuntimeException('Foto tidak dapat diproses.');
        }

        // Latar putih untuk PNG/WebP transparan, karena JPEG tidak punya kanal alfa.
        imagefill($target, 0, 0, (int) imagecolorallocate($target, 255, 255, 255));
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $target;
    }

    private function encode(GdImage $image, int $quality): string
    {
        ob_start();
        imageinterlace($image, true);
        imagejpeg($image, null, $quality);

        return (string) ob_get_clean();
    }
}
