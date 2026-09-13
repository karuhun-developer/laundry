<?php

namespace App\Data\Reports;

final readonly class OperationsReportData
{
    /**
     * @param  list<array{branch_id: int, branch_name: string, branch_code: string, organization_name: string, city_name: string, order_count: int, paid_revenue_rupiah: int, completed_order_count: int, cancelled_order_count: int, cancellation_rate: float, average_turnaround_minutes: int|null}>  $branchComparisons
     * @param  list<array{id: int, name: string}>  $organizations
     * @param  list<array{id: int, name: string, province: string}>  $cities
     * @param  list<array{id: int, organization_id: int, city_id: int, name: string, code: string}>  $branches
     * @param  list<array{id: int, organization_id: int, name: string, code: string}>  $services
     * @param  list<array{value: string, label: string}>  $statuses
     */
    public function __construct(
        public ReportFiltersData $filters,
        public int $paidRevenueRupiah,
        public int $orderCount,
        public ?int $averageTurnaroundMinutes,
        public int $cancelledOrderCount,
        public float $cancellationRate,
        public array $branchComparisons,
        public array $organizations,
        public array $cities,
        public array $branches,
        public array $services,
        public array $statuses,
        public string $defaultDateFrom,
        public string $defaultDateTo,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'filters' => $this->filters->toArray(),
            'summary' => [
                'paid_revenue_rupiah' => $this->paidRevenueRupiah,
                'order_count' => $this->orderCount,
                'average_turnaround_minutes' => $this->averageTurnaroundMinutes,
                'cancelled_order_count' => $this->cancelledOrderCount,
                'cancellation_rate' => $this->cancellationRate,
            ],
            'branches' => $this->branchComparisons,
            'options' => [
                'organizations' => $this->organizations,
                'cities' => $this->cities,
                'branches' => $this->branches,
                'services' => $this->services,
                'statuses' => $this->statuses,
            ],
            'defaultFilters' => [
                'date_from' => $this->defaultDateFrom,
                'date_to' => $this->defaultDateTo,
            ],
        ];
    }
}
