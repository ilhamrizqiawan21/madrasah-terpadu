<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource ringkasan Beranda Wali (T-303).
 *
 * @property array{
 *     student: array{id: int, nis: string, name: string, nickname: ?string, unit: string, classroom: ?string, status_label: string, photo_url: ?string},
 *     cards: list<array{key: string, priority: int, title: string, summary: string, tone: string, href: ?string}>
 * } $resource
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
            'cards' => $this->resource['cards'],
        ];
    }
}
