<?php

namespace App\Http\Controllers;

use App\Actions\Reports\BuildOperationsReport;
use App\Data\Reports\ReportFiltersData;
use App\Enums\RoleName;
use App\Http\Requests\Reports\ReportFiltersRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        ReportFiltersRequest $request,
        BuildOperationsReport $buildOperationsReport,
    ): Response|RedirectResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new LogicException('An authenticated user is required.');
        }

        if ($user->hasAnyRole([RoleName::Customer->value, RoleName::BranchAdmin->value])) {
            return to_route('mobile.app');
        }

        if (! $user->hasAnyRole([RoleName::Owner->value, RoleName::Superadmin->value])) {
            throw new AuthorizationException;
        }

        $report = $buildOperationsReport->handle(
            $user,
            ReportFiltersData::fromRequest($request, $user),
        );

        return Inertia::render('Dashboard', $report->toArray());
    }
}
