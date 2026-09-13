<?php

namespace App\Data\Reports;

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Http\Requests\Reports\ReportFiltersRequest;
use App\Models\User;
use Carbon\CarbonImmutable;

final readonly class ReportFiltersData
{
    public function __construct(
        public CarbonImmutable $dateFrom,
        public CarbonImmutable $dateTo,
        public ?int $organizationId,
        public ?int $cityId,
        public ?int $branchId,
        public ?int $serviceId,
        public ?OrderStatus $status,
    ) {}

    public static function fromRequest(ReportFiltersRequest $request, User $user): self
    {
        $organizationId = $user->hasRole(RoleName::Superadmin->value)
            ? self::optionalInteger($request, 'organization_id')
            : $user->organization_id;

        return new self(
            dateFrom: CarbonImmutable::parse($request->string('date_from')->toString())->startOfDay(),
            dateTo: CarbonImmutable::parse($request->string('date_to')->toString())->endOfDay(),
            organizationId: $organizationId,
            cityId: self::optionalInteger($request, 'city_id'),
            branchId: self::optionalInteger($request, 'branch_id'),
            serviceId: self::optionalInteger($request, 'service_id'),
            status: $request->filled('status')
                ? OrderStatus::from($request->string('status')->toString())
                : null,
        );
    }

    /** @return array{date_from: string, date_to: string, organization_id: int|null, city_id: int|null, branch_id: int|null, service_id: int|null, status: string|null} */
    public function toArray(): array
    {
        return [
            'date_from' => $this->dateFrom->toDateString(),
            'date_to' => $this->dateTo->toDateString(),
            'organization_id' => $this->organizationId,
            'city_id' => $this->cityId,
            'branch_id' => $this->branchId,
            'service_id' => $this->serviceId,
            'status' => $this->status?->value,
        ];
    }

    private static function optionalInteger(ReportFiltersRequest $request, string $key): ?int
    {
        return $request->filled($key) ? $request->integer($key) : null;
    }
}
