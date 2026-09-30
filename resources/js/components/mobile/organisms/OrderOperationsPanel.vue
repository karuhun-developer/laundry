<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { firstRequestError, messageFromResponseData } from '@/lib/mobileHttp';
import { formatOrderDate } from '@/lib/mobileOrders';
import { store as complaintRoute } from '@/routes/api/v1/organizations/branches/orders/complaints';
import { store as estimateRoute } from '@/routes/api/v1/organizations/branches/orders/estimate';
import { store as taskRoute } from '@/routes/api/v1/organizations/branches/orders/tasks';
import type {
    ApiEnvelope,
    MobileOrder,
    OrderComplaint,
    OrderOperationsOrder,
    OrderTask,
    OrderTaskStatus,
} from '@/types/mobile';

const props = defineProps<{ order: OrderOperationsOrder; staff: boolean; disabled?: boolean }>();
const emit = defineEmits<{ saved: [order: MobileOrder] }>();
const localOrder = ref<MobileOrder | null>(null);
const order = computed(() =>
    localOrder.value && localOrder.value.id === props.order.id ? localOrder.value : props.order,
);
watch(
    () => props.order,
    () => {
        localOrder.value = null;
    },
);
const error = ref('');
const success = ref('');
const taskOpen = ref(false);
const complaintOpen = ref(false);
const estimateOpen = ref(false);
const taskForm = useHttp<
    {
        task_id: number | null;
        kind: 'pickup' | 'delivery';
        status: OrderTaskStatus;
        operator_name: string;
        scheduled_at: string;
        address: string;
        recipient_name: string;
        note: string;
        photos: File[];
    },
    ApiEnvelope<MobileOrder>
>({
    task_id: null,
    kind: 'delivery',
    status: 'scheduled',
    operator_name: '',
    scheduled_at: '',
    address: '',
    recipient_name: '',
    note: '',
    photos: [],
});
const complaintForm = useHttp<
    {
        complaint_id: number | null;
        category: string;
        description: string;
        status: 'new' | 'handling' | 'resolved';
        note: string;
        photos: File[];
    },
    ApiEnvelope<MobileOrder>
>({ complaint_id: null, category: 'other', description: '', status: 'new', note: '', photos: [] });
const estimateForm = useHttp<
    { estimated_ready_at: string; reason: string },
    ApiEnvelope<MobileOrder>
>({ estimated_ready_at: '', reason: '' });
const busy = computed(
    () =>
        props.disabled ||
        taskForm.processing ||
        complaintForm.processing ||
        estimateForm.processing,
);
const terminal = computed(() => ['completed', 'cancelled'].includes(order.value.status));
const activeComplaint = computed(() =>
    order.value.complaints?.some((complaint) => complaint.status !== 'resolved'),
);
const taskLabels: Record<OrderTaskStatus, string> = {
    waiting: 'Menunggu penjadwalan',
    scheduled: 'Terjadwal',
    en_route: 'Dalam perjalanan',
    completed: 'Diserahkan',
    failed: 'Gagal, perlu jadwal ulang',
    cancelled: 'Dibatalkan',
};
const taskTransitions: Record<OrderTaskStatus, OrderTaskStatus[]> = {
    waiting: ['scheduled', 'cancelled'],
    scheduled: ['scheduled', 'en_route', 'cancelled'],
    en_route: ['completed', 'failed', 'cancelled'],
    failed: ['scheduled', 'cancelled'],
    completed: [],
    cancelled: [],
};
const taskOptions = computed<OrderTaskStatus[]>(() => {
    const task = order.value.tasks?.find((entry) => entry.id === taskForm.task_id);
    const transitions: OrderTaskStatus[] = task ? taskTransitions[task.status] : ['scheduled'];
    return transitions.filter(
        (status) =>
            taskForm.kind !== 'delivery' ||
            order.value.status === 'ready_for_pickup' ||
            !['en_route', 'completed'].includes(status),
    );
});
const taskStatusLabel = (status: string): string =>
    Object.entries(taskLabels).find(([value]) => value === status)?.[1] ?? status;
