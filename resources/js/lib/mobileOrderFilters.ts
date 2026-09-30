import type { MobileOrder, OrderQueue } from '@/types/mobile';

export type CustomerOrderFilters = {
    search: string;
    queue?: OrderQueue;
};

export const emptyCustomerOrderFilters = (): CustomerOrderFilters => ({
    search: '',
});

export const normalizeCustomerOrderFilters = (
    filters: CustomerOrderFilters,
): CustomerOrderFilters => ({
    search: filters.search.trim(),
    ...(filters.queue ? { queue: filters.queue } : {}),
});

export const hasCustomerOrderFilters = (filters: CustomerOrderFilters): boolean =>
    Boolean(filters.search.trim() || (filters.queue && filters.queue !== 'all'));

export const filterCustomerOrders = (
    orders: readonly MobileOrder[],
    filters: CustomerOrderFilters,
): MobileOrder[] => {
    const normalized = normalizeCustomerOrderFilters(filters);
    const searchTerms = normalized.search.toLocaleLowerCase('id-ID').split(/\s+/).filter(Boolean);

    return orders.filter((order) => {
        const searchableText = [
            order.order_number,
            order.branch.name,
            order.branch.code,
            ...order.items.flatMap((item) => [item.service.name, item.service.code]),
        ]
            .join(' ')
            .toLocaleLowerCase('id-ID');
        return searchTerms.every((term) => searchableText.includes(term));
    });
};
