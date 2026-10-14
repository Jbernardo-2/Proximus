<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DashboardService;
use App\UserRole;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboardService): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('dashboards.index', [
            'driverDashboard' => $user->role === UserRole::Repartidor
                ? $dashboardService->forDriver($user)
                : null,
            'managementDashboard' => in_array($user->role, [UserRole::Admin, UserRole::Supervisor], true)
                ? $dashboardService->management()
                : null,
            'roleDashboard' => in_array($user->role, [UserRole::Bodeguero, UserRole::Preventista], true)
                ? $dashboardService->forUser($user)
                : null,
        ]);
    }
}
