<script setup lang="ts">
import { Building2, MapPin, ReceiptText, UserRound } from '@lucide/vue';
import { computed } from 'vue';
import BranchWhatsAppButton from '@/components/mobile/molecules/BranchWhatsAppButton.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { formatRupiah, formatWeight } from '@/lib/mobileOrders';
import type { MobileBranch, MobileOrder, MobileRole } from '@/types/mobile';

const props = defineProps<{ order: MobileOrder; branch: MobileBranch | null; role: MobileRole }>();
const isPricingPending = computed(() => !props.order.pricing.confirmed);
</script>

<template>
    <Dialog>
        <DialogTrigger as-child>
            <Button
                type="button"
                variant="outline"
                class="h-auto min-h-11 w-full rounded-xl border-line text-brand-700 whitespace-normal"
                data-test="open-order-details"
            >
                <ReceiptText class="size-4" aria-hidden="true" />
                Rincian pesanan
            </Button>
        </DialogTrigger>
        <DialogContent
            class="mobile-dialog max-h-[90dvh] overflow-y-auto sm:max-w-xl"
            data-test="order-details-dialog"
        >
            <DialogHeader class="pr-10">
                <DialogTitle>Rincian pesanan</DialogTitle>
                <DialogDescription>{{ order.order_number }}</DialogDescription>
            </DialogHeader>
            <section class="rounded-2xl bg-surface-blue p-4" aria-label="Informasi cabang">
                <p class="flex items-center gap-2 font-semibold text-brand-700">
                    <Building2 class="size-4 shrink-0" aria-hidden="true" />
                    {{ order.branch.name }}
                </p>
                <p v-if="branch" class="mt-2 text-sm leading-6 text-ink-700">
                    {{ branch.address }}
                </p>
                <BranchWhatsAppButton
                    v-if="role === 'customer' && branch?.phone"
                    :phone="branch.phone"
                    class="mt-3"
                />
            </section>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl bg-canvas p-3">
                        <p class="flex items-center gap-2 text-xs text-ink-500">
                            <UserRound class="size-4" aria-hidden="true" />
                            Pelanggan
                        </p>
                        <p class="mt-1 font-semibold text-ink-950">{{ order.customer.name }}</p>
                    </div>
                    <div v-if="order.delivery?.address" class="rounded-xl bg-canvas p-3">
                        <p class="flex items-center gap-2 text-xs text-ink-500">
                            <MapPin class="size-4" aria-hidden="true" />
                            Alamat jemput / antar
                        </p>
                        <p class="mt-1 text-sm font-semibold text-ink-950">
                            {{ order.delivery.address.label }} ·
                            {{ order.delivery.address.recipient_name }}
                        </p>
                        <p class="mt-1 text-sm leading-6 text-ink-700">
                            {{ order.delivery.address.address }}
                        </p>
                        <p class="mt-1 text-xs text-ink-500">
                            WA {{ order.delivery.address.whatsapp_phone }}
                        </p>
                    </div>
                </div>

                <div class="space-y-3 rounded-xl border border-line p-4">
                    <div
                        v-for="item in order.items"
                        :key="item.id"
                        class="flex items-start justify-between gap-3 text-sm"
                    >
                        <div class="min-w-0">
                            <p class="font-medium text-ink-950">{{ item.service.name }}</p>
                            <p v-if="!isPricingPending" class="text-ink-500">
                                {{ item.price_option.name }} ·
                                {{ formatWeight(item.calculation.requested_weight_grams) }}
                            </p>
                            <p v-else class="text-ink-500">
                                {{ item.price_option.name }} · Berat ditentukan petugas cabang
                            </p>
                        </div>
                        <p
                            v-if="!isPricingPending"
                            class="shrink-0 font-medium text-ink-700"
                        >
                            {{ formatRupiah(item.calculation.subtotal_rupiah) }}
                        </p>
                    </div>
                    <div
                        v-if="!isPricingPending"
                        class="flex items-end justify-between gap-3 border-t border-dashed border-line pt-3"
                    >
                        <div>
                            <p class="text-xs text-ink-500">Total pesanan</p>
                            <p class="mt-1 font-display text-xl font-semibold text-brand-700">
                                {{ formatRupiah(order.pricing.total_rupiah) }}
                            </p>
                        </div>
                        <p v-if="order.delivery" class="text-right text-xs text-ink-500">
                            Termasuk antar-jemput
                            {{ formatRupiah(order.delivery.fee_rupiah) }}
                        </p>
                    </div>
                </div>

            <p v-if="isPricingPending" class="text-sm leading-6 text-ink-500">
                Harga menunggu konfirmasi berat dari petugas cabang.
            </p>
        </DialogContent>
    </Dialog>
</template>
