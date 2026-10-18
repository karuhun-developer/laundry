<script setup lang="ts">
import { AlertCircle, CheckCircle2, FileSpreadsheet, LoaderCircle, Upload } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { importMethod as ordersImport, template as ordersTemplate } from '@/routes/cms/orders';
import { status as ordersImportStatus } from '@/routes/cms/orders/import';

type ImportState = 'idle' | 'uploading' | 'processing' | 'completed' | 'failed';

interface ImportProgress {
    id: string;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    total_rows: number;
    processed_rows: number;
    imported: number;
    errors: string[];
    error_count: number;
}

const emit = defineEmits<{
    (e: 'imported'): void;
}>();

const open = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);
const fileError = ref<string | null>(null);

const state = ref<ImportState>('idle');
const progress = ref<ImportProgress>({
    id: '',
    status: 'pending',
    total_rows: 0,
    processed_rows: 0,
    imported: 0,
    errors: [],
    error_count: 0,
});
const errorMessage = ref<string>('');
let pollInterval: ReturnType<typeof setInterval> | null = null;

const progressPercent = computed<number>(() => {
    if (state.value === 'completed') return 100;
    if (progress.value.total_rows <= 0) return 0;
    return Math.min(100, Math.round((progress.value.processed_rows / progress.value.total_rows) * 100));
});

const getCsrfToken = (): string =>
    typeof document !== 'undefined'
        ? document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
        : '';

const handleFileChange = (event: Event): void => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        selectedFile.value = target.files[0];
        fileError.value = null;
    } else {
        selectedFile.value = null;
    }
};

const stopPolling = (): void => {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
};

const checkStatus = async (importId: string): Promise<void> => {
    try {
        const response = await fetch(ordersImportStatus(importId).url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            return;
        }

        const data = (await response.json()) as ImportProgress;
        progress.value = data;

        if (data.status === 'completed') {
            stopPolling();
            state.value = 'completed';
            emit('imported');
        } else if (data.status === 'failed') {
            stopPolling();
            state.value = 'failed';
            errorMessage.value = data.errors[0] ?? 'Terjadi kesalahan saat memproses impor.';
        }
    } catch {
        // Silently retry on polling network errors
    }
};

const submit = async (): Promise<void> => {
    if (!selectedFile.value) {
        fileError.value = 'Pilih berkas CSV terlebih dahulu.';
        return;
    }

    state.value = 'uploading';
    fileError.value = null;

    try {
        const formData = new FormData();
        formData.append('file', selectedFile.value);

        const response = await fetch(ordersImport().url, {
            method: 'POST',
            body: formData,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
        });

        if (!response.ok) {
            const err = await response.json().catch(() => ({}));
            state.value = 'failed';
            errorMessage.value = err.message ?? 'Gagal mengunggah berkas CSV.';
            return;
        }

        const data = (await response.json()) as { import_id: string; total_rows: number };
        progress.value = {
            id: data.import_id,
            status: 'pending',
            total_rows: data.total_rows,
            processed_rows: 0,
            imported: 0,
            errors: [],
            error_count: 0,
        };

        state.value = 'processing';

        // Start polling every 1 second
        pollInterval = setInterval(() => {
            void checkStatus(data.import_id);
        }, 1000);

        // Immediate first check
        void checkStatus(data.import_id);
    } catch (err: unknown) {
        state.value = 'failed';
        errorMessage.value = err instanceof Error ? err.message : 'Gagal menghubungi server.';
    }
};

