<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Enums\SettingKey;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Menyimpan identitas sekolah (Pengaturan > Identitas sekolah) dan logo/favicon
 * (Pengaturan > Tampilan), T-201.
 * Berkas disimpan di disk privat dan disajikan lewat BrandingAssetController,
 * sehingga tidak bergantung pada storage:link dan tipe kontennya terkendali.
 */
final class IdentitySettingsService
{
    public const DISK = 'local';

    private const DIRECTORY = 'branding';

    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * @param  array{app_name: string, foundation_name: string, address: ?string, phone: ?string, email: ?string}  $data
     */
    public function updateIdentity(array $data, User $actor): void
    {
        $this->settings->setMany([
            SettingKey::AppName->value => $data['app_name'],
            SettingKey::FoundationName->value => $data['foundation_name'],
            SettingKey::Address->value => $data['address'],
            SettingKey::Phone->value => $data['phone'],
            SettingKey::Email->value => $data['email'],
        ], $actor);
    }

    public function updateAssets(
        ?UploadedFile $logo,
        ?UploadedFile $favicon,
        bool $removeLogo,
        bool $removeFavicon,
        User $actor,
    ): void {
        $current = $this->settings->identity();
        $values = [];
        $newFiles = [];
        $obsoleteFiles = [];

        foreach ([
            [SettingKey::LogoPath, $logo, $removeLogo, $current['logo_path']],
            [SettingKey::FaviconPath, $favicon, $removeFavicon, $current['favicon_path']],
        ] as [$key, $upload, $remove, $existingPath]) {
            if ($upload instanceof UploadedFile) {
                $path = $upload->store(self::DIRECTORY, self::DISK);

                if ($path === false) {
                    $this->deleteFiles($newFiles);

                    throw new RuntimeException('Berkas gambar gagal disimpan.');
                }

                $newFiles[] = $path;
                $values[$key->value] = $path;
            } elseif ($remove) {
                $values[$key->value] = null;
            } else {
                continue;
            }

            if ($existingPath !== null) {
                $obsoleteFiles[] = $existingPath;
            }
        }

        if ($values === []) {
            return;
        }

        try {
            $this->settings->setMany($values, $actor);
        } catch (Throwable $exception) {
            $this->deleteFiles($newFiles);

            throw $exception;
        }

        // Berkas lama baru dihapus setelah jalur baru tersimpan, agar logo tidak pernah hilang di tengah jalan.
        DB::afterCommit(fn () => $this->deleteFiles($obsoleteFiles));
    }

    /**
     * @param  list<string>  $paths
     */
    private function deleteFiles(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk(self::DISK)->delete($paths);
        }
    }
}
