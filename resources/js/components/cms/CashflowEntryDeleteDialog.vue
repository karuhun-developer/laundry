<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
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
import { destroy } from '@/routes/cms/cashflow';
import type { CashflowEntrySummary } from '@/types';

const props = defineProps<{ entry: CashflowEntrySummary }>();
const open = ref(false);
const form = useForm({});

const submit = (): void => {
    form.delete(destroy(props.entry.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="text-destructive hover:bg-destructive/10 hover:text-destructive min-h-11 min-w-11"
                :aria-label="'Hapus entri ' + entry.description"
                data-test="open-delete-cashflow-entry"
            >
                <Trash2 />
                <span class="hidden xl:inline">Hapus</span>
            </Button>
        </DialogTrigger>
        <DialogContent>
            <form
                class="space-y-6"
                data-test="delete-cashflow-entry-form"
                @submit.prevent="submit"
            >
                <DialogHeader class="space-y-3">
                    <DialogTitle>Hapus entri cashflow?</DialogTitle>
                    <DialogDescription>
                        Apakah Anda yakin ingin menghapus <strong>{{ entry.description }}</strong>?
                        Tindakan ini tidak dapat dibatalkan.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary" :disabled="form.processing">
                            Batal
                        </Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="form.processing"
                        data-test="confirm-delete-cashflow-entry"
                    >
                        <LoaderCircle v-if="form.processing" class="animate-spin" />
                        <Trash2 v-else />
                        {{ form.processing ? 'Menghapus...' : 'Ya, Hapus' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
