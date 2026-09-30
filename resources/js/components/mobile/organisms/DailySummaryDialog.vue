<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { firstRequestError } from '@/lib/mobileHttp';
import { formatRupiah } from '@/lib/mobileOrders';
import { show } from '@/routes/api/v1/mobile/daily-summary';
import type { ApiEnvelope } from '@/types/mobile';

type DailySummary = {
    date: string;
    timezone: string;
    receipts: { cash: number; qris: number; gateway: number };
    manual_income_rupiah: number;
    manual_expenses_rupiah: number;
    net_income_rupiah: number;
    outstanding_rupiah: number;
    statistics: {
        total: number;
        active: number;
        unweighed: number;
        ready: number;
        overdue: number;
        tasks: number;
        complaints: number;
    };
};
const props = defineProps<{ branchId?: number; organizationId?: number }>();
const open = defineModel<boolean>('open', { required: true });
const date = ref('');
const summary = ref<DailySummary | null>(null);
const request = useHttp<Record<string, never>, ApiEnvelope<DailySummary>>({});
const error = ref('');
let generation = 0;
const load = async (): Promise<void> => {
    const currentGeneration = ++generation;
    request.cancel();
    error.value = '';
    try {
        const response = await request.get(
            show({
                query: {
                    ...(props.branchId ? { branch_id: props.branchId } : {}),
                    ...(props.organizationId ? { organization_id: props.organizationId } : {}),
                    ...(date.value ? { date: date.value } : {}),
                },
            }).url,
        );
        if (currentGeneration !== generation) {
            return;
        }
        if (!response) {
            error.value = firstRequestError(request.errors, 'Ringkasan tidak dapat dimuat.');
            return;
        }
        summary.value = response.data;
        date.value = response.data.date;
    } catch (exception: unknown) {
        if (currentGeneration === generation) {
            error.value =
                exception instanceof Error ? exception.message : 'Ringkasan tidak dapat dimuat.';
        }
    }
};
onMounted(() => void load());
watch(
    () => [props.branchId, props.organizationId],
    () => void load(),
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="mobile-dialog max-h-[90dvh] overflow-y-auto sm:max-w-lg">
            <DialogHeader class="pr-8"><DialogTitle>Ringkasan harian</DialogTitle><DialogDescription>Penerimaan sesuai tanggal pelunasan, bukan tanggal pesanan.</DialogDescription></DialogHeader>
            <form class="flex flex-wrap items-end gap-2" @submit.prevent="load"><label class="min-w-0 flex-1 text-sm font-medium">Tanggal<input v-model="date" type="date" required class="mt-1 min-h-11 w-full rounded-lg border border-line px-3" /></label><Button type="submit" :disabled="request.processing">Tampilkan</Button></form>
            <p v-if="request.processing" role="status" class="animate-pulse text-sm text-ink-500">Memuat ringkasan...</p>
            <p v-if="error" role="alert" class="text-sm text-red-600">{{ error }} <Button variant="ghost" @click="load">Coba lagi</Button></p>
            <template v-if="summary && !request.processing && !error">
                <p class="text-xs text-ink-500">{{ summary.date }} · {{ summary.timezone }}</p>
                <dl class="grid grid-cols-2 gap-3">
                    <div v-for="(amount, method) in summary.receipts" :key="method" class="rounded-xl bg-surface-blue p-3"><dt class="text-xs text-ink-500">{{ method === 'cash' ? 'Tunai' : method === 'qris' ? 'QRIS' : 'Gateway' }}</dt><dd class="mt-1 break-words font-semibold text-brand-700">{{ formatRupiah(amount) }}</dd></div>
                    <div class="rounded-xl bg-canvas p-3"><dt class="text-xs text-ink-500">Pemasukan manual</dt><dd class="mt-1 break-words font-semibold text-ink-950">{{ formatRupiah(summary.manual_income_rupiah) }}</dd></div>
                    <div class="rounded-xl bg-canvas p-3"><dt class="text-xs text-ink-500">Pengeluaran manual</dt><dd class="mt-1 break-words font-semibold text-ink-950">{{ formatRupiah(summary.manual_expenses_rupiah) }}</dd></div>
                    <div class="rounded-xl bg-brand-700 p-3 text-white"><dt class="text-xs text-brand-100">Penerimaan bersih</dt><dd class="mt-1 break-words font-semibold">{{ formatRupiah(summary.net_income_rupiah) }}</dd></div>
                </dl>
                <div class="rounded-xl bg-amber-500/10 p-4"><p class="text-xs text-amber-800">Sisa tagihan seluruh pesanan, termasuk selesai</p><p class="mt-1 font-display text-xl font-semibold text-amber-800">{{ formatRupiah(summary.outstanding_rupiah) }}</p></div>
                <dl class="grid grid-cols-2 gap-3 text-sm"><div v-for="(label, key) in { active: 'Aktif', unweighed: 'Belum ditimbang', ready: 'Siap diambil', overdue: 'Terlambat', tasks: 'Jemput / antar aktif', complaints: 'Komplain aktif' }" :key="key" class="flex items-center justify-between gap-2 rounded-lg border border-line p-3"><dt class="text-xs text-ink-500">{{ label }}</dt><dd class="font-semibold text-brand-700">{{ summary.statistics[key] }}</dd></div></dl>
            </template>
        </DialogContent>
    </Dialog>
</template>
