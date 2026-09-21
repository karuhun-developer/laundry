<script setup lang="ts">
import {
    Building2,
    Calendar,
    CircleDollarSign,
    Clock,
    ExternalLink,
    Eye,
    MapPin,
    Package,
    Phone,
    User,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type {
    CmsOrderItemSummary,
    CmsOrderPaymentSummary,
    CmsOrderStatusEventSummary,
    CmsOrderSummary,
    OrderStatusOption,
} from '@/types';

const props = defineProps<{
    order: CmsOrderSummary;
    orderStatuses: OrderStatusOption[];
}>();

const open = ref(false);

const items = computed<CmsOrderItemSummary[]>(() => {
    if (Array.isArray(props.order.items)) return props.order.items;
    if (
        props.order.items &&
        typeof props.order.items === 'object' &&
        'data' in props.order.items &&
        Array.isArray((props.order.items as { data: CmsOrderItemSummary[] }).data)
    ) {
        return (props.order.items as { data: CmsOrderItemSummary[] }).data;
    }
    return [];
});

const payments = computed<CmsOrderPaymentSummary[]>(() => {
    if (Array.isArray(props.order.payments)) return props.order.payments;
    if (
        props.order.payments &&
        typeof props.order.payments === 'object' &&
        'data' in props.order.payments &&
        Array.isArray((props.order.payments as { data: CmsOrderPaymentSummary[] }).data)
    ) {
        return (props.order.payments as { data: CmsOrderPaymentSummary[] }).data;
    }
    return [];
});

const statusHistory = computed<CmsOrderStatusEventSummary[]>(() => {
    if (Array.isArray(props.order.status_history)) return props.order.status_history;
    if (
        props.order.status_history &&
        typeof props.order.status_history === 'object' &&
        'data' in props.order.status_history &&
        Array.isArray((props.order.status_history as { data: CmsOrderStatusEventSummary[] }).data)
    ) {
        return (props.order.status_history as { data: CmsOrderStatusEventSummary[] }).data;
    }
    return [];
});

const formatRupiah = (amount: number): string =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(amount);

const formatDate = (dateStr?: string | null): string => {
    if (!dateStr) return '-';
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(dateStr));
};

const formatWeight = (grams: number): string =>
    `${(grams / 1000).toLocaleString('id-ID', { maximumFractionDigits: 2 })} kg`;

const statusLabel = (statusVal: string): string =>
    props.orderStatuses.find((s) => s.value === statusVal)?.label ?? statusVal;

const statusBadgeVariant = (
    status: string,
): 'default' | 'secondary' | 'outline' | 'destructive' => {
    switch (status) {
        case 'completed':
            return 'default';
        case 'cancelled':
            return 'destructive';
        case 'ready_for_pickup':
            return 'default';
        default:
            return 'secondary';
    }
};

const cleanPhone = computed(() => {
    const phone = props.order.customer.whatsapp_phone;
    if (!phone) return null;
    let normalized = phone.replace(/\D/g, '');
    if (normalized.startsWith('0')) {
        normalized = `62${normalized.slice(1)}`;
    }
    return normalized;
});

