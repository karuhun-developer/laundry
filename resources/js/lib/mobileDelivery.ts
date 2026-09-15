import { formatDistance, formatRupiah } from '@/lib/mobileOrders';
import type { NearestMobileBranch } from '@/types/mobile';

export const deliveryCoverageSummary = (delivery: NearestMobileBranch['delivery']): string => {
    const distance = formatDistance(delivery.distance_meters);
    const freeRadius = formatDistance(delivery.free_radius_meters);

    if (delivery.within_free_radius) {
        return `Jarak ${distance} · gratis jemput & antar dalam radius ${freeRadius}.`;
    }

    return `Jarak ${distance} · radius gratis ${freeRadius} · estimasi biaya kelebihan ${formatRupiah(delivery.fee_rupiah)} (${formatRupiah(delivery.fee_per_km_rupiah)}/km).`;
};
