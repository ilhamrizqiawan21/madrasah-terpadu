<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\Guardian\GuardianHomeSummaryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource ringkasan Beranda Wali (T-303). Bentuk data: GuardianHomeSummaryService.
 *
 * @phpstan-import-type HomeSummary from GuardianHomeSummaryService
 *
 * @property HomeSummary $resource
 */
class GuardianHomeResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'student' => $this->resource['student'],
            'today' => $this->resource['today'],
            'week' => $this->resource['week'],
            'leave' => $this->resource['leave'],
            'behavior' => $this->resource['behavior'],
            'exams' => $this->resource['exams'],
            'cards' => $this->resource['cards'],
        ];
    }
}
