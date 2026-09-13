export type ReportFilters = {
    date_from: string;
    date_to: string;
    organization_id: number | null;
    city_id: number | null;
    branch_id: number | null;
    service_id: number | null;
    status: string | null;
};

export type ReportFilterForm = {
    date_from: string;
    date_to: string;
    organization_id: string;
    city_id: string;
    branch_id: string;
    service_id: string;
    status: string;
};

export type ReportQuery = {
    date_from: string;
    date_to: string;
    organization_id?: number;
    city_id?: number;
    branch_id?: number;
    service_id?: number;
    status?: string;
};

export type ReportSummary = {
    paid_revenue_rupiah: number;
    order_count: number;
    average_turnaround_minutes: number | null;
    cancelled_order_count: number;
    cancellation_rate: number;
};

export type ReportBranchComparison = {
    branch_id: number;
    branch_name: string;
    branch_code: string;
    organization_name: string;
    city_name: string;
    order_count: number;
    paid_revenue_rupiah: number;
    completed_order_count: number;
    cancelled_order_count: number;
    cancellation_rate: number;
    average_turnaround_minutes: number | null;
};

export type ReportOrganizationOption = {
    id: number;
    name: string;
};

export type ReportCityOption = {
    id: number;
    name: string;
    province: string;
};

export type ReportBranchOption = {
    id: number;
    organization_id: number;
    city_id: number;
    name: string;
    code: string;
};

export type ReportServiceOption = {
    id: number;
    organization_id: number;
    name: string;
    code: string;
};

export type ReportStatusOption = {
    value: string;
    label: string;
};

export type OperationsReportPageProps = {
    filters: ReportFilters;
    summary: ReportSummary;
    branches: ReportBranchComparison[];
    options: {
        organizations: ReportOrganizationOption[];
        cities: ReportCityOption[];
        branches: ReportBranchOption[];
        services: ReportServiceOption[];
        statuses: ReportStatusOption[];
    };
    defaultFilters: {
        date_from: string;
        date_to: string;
    };
};
