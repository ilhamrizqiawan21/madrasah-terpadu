<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('guardian/billing');
    }
}
