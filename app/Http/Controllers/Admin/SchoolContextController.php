<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SchoolContextController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'unit' => ['sometimes', 'string', 'in:MI,RA,TPQ'],
            'academic_year' => ['sometimes', 'string', 'in:2026/2027,2025/2026'],
        ]);

        if (isset($validated['unit'])) {
            $request->session()->put('active_unit', $validated['unit']);
        }

        if (isset($validated['academic_year'])) {
            $request->session()->put('active_academic_year', $validated['academic_year']);
        }

        return back();
    }
}