const resetState = (): void => {
    stopPolling();
    state.value = 'idle';
    selectedFile.value = null;
    fileError.value = null;
    errorMessage.value = '';
    progress.value = {
        id: '',
        status: 'pending',
        total_rows: 0,
        processed_rows: 0,
        imported: 0,
        errors: [],
        error_count: 0,
    };
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

watch(open, (isOpen) => {
    if (!isOpen) {
        if (state.value === 'completed' || state.value === 'failed') {
            resetState();
        } else {
            stopPolling();
        }
    }
});

onBeforeUnmount(() => {
    stopPolling();
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="outline" class="gap-2" data-test="open-order-import-dialog">
                <Upload class="size-4" />
                Import CSV
            </Button>
        </DialogTrigger>

        <DialogContent class="max-w-lg">
            <div class="space-y-6">
                <DialogHeader class="pr-11 text-left">
                    <span class="bg-brand-100 text-brand-700 mb-2 grid size-11 place-items-center rounded-xl ring-1 ring-brand-600/10">
                        <Upload class="size-5" />
                    </span>
                    <DialogTitle>Import Data Pesanan</DialogTitle>
                    <DialogDescription>
                        Unggah berkas CSV untuk mengimpor data transaksi pesanan pelanggan secara massal via background queue.
                    </DialogDescription>
                </DialogHeader>

                <!-- 1. Idle Form State -->
                <form
                    v-if="state === 'idle'"
                    class="space-y-4"
                    data-test="order-import-dialog"
                    @submit.prevent="submit"
                >
                    <div class="rounded-xl border border-line bg-muted/40 p-4 text-xs text-ink-600 space-y-2">
                        <p class="font-medium text-ink-900">Petunjuk Import Pesanan:</p>
                        <ul class="list-disc pl-4 space-y-1">
                            <li>Gunakan format template CSV yang telah disediakan.</li>
                            <li>Nomor HP dapat berupa format angka maupun berstrip (contoh: <code>0812-8921-3041</code>). Tanda <code>-</code> menandakan nomor HP kosong.</li>
                            <li>Pelanggan baru otomatis dibuatkan akun dengan PIN default <strong>676767</strong> (jika nomor HP ada).</li>
                            <li>Pesanan berstatus <strong>lunas</strong> otomatis mencatat riwayat pembayaran dan arus kas.</li>
                            <li>Data diproses di latar belakang sehingga aman untuk berkas bervolume besar.</li>
                        </ul>
                        <div class="pt-2">
                            <a
                                :href="ordersTemplate().url"
                                class="inline-flex items-center gap-1.5 font-medium text-brand-600 hover:text-brand-700 underline"
                                download
                            >
                                <FileSpreadsheet class="size-4" />
                                Unduh Template CSV Pesanan
                            </a>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="order-csv-file">Pilih Berkas CSV</Label>
                        <input
                            id="order-csv-file"
                            ref="fileInput"
                            type="file"
                            accept=".csv,text/csv,text/plain"
                            class="border-input focus-visible:border-ring focus-visible:ring-ring/50 file:text-foreground flex h-10 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 disabled:cursor-not-allowed disabled:opacity-50"
                            required
                            @change="handleFileChange"
                        />
                        <p v-if="fileError" class="text-xs text-destructive">
                            {{ fileError }}
                        </p>
                    </div>

                    <DialogFooter class="gap-2 pt-2">
                        <DialogClose as-child>
                            <Button type="button" variant="secondary">Batal</Button>
                        </DialogClose>
                        <Button type="submit" :disabled="!selectedFile" data-test="submit-order-import">
                            Import Pesanan
                        </Button>
                    </DialogFooter>
                </form>

                <!-- 2. Uploading & Processing State -->
                <div v-else-if="state === 'uploading' || state === 'processing'" class="space-y-5 py-2">
                    <div class="flex items-center gap-3">
                        <LoaderCircle class="size-5 animate-spin text-brand-600 shrink-0" />
                        <div>
                            <p class="text-sm font-medium text-ink-900">
                                {{ state === 'uploading' ? 'Mengunggah berkas CSV...' : `Memproses ${progress.processed_rows} dari ${progress.total_rows} pesanan...` }}
                            </p>
                            <p class="text-xs text-ink-500">
                                Berjalan di background queue. Anda dapat menutup dialog ini dan proses tetap berjalan.
                            </p>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <div class="flex justify-between text-xs font-semibold text-ink-600">
                            <span>Progress</span>
                            <span>{{ progressPercent }}%</span>
                        </div>
                        <div class="h-2.5 w-full overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full bg-brand-600 transition-all duration-300"
                                :style="{ width: `${progressPercent}%` }"
                            />
                        </div>
                    </div>

                    <DialogFooter class="pt-2">
                        <DialogClose as-child>
                            <Button type="button" variant="secondary">
                                Jalankan di Latar Belakang (Tutup)
                            </Button>
                        </DialogClose>
                    </DialogFooter>
                </div>

                <!-- 3. Completed State -->
                <div v-else-if="state === 'completed'" class="space-y-4 py-2">
                    <div class="flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-4 dark:border-emerald-900/50 dark:bg-emerald-950/20">
                        <CheckCircle2 class="size-5 text-emerald-600 mt-0.5 shrink-0" />
                        <div class="space-y-1">
                            <p class="text-sm font-semibold text-emerald-900 dark:text-emerald-200">
                                Impor Selesai!
                            </p>
                            <p class="text-xs text-emerald-800 dark:text-emerald-300">
                                Berhasil mengimpor <strong>{{ progress.imported }}</strong> data pesanan.
                            </p>
                        </div>
                    </div>

                    <div v-if="progress.error_count > 0" class="space-y-2 rounded-lg border border-amber-200 bg-amber-50/50 p-3 text-xs text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-200">
                        <p class="font-semibold">Terdapat {{ progress.error_count }} kendala pada baris:</p>
                        <ul class="list-disc pl-4 space-y-0.5 max-h-32 overflow-y-auto">
                            <li v-for="(err, idx) in progress.errors" :key="idx">
                                {{ err }}
                            </li>
                        </ul>
                    </div>

                    <DialogFooter class="pt-2">
                        <Button type="button" @click="resetState(); open = false;">
                            Selesai
                        </Button>
                    </DialogFooter>
                </div>

                <!-- 4. Failed State -->
                <div v-else-if="state === 'failed'" class="space-y-4 py-2">
                    <div class="flex items-start gap-3 rounded-lg border border-destructive/20 bg-destructive/10 p-4">
                        <AlertCircle class="size-5 text-destructive mt-0.5 shrink-0" />
                        <div class="space-y-1">
                            <p class="text-sm font-semibold text-destructive">
                                Impor Gagal
                            </p>
                            <p class="text-xs text-destructive">
                                {{ errorMessage }}
                            </p>
                        </div>
                    </div>

                    <DialogFooter class="gap-2 pt-2">
                        <Button type="button" variant="secondary" @click="resetState(); open = false;">
                            Tutup
                        </Button>
                        <Button type="button" @click="resetState">
                            Coba Lagi
                        </Button>
                    </DialogFooter>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
