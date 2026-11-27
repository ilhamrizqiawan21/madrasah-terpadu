<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Features\FeatureFlags;
use App\Support\Navigation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman jujur untuk menu yang modulnya belum dibangun (ADR-028).
 * Izin diperiksa dari katalog menu agar tautan "Segera" tidak membuka modul peran lain.
 */
class ComingSoonController extends Controller
{
    public function __construct(private readonly FeatureFlags $features) {}

    public function __invoke(Request $request, string $module): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $item = Navigation::soonItem($module);
        abort_if($item === null, 404);
        abort_unless($user->can($item['permission']), 403);
        // Modul yang dimatikan lewat Kunci fitur tetap 404, sama seperti rute modul jadi (T-211).
        abort_if($item['feature'] !== null && ! $this->features->enabled($item['feature']), 404);

        return Inertia::render('admin/coming-soon', [
            'module' => [
                'label' => $item['label'],
                'group' => $item['group'],
                'description' => $item['description'],
                'plan' => $item['plan'],
            ],
        ]);
    }
}
