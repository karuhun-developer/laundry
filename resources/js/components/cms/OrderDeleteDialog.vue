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
import { destroy as destroyOrder } from '@/routes/cms/orders';

const props = defineProps<{
    order: {
        id: number;
        order_number: string;
    };
    disabled?: boolean;
}>();

const emit = defineEmits<{
    (e: 'deleted'): void;
}>();

const open = ref(false);
const form = useForm({});

const submit = (): void => {
    form.delete(destroyOrder(props.order.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            emit('deleted');
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
                :disabled="disabled"
                :aria-label="`Hapus pesanan ${order.order_number}`"
                data-test="open-delete-order-dialog"
            >
                <Trash2 />
                <span class="hidden xl:inline">Hapus</span>
            </Button>
        </DialogTrigger>
        <DialogContent>
            <form class="space-y-6" data-test="delete-order-form" @submit.prevent="submit">
                <DialogHeader class="space-y-3">
                    <DialogTitle>Hapus pesanan?</DialogTitle>
                    <DialogDescription>
                        Apakah Anda yakin ingin menghapus pesanan <strong>#{{ order.order_number }}</strong>? Tindakan ini tidak dapat dibatalkan.
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
                        data-test="confirm-delete-order"
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
