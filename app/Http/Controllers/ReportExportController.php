<?php

namespace App\Http\Controllers;

use App\Actions\Reports\BuildOperationsReport;
use App\Actions\Reports\ExportOperationsReportCsv;
use App\Data\Reports\ReportFiltersData;
use App\Enums\RoleName;
use App\Http\Requests\Reports\ReportFiltersRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function __invoke(
        ReportFiltersRequest $request,
        BuildOperationsReport $buildOperationsReport,
        ExportOperationsReportCsv $exportOperationsReportCsv,
    ): StreamedResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new LogicException('An authenticated user is required.');
        }

        if (! $user->hasAnyRole([RoleName::Owner->value, RoleName::Superadmin->value])) {
            throw new AuthorizationException;
        }

        return $exportOperationsReportCsv->handle(
            $buildOperationsReport->handle(
                $user,
                ReportFiltersData::fromRequest($request, $user),
            ),
        );
    }
}
