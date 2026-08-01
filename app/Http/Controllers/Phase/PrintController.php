<?php

declare(strict_types=1);

namespace App\Http\Controllers\Phase;

use App\Http\Controllers\Controller;
use App\Services\Phase\PhaseLibrary;
use App\Support\Phase\Phase;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PrintController extends Controller
{
    public function __invoke(Request $request, PhaseLibrary $library): View
    {
        $signatures = array_filter(explode(',', (string) $request->query('phases', '')));

        $phases = array_values(array_filter(array_map(
            fn (string $sig): ?Phase => $library->find(trim($sig)),
            $signatures,
        )));

        return view('phase10.print', [
            'phases' => $phases,
            'title' => $request->query('title', 'Phase 10'),
            'backUrl' => url()->previous(route('phase10.generator')),
        ]);
    }
}
