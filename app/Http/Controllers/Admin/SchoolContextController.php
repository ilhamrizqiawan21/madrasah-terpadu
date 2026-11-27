<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SchoolContextController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'unit' => ['sometimes', 'string', Rule::exists('units', 'code')->where('is_active', true)],
            'academic_year_id' => ['sometimes', 'integer', Rule::exists('academic_years', 'id')],
        ], [
            'unit.exists' => 'Unit tidak ditemukan atau sedang nonaktif.',
            'academic_year_id.exists' => 'Tahun ajaran tidak ditemukan.',
        ]);

        if (isset($validated['unit'])) {
            $request->session()->put(SchoolContext::SESSION_UNIT, $validated['unit']);
        }

        if (isset($validated['academic_year_id'])) {
            $request->session()->put(SchoolContext::SESSION_ACADEMIC_YEAR, (int) $validated['academic_year_id']);
        }

        return back();
    }
}
