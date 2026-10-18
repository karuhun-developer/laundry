<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { FileSpreadsheet, Upload } from '@lucide/vue';
import { ref } from 'vue';
import CmsFormDialog from '@/components/cms/CmsFormDialog.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { importMethod as cashflowImport, template as cashflowTemplate } from '@/routes/cms/cashflow';

const open = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

const form = useForm<{
    file: File | null;
}>({
    file: null,
});

const handleFileChange = (event: Event): void => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        form.file = target.files[0];
    } else {
        form.file = null;
    }
};

const submit = (): void => {
    if (!form.file) return;

    form.post(cashflowImport().url, {
        onSuccess: () => {
            open.value = false;
            form.reset();
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
};
</script>

<template>
    <CmsFormDialog
        v-model:open="open"
        title="Import Data Cashflow"
        description="Unggah berkas CSV untuk mengimpor data arus kas pengeluaran atau pemasukan secara massal."
        form-test="cashflow-import-dialog"
        :processing="form.processing"
        submit-label="Import Cashflow"
        :submit-disabled="!form.file"
        content-class="max-w-lg"
        @submit="submit"
    >
        <template #trigger>
            <Button variant="outline" class="gap-2">
                <Upload class="size-4" />
                Import CSV
            </Button>
        </template>

        <template #icon>
            <Upload class="size-5" />
        </template>

        <div class="space-y-4">
            <div class="rounded-xl border border-line bg-muted/40 p-4 text-xs text-ink-600 space-y-2">
                <p class="font-medium text-ink-900">Petunjuk Import Cashflow:</p>
                <ul class="list-disc pl-4 space-y-1">
                    <li>Gunakan format template CSV yang telah disediakan.</li>
                    <li>Kolom tipe diisi <strong>pengeluaran</strong> atau <strong>pemasukan</strong>.</li>
                    <li>Nama cabang dan kategori yang belum terdaftar akan dibuat secara otomatis.</li>
                    <li>Format jumlah nominal berupa angka rupiah.</li>
                </ul>
                <div class="pt-2">
                    <a
                        :href="cashflowTemplate().url"
                        class="inline-flex items-center gap-1.5 font-medium text-brand-600 hover:text-brand-700 underline"
                        download
                    >
                        <FileSpreadsheet class="size-4" />
                        Unduh Template CSV Cashflow
                    </a>
                </div>
            </div>

            <div class="space-y-2">
                <Label for="cashflow-csv-file">Pilih Berkas CSV</Label>
                <input
                    id="cashflow-csv-file"
                    ref="fileInput"
                    type="file"
                    accept=".csv,text/csv,text/plain"
                    class="border-input focus-visible:border-ring focus-visible:ring-ring/50 file:text-foreground flex h-10 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 disabled:cursor-not-allowed disabled:opacity-50"
                    required
                    @change="handleFileChange"
                />
                <p v-if="form.errors.file" class="text-xs text-destructive">
                    {{ form.errors.file }}
                </p>
            </div>
        </div>
    </CmsFormDialog>
</template>