const waLink = computed(() => {
    if (!cleanPhone.value) return null;
    return `https://wa.me/${cleanPhone.value}`;
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="outline" size="sm" class="h-8 gap-1.5 px-2.5 text-xs">
                <Eye class="size-3.5" />
                <span>Detail</span>
            </Button>
        </DialogTrigger>

        <DialogContent class="max-h-[90vh] max-w-2xl overflow-y-auto p-6">
            <DialogHeader class="border-line border-b pb-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <DialogTitle class="font-display text-ink-950 text-xl font-bold">
                            {{ order.order_number ?? `Pesanan #${order.id}` }}
                        </DialogTitle>
                        <p class="text-ink-500 mt-1 flex items-center gap-1.5 text-xs">
                            <Calendar class="size-3.5" />
                            Dibuat pada {{ formatDate(order.created_at) }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Badge :variant="statusBadgeVariant(order.status)">
                            {{ statusLabel(order.status) }}
                        </Badge>
                        <Badge
                            v-if="order.payment_summary.is_paid"
                            variant="default"
                            class="bg-emerald-600 hover:bg-emerald-600 text-white"
                        >
                            Lunas
                        </Badge>
                        <Badge
                            v-else-if="order.payment_summary.paid_rupiah > 0"
                            variant="secondary"
                            class="bg-amber-100 text-amber-900 border-amber-300 dark:bg-amber-950 dark:text-amber-200"
                        >
                            Sisa {{ formatRupiah(order.payment_summary.outstanding_rupiah) }}
                        </Badge>
                        <Badge v-else variant="outline">
                            Belum bayar
                        </Badge>
                    </div>
                </div>
            </DialogHeader>

            <div class="space-y-6 pt-2">
                <!-- Customer & Branch Section -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <!-- Customer Card -->
                    <div class="border-line bg-canvas/50 rounded-2xl border p-4 space-y-2">
                        <div class="flex items-center gap-2 text-ink-900 text-sm font-semibold">
                            <User class="size-4 text-ink-500" />
                            <span>Informasi Pelanggan</span>
                        </div>
                        <p class="text-ink-950 font-medium text-sm">{{ order.customer.name }}</p>
                        <div v-if="order.customer.whatsapp_phone" class="flex items-center gap-2">
                            <Phone class="size-3.5 text-ink-400" />
                            <a
                                v-if="waLink"
                                :href="waLink"
                                target="_blank"
                                rel="noreferrer"
                                class="inline-flex items-center gap-1 text-xs text-primary font-medium hover:underline"
                            >
                                <span>{{ order.customer.whatsapp_phone }}</span>
                                <ExternalLink class="size-3" />
                            </a>
                            <span v-else class="text-ink-600 text-xs">{{ order.customer.whatsapp_phone }}</span>
                        </div>
                        <p v-else class="text-ink-400 text-xs">Nomor WhatsApp tidak terdaftar</p>
                    </div>

                    <!-- Branch Card -->
                    <div class="border-line bg-canvas/50 rounded-2xl border p-4 space-y-2">
                        <div class="flex items-center gap-2 text-ink-900 text-sm font-semibold">
                            <Building2 class="size-4 text-ink-500" />
                            <span>Cabang & Organisasi</span>
                        </div>
                        <p class="text-ink-950 font-medium text-sm">
                            {{ order.branch.name }}
                            <span class="text-ink-400 text-xs font-normal">({{ order.branch.code }})</span>
                        </p>
                        <p class="text-ink-500 text-xs">{{ order.organization.name }}</p>
                    </div>
                </div>

                <!-- Delivery Address if available -->
                <div v-if="order.delivery?.address" class="border-line bg-canvas/50 rounded-2xl border p-4 space-y-1.5">
                    <div class="flex items-center gap-2 text-ink-900 text-sm font-semibold">
                        <MapPin class="size-4 text-ink-500" />
                        <span>Alamat Antar / Jemput</span>
                    </div>
                    <p class="text-ink-800 text-xs">
                        {{ order.delivery.address.recipient_name ?? order.customer.name }}
                        <span v-if="order.delivery.address.whatsapp_phone"> · {{ order.delivery.address.whatsapp_phone }}</span>
                    </p>
                    <p class="text-ink-600 text-xs leading-relaxed">
                        {{ order.delivery.address.address }}
                    </p>
                    <p v-if="order.delivery.address.notes" class="text-ink-500 text-xs italic">
                        Catatan: {{ order.delivery.address.notes }}
                    </p>
                </div>

                <!-- Order Items & Calculation -->
                <div class="border-line overflow-hidden rounded-2xl border">
                    <div class="border-line bg-canvas/60 border-b px-4 py-3 flex items-center gap-2 font-semibold text-sm text-ink-900">
                        <Package class="size-4 text-ink-500" />
                        <span>Rincian Layanan & Paket</span>
                    </div>
                    <div class="divide-line divide-y">
                        <div
                            v-for="item in items"
                            :key="item.id"
                            class="p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-sm"
                        >
                            <div>
                                <p class="font-medium text-ink-950">
                                    {{ item.service.name }}
                                </p>
                                <p class="text-xs text-ink-500 mt-0.5">
                                    Paket: {{ item.price_option.name }} · Berat: {{ formatWeight(item.calculation.chargeable_weight_grams) }}
                                </p>
                                <p v-if="item.price_option.pricing_snapshot.price_per_kilogram_rupiah" class="text-xs text-ink-400 mt-0.5">
                                    Tarif: {{ formatRupiah(item.price_option.pricing_snapshot.price_per_kilogram_rupiah) }} / kg
                                </p>
                            </div>
                            <div class="text-right sm:self-center">
                                <span class="font-semibold text-ink-950 text-sm">
                                    {{ formatRupiah(item.calculation.subtotal_rupiah) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Pricing Summary Footer -->
                    <div class="border-line bg-canvas/30 border-t p-4 space-y-1.5 text-xs text-ink-600">
                        <div class="flex justify-between">
                            <span>Subtotal Layanan</span>
                            <span class="font-medium text-ink-800">{{ formatRupiah(order.pricing.items_subtotal_rupiah) }}</span>
                        </div>
                        <div v-if="order.pricing.delivery_fee_rupiah > 0" class="flex justify-between">
                            <span>Biaya Pengiriman</span>
                            <span class="font-medium text-ink-800">{{ formatRupiah(order.pricing.delivery_fee_rupiah) }}</span>
                        </div>
                        <div class="border-line flex justify-between border-t pt-2 text-sm font-bold text-ink-950">
                            <span>Total Pembayaran</span>
                            <span>{{ formatRupiah(order.pricing.total_rupiah) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Payments History -->
                <div class="border-line overflow-hidden rounded-2xl border">
                    <div class="border-line bg-canvas/60 border-b px-4 py-3 flex items-center justify-between font-semibold text-sm text-ink-900">
                        <div class="flex items-center gap-2">
                            <CircleDollarSign class="size-4 text-ink-500" />
                            <span>Histori Pembayaran</span>
                        </div>
                        <span class="text-xs font-normal text-ink-500">
                            Terbayar: {{ formatRupiah(order.payment_summary.paid_rupiah) }}
                        </span>
                    </div>

                    <div v-if="payments.length === 0" class="p-4 text-center text-xs text-ink-400">
                        Belum ada riwayat pembayaran yang tercatat.
                    </div>
                    <div v-else class="divide-line divide-y text-xs">
                        <div
                            v-for="payment in payments"
                            :key="payment.id"
                            class="p-4 flex items-center justify-between gap-3"
                        >
                            <div>
                                <p class="font-medium text-ink-950">
                                    {{ payment.payment_number ?? `Pembayaran #${payment.id}` }}
                                </p>
                                <p class="text-ink-500 mt-0.5">
                                    Metode: {{ payment.method_label }} · {{ formatDate(payment.created_at) }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-ink-950">{{ formatRupiah(payment.amount_rupiah) }}</p>
                                <Badge
                                    :variant="payment.status === 'paid' ? 'default' : 'secondary'"
                                    class="text-[10px] mt-0.5"
                                >
                                    {{ payment.status_label }}
                                </Badge>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status Progress History -->
                <div v-if="statusHistory.length > 0" class="border-line overflow-hidden rounded-2xl border">
                    <div class="border-line bg-canvas/60 border-b px-4 py-3 flex items-center gap-2 font-semibold text-sm text-ink-900">
                        <Clock class="size-4 text-ink-500" />
                        <span>Riwayat Pengerjaan</span>
                    </div>
                    <div class="p-4 space-y-3">
                        <div
                            v-for="event in statusHistory"
                            :key="event.id"
                            class="flex items-start gap-3 text-xs"
                        >
                            <div class="mt-0.5 size-2 rounded-full bg-primary shrink-0" />
                            <div class="flex-1">
                                <div class="flex flex-wrap items-center justify-between gap-1">
                                    <p class="font-semibold text-ink-950">
                                        {{ statusLabel(event.status) }}
                                    </p>
                                    <span class="text-ink-400 text-[11px]">{{ formatDate(event.occurred_at) }}</span>
                                </div>
                                <p class="text-ink-500 text-[11px] mt-0.5">
                                    Oleh: {{ event.actor.name }}
                                </p>
                                <p v-if="event.note" class="text-ink-600 mt-1 italic">
                                    "{{ event.note }}"
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
