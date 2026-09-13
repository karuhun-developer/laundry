import type {
    ReportBranchOption,
    ReportFilterForm,
    ReportQuery,
    ReportServiceOption,
} from '@/types';

const optionalId = (value: string): number | undefined => {
    if (value === '') {
        return undefined;
    }

    const parsed = Number(value);

    return Number.isInteger(parsed) && parsed > 0 ? parsed : undefined;
};

export const buildReportQuery = (filters: ReportFilterForm): ReportQuery => {
    const query: ReportQuery = {
        date_from: filters.date_from,
        date_to: filters.date_to,
    };
    const organizationId = optionalId(filters.organization_id);
    const cityId = optionalId(filters.city_id);
    const branchId = optionalId(filters.branch_id);
    const serviceId = optionalId(filters.service_id);

    if (organizationId !== undefined) {
        query.organization_id = organizationId;
    }

    if (cityId !== undefined) {
        query.city_id = cityId;
    }

    if (branchId !== undefined) {
        query.branch_id = branchId;
    }

    if (serviceId !== undefined) {
        query.service_id = serviceId;
    }

    if (filters.status !== '') {
        query.status = filters.status;
    }

    return query;
};

export const filterReportBranches = (
    branches: ReportBranchOption[],
    organizationId: string,
    cityId: string,
): ReportBranchOption[] => {
    const selectedOrganizationId = optionalId(organizationId);
    const selectedCityId = optionalId(cityId);

    return branches.filter(
        (branch) =>
            (selectedOrganizationId === undefined ||
                branch.organization_id === selectedOrganizationId) &&
            (selectedCityId === undefined || branch.city_id === selectedCityId),
    );
};

export const filterReportServices = (
    services: ReportServiceOption[],
    organizationId: string,
): ReportServiceOption[] => {
    const selectedOrganizationId = optionalId(organizationId);

    return services.filter(
        (service) =>
            selectedOrganizationId === undefined ||
            service.organization_id === selectedOrganizationId,
    );
};

export const formatReportDuration = (minutes: number | null): string => {
    if (minutes === null) {
        return 'Belum tersedia';
    }

    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;

    if (hours === 0) {
        return `${remainingMinutes} menit`;
    }

    if (remainingMinutes === 0) {
        return `${hours} jam`;
    }

    return `${hours} jam ${remainingMinutes} menit`;
};
