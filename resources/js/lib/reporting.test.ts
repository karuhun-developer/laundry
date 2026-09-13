import { describe, expect, it } from 'vitest';
import {
    buildReportQuery,
    filterReportBranches,
    filterReportServices,
    formatReportDuration,
} from '@/lib/reporting';
import type { ReportBranchOption, ReportFilterForm, ReportServiceOption } from '@/types';

const emptyFilters = (): ReportFilterForm => ({
    date_from: '2026-09-01',
    date_to: '2026-09-30',
    organization_id: '',
    city_id: '',
    branch_id: '',
    service_id: '',
    status: '',
});

describe('reporting helpers', () => {
    it('omits blank optional filters from the request query', () => {
        expect(buildReportQuery(emptyFilters())).toEqual({
            date_from: '2026-09-01',
            date_to: '2026-09-30',
        });
    });

    it('converts selected identifiers to numbers', () => {
        expect(
            buildReportQuery({
                ...emptyFilters(),
                organization_id: '2',
                city_id: '3',
                branch_id: '4',
                service_id: '5',
                status: 'completed',
            }),
        ).toEqual({
            date_from: '2026-09-01',
            date_to: '2026-09-30',
            organization_id: 2,
            city_id: 3,
            branch_id: 4,
            service_id: 5,
            status: 'completed',
        });
    });

    it('formats turnaround minutes for operational reading', () => {
        expect(formatReportDuration(125)).toBe('2 jam 5 menit');
        expect(formatReportDuration(120)).toBe('2 jam');
        expect(formatReportDuration(45)).toBe('45 menit');
        expect(formatReportDuration(null)).toBe('Belum tersedia');
    });

    it('filters branch choices by organization and city', () => {
        const branches: ReportBranchOption[] = [
            { id: 1, organization_id: 1, city_id: 10, name: 'Kemang', code: 'KMG' },
            { id: 2, organization_id: 1, city_id: 20, name: 'Dago', code: 'DGO' },
            { id: 3, organization_id: 2, city_id: 10, name: 'Menteng', code: 'MTG' },
        ];

        expect(filterReportBranches(branches, '1', '10')).toEqual([branches[0]]);
        expect(filterReportBranches(branches, '', '10')).toEqual([branches[0], branches[2]]);
    });

    it('filters service choices by organization', () => {
        const services: ReportServiceOption[] = [
            { id: 1, organization_id: 1, name: 'Cuci reguler', code: 'REG' },
            { id: 2, organization_id: 2, name: 'Cuci express', code: 'EXP' },
        ];

        expect(filterReportServices(services, '2')).toEqual([services[1]]);
        expect(filterReportServices(services, '')).toEqual(services);
    });
});