const complaintCanResolve = computed(
    () =>
        order.value.complaints?.find((entry) => entry.id === complaintForm.complaint_id)?.status ===
        'handling',
);
const complaintLabels = { new: 'Baru', handling: 'Sedang ditangani', resolved: 'Selesai' };
const categoryLabels: Record<string, string> = {
    late: 'Terlambat',
    damaged: 'Rusak / noda',
    missing: 'Barang kurang',
    other: 'Lainnya',
};
const routeParams = computed(() => ({
    organization: order.value.organization.slug,
    branch: order.value.branch.id,
    order: order.value.id,
}));
const localDate = (value: string | null | undefined): string => {
    if (!value) {
        return '';
    }
    const date = new Date(value);
    return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
};
const openTask = (task?: OrderTask): void => {
    taskForm.resetAndClearErrors();
    taskForm.task_id = task?.id ?? null;
    taskForm.kind = task?.kind ?? 'delivery';
    taskForm.status =
        task?.status === 'en_route'
            ? 'completed'
            : task?.status === 'scheduled' &&
                (task.kind === 'pickup' || order.value.status === 'ready_for_pickup')
              ? 'en_route'
              : 'scheduled';
    taskForm.operator_name = task?.operator_name ?? '';
    taskForm.scheduled_at = localDate(task?.scheduled_at);
    taskForm.address = task?.address ?? order.value.delivery?.address?.address ?? '';
    taskForm.recipient_name =
        task?.recipient_name ?? order.value.delivery?.address?.recipient_name ?? '';
    taskOpen.value = true;
    complaintOpen.value = false;
    estimateOpen.value = false;
    error.value = '';
};
const openComplaint = (complaint?: OrderComplaint): void => {
    complaintForm.resetAndClearErrors();
    complaintForm.complaint_id = complaint?.id ?? null;
    complaintForm.category = complaint?.category ?? 'other';
    complaintForm.description = complaint?.description ?? '';
    complaintForm.status = complaint
        ? complaint.status === 'new'
            ? 'handling'
            : 'resolved'
        : 'new';
    complaintOpen.value = true;
    taskOpen.value = false;
    estimateOpen.value = false;
    error.value = '';
};
const choosePhotos = (event: Event, kind: 'task' | 'complaint'): void => {
    if (!(event.target instanceof HTMLInputElement)) {
        return;
    }
    const photos = Array.from(event.target.files ?? []);
    if (kind === 'task') {
        taskForm.photos = photos;
    } else {
        complaintForm.photos = photos;
    }
};
const save = async (kind: 'task' | 'complaint' | 'estimate'): Promise<void> => {
    if (busy.value) {
        return;
    }
    error.value = '';
    success.value = '';
    const form = kind === 'task' ? taskForm : kind === 'complaint' ? complaintForm : estimateForm;
    const url =
        kind === 'task'
            ? taskRoute(routeParams.value).url
            : kind === 'complaint'
              ? complaintRoute(routeParams.value).url
              : estimateRoute(routeParams.value).url;
    if (kind === 'task') {
        taskForm.transform((data) => ({
            ...data,
            scheduled_at: data.scheduled_at ? new Date(data.scheduled_at).toISOString() : null,
        }));
    }
    if (kind === 'estimate') {
        estimateForm.transform((data) => ({
            ...data,
            estimated_ready_at: new Date(data.estimated_ready_at).toISOString(),
        }));
    }
    try {
        const response = await form.post(url, {
            onHttpException: (response) => {
                error.value = messageFromResponseData(response.data, 'Data tidak dapat disimpan.');
            },
        });
        if (!response) {
            error.value = firstRequestError(
                form.errors,
                error.value || 'Periksa data dan coba lagi.',
            );
            return;
        }
        localOrder.value = response.data;
        emit('saved', response.data);
        taskOpen.value = false;
        complaintOpen.value = false;
        estimateOpen.value = false;
        success.value = 'Perubahan tersimpan.';
    } catch (exception: unknown) {
        error.value = firstRequestError(
            form.errors,
            error.value ||
                (exception instanceof Error ? exception.message : 'Data tidak dapat disimpan.'),
        );
    }
};
</script>

