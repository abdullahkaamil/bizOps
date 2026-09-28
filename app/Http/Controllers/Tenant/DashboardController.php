<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Dashboard\DashboardData;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardData $data): Response
    {
        return Inertia::render('Dashboard', [
            'dashboard' => $data->for($request->user()),
        ]);
    }
}
