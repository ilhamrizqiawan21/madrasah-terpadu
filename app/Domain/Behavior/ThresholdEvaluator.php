<?php

declare(strict_types=1);

namespace App\Domain\Behavior;

use App\Models\DisciplineThreshold;
use Illuminate\Support\Collection;

/**
 * Evaluator domain murni untuk menentukan ambang disiplin yang baru terlewati.
 */
final class ThresholdEvaluator
{
    /**
     * @param  Collection<int, DisciplineThreshold>  $activeThresholds
     * @param  list<int>  $alreadyTriggeredThresholdIds
     * @return Collection<int, DisciplineThreshold>
     */
    public function crossedThresholds(
        Collection $activeThresholds,
        int $violationBalance,
        array $alreadyTriggeredThresholdIds,
    ): Collection {
        if ($violationBalance <= 0) {
            return new Collection;
        }

        return $activeThresholds
            ->filter(static fn (DisciplineThreshold $threshold): bool => $threshold->is_active
                && $violationBalance >= $threshold->points
                && ! in_array($threshold->id, $alreadyTriggeredThresholdIds, true))
            ->sortBy('points')
            ->values();
    }
}
