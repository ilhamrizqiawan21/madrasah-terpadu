<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Feature;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateFeatureFlagsRequest;
use App\Models\User;
use App\Services\Features\FeatureFlags;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FeatureFlagController extends Controller
{
    public function __construct(private readonly FeatureFlags $flags) {}

    public function edit(): Response
    {
        $values = $this->flags->all();

        return Inertia::render('admin/settings/features', [
            'features' => array_map(fn (Feature $feature): array => [
                'key' => $feature->value,
                'label' => $feature->label(),
                'description' => $feature->description(),
                'enabled' => $values[$feature->value],
                'held_until_cutover' => $feature->heldUntilCutover(),
            ], Feature::cases()),
        ]);
    }

    public function update(UpdateFeatureFlagsRequest $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $this->flags->update($request->featureValues(), $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kunci fitur berhasil disimpan.']);

        return back();
    }
}
