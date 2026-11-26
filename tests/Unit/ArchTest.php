<?php

declare(strict_types=1);

arch('tidak ada fungsi debug dd, dump, atau ray')
    ->expect(['dd', 'dump', 'ray'])
    ->not->toBeUsed();

arch('domain tidak bergantung pada http atau facade database')
    ->expect('App\Domain')
    ->not->toUse([
        'Illuminate\Http',
        'Illuminate\Support\Facades\DB',
    ]);

arch('controller tidak memakai facade database secara langsung')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('model tidak bergantung pada service')
    ->expect('App\Models')
    ->not->toUse('App\Services');

arch('semua kelas di app menggunakan strict types')
    ->expect('App')
    ->toUseStrictTypes();