<template>
    <section class="space-y-4" aria-label="Operasional pesanan">
        <div class="rounded-xl bg-surface-blue p-4">
            <p class="text-xs font-semibold text-ink-500">Estimasi selesai</p>
            <p class="mt-1 font-semibold" :class="order.is_overdue ? 'text-red-600' : 'text-brand-700'">
                {{ order.estimated_ready_at ? formatOrderDate(order.estimated_ready_at) : 'Setelah konfirmasi berat & harga' }}
            </p>
            <p v-if="order.is_overdue" class="mt-1 text-xs text-red-600">Melewati estimasi, perlu tindak lanjut.</p>
            <Button v-if="staff && order.pricing.confirmed && !terminal" variant="outline" class="mt-3 min-h-11" :disabled="busy" @click="estimateOpen = !estimateOpen; taskOpen = false; complaintOpen = false">Ubah estimasi</Button>
        </div>
        <form v-if="estimateOpen" class="space-y-3 rounded-xl border border-line p-4" @submit.prevent="save('estimate')">
            <label class="block text-sm font-medium">Tanggal & waktu baru<input v-model="estimateForm.estimated_ready_at" type="datetime-local" required class="mt-1 min-h-11 w-full rounded-lg border border-line px-3" /></label>
            <label class="block text-sm font-medium">Alasan perubahan<textarea v-model="estimateForm.reason" required maxlength="300" class="mt-1 min-h-20 w-full rounded-lg border border-line p-3" /></label>
            <Button :disabled="busy" type="submit">Simpan estimasi</Button>
        </form>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="font-display font-semibold text-ink-950">Jemput & antar</h3>
            <Button v-if="staff && !terminal" variant="outline" :disabled="busy || order.tasks?.some(task => task.kind === 'delivery' && !['completed', 'cancelled'].includes(task.status))" @click="openTask()">Jadwalkan antar</Button>
        </div>
        <p v-if="!order.tasks?.length" class="text-sm text-ink-500">Belum ada tugas antar-jemput.</p>
        <article v-for="task in order.tasks" :key="task.id" class="space-y-2 rounded-xl border border-line p-3">
            <p class="flex flex-wrap justify-between gap-2 text-sm font-semibold"><span>{{ task.kind === 'pickup' ? 'Penjemputan' : 'Pengantaran' }}</span><span class="text-brand-600">{{ taskLabels[task.status] }}</span></p>
            <p v-if="task.operator_name" class="text-sm text-ink-700">Petugas: {{ task.operator_name }}</p>
            <p v-if="task.scheduled_at" class="text-xs text-ink-500">{{ formatOrderDate(task.scheduled_at) }}</p>
            <p class="wrap-anywhere text-sm text-ink-700">{{ task.address }}</p>
            <p v-if="task.recipient_name" class="text-sm text-ink-700">Penerima: {{ task.recipient_name }}</p>
            <p v-if="task.note" class="text-sm text-ink-700">{{ task.note }}</p>
            <div class="flex flex-wrap gap-2"><a v-for="photo in task.evidence" :key="photo.id" :href="photo.url" target="_blank" rel="noopener noreferrer" class="rounded-lg focus-visible:ring-2"><img :src="photo.url" :alt="'Bukti ' + (task.kind === 'pickup' ? 'jemput' : 'antar')" loading="lazy" class="size-20 rounded-lg object-cover" /></a></div>
            <details v-if="task.history.length" class="text-xs text-ink-500"><summary class="min-h-11 cursor-pointer py-3">Riwayat tugas</summary><ol class="space-y-2"><li v-for="event in task.history" :key="event.id">{{ formatOrderDate(event.occurred_at) }} · {{ event.actor_name }} · {{ taskStatusLabel(event.status) }}<p v-if="event.note" class="text-ink-700">{{ event.note }}</p></li></ol></details>
            <Button v-if="staff && !terminal && !['completed', 'cancelled'].includes(task.status)" variant="outline" :disabled="busy" @click="openTask(task)">Perbarui tugas</Button>
        </article>
        <form v-if="taskOpen" class="space-y-3 rounded-xl bg-canvas p-4" @submit.prevent="save('task')">
            <h4 class="font-semibold text-brand-700">{{ taskForm.kind === 'pickup' ? 'Penjemputan' : 'Pengantaran' }}</h4>
            <label class="block text-sm font-medium">Progres<select v-model="taskForm.status" class="mt-1 min-h-11 w-full rounded-lg border border-line px-3"><option v-for="status in taskOptions" :key="status" :value="status">{{ taskLabels[status] }}</option></select></label>
            <p v-if="taskForm.kind === 'delivery' && order.status !== 'ready_for_pickup'" class="text-xs text-ink-500">Boleh dijadwalkan sekarang. Berangkat setelah laundry siap.</p>
            <template v-if="taskForm.status === 'scheduled'">
                <label class="block text-sm font-medium">Nama petugas<input v-model="taskForm.operator_name" required maxlength="120" class="mt-1 min-h-11 w-full rounded-lg border border-line px-3" /></label>
                <label class="block text-sm font-medium">Jadwal<input v-model="taskForm.scheduled_at" required type="datetime-local" class="mt-1 min-h-11 w-full rounded-lg border border-line px-3" /></label>
                <label class="block text-sm font-medium">Alamat<textarea v-model="taskForm.address" required maxlength="2000" class="mt-1 min-h-20 w-full rounded-lg border border-line p-3" /></label>
            </template>
            <label v-if="taskForm.status === 'completed'" class="block text-sm font-medium">Nama penerima<input v-model="taskForm.recipient_name" required maxlength="120" class="mt-1 min-h-11 w-full rounded-lg border border-line px-3" /></label>
            <label class="block text-sm font-medium">{{ taskForm.status === 'failed' ? 'Alasan gagal' : 'Catatan' }}<textarea v-model="taskForm.note" :required="taskForm.status === 'failed'" maxlength="2000" class="mt-1 min-h-20 w-full rounded-lg border border-line p-3" /></label>
            <label class="block text-sm font-medium">Foto bukti (opsional, maks. 3 × 5 MB)<input type="file" accept="image/jpeg,image/png,image/webp" multiple class="mt-2 block min-h-11 w-full text-xs" @change="choosePhotos($event, 'task')" /></label>
            <div class="flex gap-2"><Button type="submit" :disabled="busy">{{ taskForm.processing ? 'Menyimpan...' : 'Simpan progres' }}</Button><Button type="button" variant="ghost" :disabled="busy" @click="taskOpen = false">Tutup</Button></div>
        </form>
        <div class="flex flex-wrap items-center justify-between gap-2"><h3 class="font-display font-semibold text-ink-950">Komplain & tindak lanjut</h3><Button v-if="!activeComplaint" variant="outline" :disabled="busy" @click="openComplaint()">Ajukan komplain</Button></div>
        <p v-if="!order.complaints?.length" class="text-sm text-ink-500">Belum ada komplain untuk pesanan ini.</p>
        <article v-for="complaint in order.complaints" :key="complaint.id" class="space-y-2 rounded-xl border border-line p-3">
            <p class="flex flex-wrap justify-between gap-2 text-sm font-semibold"><span>{{ categoryLabels[complaint.category] }}</span><span class="text-brand-600">{{ complaintLabels[complaint.status] }}</span></p>
            <p class="wrap-anywhere text-sm text-ink-700">{{ complaint.description }}</p>
            <p v-if="complaint.resolution_note" class="rounded-lg bg-brand-100 p-3 text-sm text-brand-700">Solusi: {{ complaint.resolution_note }}</p>
            <div class="flex flex-wrap gap-2"><a v-for="photo in complaint.evidence" :key="photo.id" :href="photo.url" target="_blank" rel="noopener noreferrer"><img :src="photo.url" alt="Bukti komplain" loading="lazy" class="size-20 rounded-lg object-cover" /></a></div>
            <ol class="space-y-2 text-xs text-ink-500"><li v-for="event in complaint.history" :key="event.id">{{ formatOrderDate(event.occurred_at) }} · {{ event.actor_name }} · {{ event.status === 'new' ? 'Baru' : event.status === 'handling' ? 'Sedang ditangani' : 'Selesai' }}<p v-if="event.note" class="mt-1 text-sm text-ink-700">{{ event.note }}</p></li></ol>
            <Button v-if="staff && complaint.status !== 'resolved'" variant="outline" :disabled="busy" @click="openComplaint(complaint)">Tindak lanjuti</Button>
        </article>
        <form v-if="complaintOpen" class="space-y-3 rounded-xl bg-canvas p-4" @submit.prevent="save('complaint')">
            <template v-if="!complaintForm.complaint_id"><label class="block text-sm font-medium">Kategori<select v-model="complaintForm.category" class="mt-1 min-h-11 w-full rounded-lg border border-line px-3"><option v-for="(label, value) in categoryLabels" :key="value" :value="value">{{ label }}</option></select></label><label class="block text-sm font-medium">Jelaskan kendala<textarea v-model="complaintForm.description" required maxlength="4000" class="mt-1 min-h-24 w-full rounded-lg border border-line p-3" /></label></template>
            <template v-else><label class="block text-sm font-medium">Progres<select v-model="complaintForm.status" class="mt-1 min-h-11 w-full rounded-lg border border-line px-3"><option value="handling">Sedang ditangani</option><option v-if="complaintCanResolve" value="resolved">Selesai</option></select></label><label class="block text-sm font-medium">{{ complaintForm.status === 'resolved' ? 'Solusi / hasil penyelesaian' : 'Catatan tindak lanjut' }}<textarea v-model="complaintForm.note" required maxlength="4000" class="mt-1 min-h-24 w-full rounded-lg border border-line p-3" /></label></template>
            <label class="block text-sm font-medium">Foto bukti (opsional, maks. 3 × 5 MB)<input type="file" accept="image/jpeg,image/png,image/webp" multiple class="mt-2 block min-h-11 w-full text-xs" @change="choosePhotos($event, 'complaint')" /></label>
            <div class="flex gap-2"><Button type="submit" :disabled="busy">{{ complaintForm.processing ? 'Menyimpan...' : 'Simpan komplain' }}</Button><Button type="button" variant="ghost" :disabled="busy" @click="complaintOpen = false">Tutup</Button></div>
        </form>
        <p v-if="error" role="alert" class="rounded-xl bg-red-600/5 p-3 text-sm text-red-600">{{ error }}</p>
        <p v-if="success" role="status" class="text-sm text-brand-600">{{ success }}</p>
    </section>
</template>
