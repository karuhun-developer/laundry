<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type DeleteRoute = {
    url: string;
    method: 'delete';
};

const props = defineProps<{
    title: string;
    description: string;
    confirmation: string;
    confirmationLabel: string;
    route: DeleteRoute;
    disabled?: boolean;
}>();

const open = ref(false);
const form = useForm({ confirmation: '' });
const confirmationMatches = computed(() => form.confirmation === props.confirmation);

watch(open, (isOpen) => {
    if (!isOpen) {
        form.reset();
        form.clearErrors();
    }
});

const submit = (): void => {
    if (!confirmationMatches.value) {
        return;
    }

    form.submit(props.route, {
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
                :disabled="disabled"
                :aria-label="`Hapus ${confirmation}`"
                data-test="open-delete-dialog"
            >
                <Trash2 />
                <span class="hidden xl:inline">Hapus</span>
            </Button>
        </DialogTrigger>
        <DialogContent>
            <form class="space-y-6" data-test="delete-form" @submit.prevent="submit">
                <DialogHeader class="space-y-3">
                    <DialogTitle>{{ title }}</DialogTitle>
                    <DialogDescription>{{ description }}</DialogDescription>
                </DialogHeader>

                <div class="border-destructive/30 bg-destructive/10 text-destructive rounded-lg border p-3 text-sm">
                    Tindakan ini tidak dapat dibatalkan. Ketik
                    <strong>{{ confirmation }}</strong> untuk melanjutkan.
                </div>

                <div class="space-y-2">
                    <Label for="delete-confirmation">{{ confirmationLabel }}</Label>
                    <Input
                        id="delete-confirmation"
                        v-model="form.confirmation"
                        autocomplete="off"
                        :placeholder="confirmation"
                        :aria-invalid="Boolean(form.errors.confirmation)"
                        aria-describedby="delete-confirmation-error"
                        data-test="delete-confirmation"
                    />
                    <InputError id="delete-confirmation-error" :message="form.errors.confirmation" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">Batal</Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="form.processing || !confirmationMatches"
                        data-test="confirm-delete"
                    >
                        <LoaderCircle v-if="form.processing" class="animate-spin" />
                        <Trash2 v-else />
                        {{ form.processing ? 'Menghapus...' : 'Hapus permanen' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
