<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { MessageSquareText, Pencil, Plus } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import CmsFormDialog from '@/components/cms/CmsFormDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/cms/message-templates';
import type {
    BranchSummary,
    MessageTemplateSummary,
    MessageTemplatesPageProps,
    OrderStatusOption,
    OrganizationOption,
} from '@/types';

const props = defineProps<{
    messageTemplate?: MessageTemplateSummary;
    branches: Pick<BranchSummary, 'id' | 'organization_id' | 'name'>[];
    organizations: OrganizationOption[];
    orderStatuses: OrderStatusOption[];
    supportedVariables: string[];
    currentOrganizationId: number | null;
    isSuperadmin: boolean;
}>();

const previewValues: Readonly<Record<string, string>> = {
    '{customer_name}': 'Budi Santoso',
    '{name}': 'Budi Santoso',
    '{order_number}': 'LDR-20260913-00000001',
    '{status}': 'Siap diambil',
    '{branch_name}': 'Cabang Kemang',
    '{tracking_url}': 'https://laundryhub.test/app?order=1',
};

const open = ref(false);
const bodyInput = ref<HTMLTextAreaElement | null>(null);
const isEditing = computed(() => props.messageTemplate !== undefined);
const defaultOrganizationId = (): string =>
    (
        props.messageTemplate?.organization_id ??
        props.currentOrganizationId ??
        props.organizations[0]?.id
    )?.toString() ?? '';
const form = useForm({
    organization_id: defaultOrganizationId(),
    branch_id: props.messageTemplate?.branch_id?.toString() ?? '',
    order_status: props.messageTemplate?.order_status ?? props.orderStatuses[0]?.value ?? '',
    body: props.messageTemplate?.body ?? '',
    is_active: props.messageTemplate?.is_active ?? true,
});

const selectedOrganizationId = computed<number | null>(() => {
    if (props.messageTemplate) {
        return props.messageTemplate.organization_id;
    }

    if (props.currentOrganizationId !== null) {
        return props.currentOrganizationId;
    }

    return form.organization_id === '' ? null : Number(form.organization_id);
});
const availableBranches = computed(() =>
    props.branches.filter((branch) => branch.organization_id === selectedOrganizationId.value),
);
const preview = computed(() =>
    Object.entries(previewValues).reduce(
        (message, [variable, value]) => message.replaceAll(variable, value),
        form.body,
    ),
);

const restoreValues = (): void => {
    form.organization_id = defaultOrganizationId();
    form.branch_id = props.messageTemplate?.branch_id?.toString() ?? '';
    form.order_status = props.messageTemplate?.order_status ?? props.orderStatuses[0]?.value ?? '';
    form.body = props.messageTemplate?.body ?? '';
    form.is_active = props.messageTemplate?.is_active ?? true;
    form.clearErrors();
};

watch(open, (isOpen) => {
    if (isOpen) {
        restoreValues();
    }
});
watch(selectedOrganizationId, () => {
    if (!isEditing.value) {
        form.branch_id = '';
    }
});

const insertVariable = async (variable: string): Promise<void> => {
    const input = bodyInput.value;
    const start = input?.selectionStart ?? form.body.length;
    const end = input?.selectionEnd ?? start;
    form.body = `${form.body.slice(0, start)}${variable}${form.body.slice(end)}`;

    await nextTick();
    bodyInput.value?.focus();
    bodyInput.value?.setSelectionRange(start + variable.length, start + variable.length);
};

const submit = (): void => {
    const organizationId = selectedOrganizationId.value;

    if (organizationId === null) {
        form.setError('organization_id', 'Pilih organisasi terlebih dahulu.');
        return;
    }

    const branch = availableBranches.value.find((item) => item.id.toString() === form.branch_id);
    const organization = props.messageTemplate?.organization ??
        props.organizations.find((item) => item.id === organizationId) ?? {
            id: organizationId,
            name: 'Organisasi saya',
        };
    const pending: MessageTemplateSummary = {
        id: props.messageTemplate?.id ?? -Date.now(),
        organization_id: organizationId,
        branch_id: branch?.id ?? null,
        scope_key: branch ? `branch:${branch.id}` : 'organization',
        order_status: form.order_status,
        body: form.body,
        is_active: form.is_active,
        organization,
        branch: branch ? { id: branch.id, name: branch.name } : null,
        pending: true,
    };

    if (props.messageTemplate) {
        form.optimistic<MessageTemplatesPageProps>((page) => ({
            messageTemplates: page.messageTemplates.map((template) =>
                template.id === props.messageTemplate?.id ? pending : template,
            ),
        })).submit(update(props.messageTemplate.id), {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
        });

        return;
    }

    form.optimistic<MessageTemplatesPageProps>((page) => ({
        messageTemplates: [pending, ...page.messageTemplates],
    })).submit(store(), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
};
</script>

<template>
    <CmsFormDialog
        v-model:open="open"
        form-test="message-template-form"
        :title="isEditing ? 'Edit template WhatsApp' : 'Template WhatsApp baru'"
        description="Atur pesan per status untuk seluruh organisasi atau satu cabang tertentu."
        :processing="form.processing"
        :submit-label="isEditing ? 'Simpan perubahan' : 'Tambah template'"
        content-class="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-3xl"
        @submit="submit"
    >
        <template #trigger>
            <Button
                type="button"
                :variant="isEditing ? 'ghost' : 'default'"
                :size="isEditing ? 'sm' : 'default'"
                :class="isEditing ? 'min-h-11 min-w-11' : undefined"
                :disabled="messageTemplate?.pending"
                :aria-label="isEditing ? `Edit ${messageTemplate?.scope_key}` : undefined"
                data-test="open-message-template-dialog"
            >
                <Pencil v-if="isEditing" />
                <Plus v-else />
                <span :class="isEditing ? 'hidden xl:inline' : ''">
                    {{ isEditing ? 'Edit' : 'Tambah template' }}
                </span>
            </Button>
        </template>
        <template #icon><MessageSquareText class="size-5" /></template>

        <div v-if="isSuperadmin" class="space-y-2">
            <Label for="message-template-organization">Organisasi</Label>
            <select
                id="message-template-organization"
                v-model="form.organization_id"
                class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-11 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-3 disabled:opacity-50"
                :disabled="isEditing"
                required
                :aria-invalid="Boolean(form.errors.organization_id)"
                aria-describedby="message-template-organization-error"
            >
                <option value="" disabled>Pilih organisasi</option>
                <option
                    v-for="organization in organizations"
                    :key="organization.id"
                    :value="organization.id.toString()"
                >
                    {{ organization.name }}
                </option>
            </select>
            <InputError
                id="message-template-organization-error"
                :message="form.errors.organization_id"
            />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label for="message-template-branch">Cakupan</Label>
                <select
                    id="message-template-branch"
                    v-model="form.branch_id"
                    class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-11 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-3"
                    :aria-invalid="Boolean(form.errors.branch_id)"
                    aria-describedby="message-template-branch-error"
                >
                    <option value="">Seluruh organisasi</option>
                    <option
                        v-for="branch in availableBranches"
                        :key="branch.id"
                        :value="branch.id.toString()"
                    >
                        {{ branch.name }}
                    </option>
                </select>
                <InputError id="message-template-branch-error" :message="form.errors.branch_id" />
            </div>

            <div class="space-y-2">
                <Label for="message-template-status">Status pesanan</Label>
                <select
                    id="message-template-status"
                    v-model="form.order_status"
                    class="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-11 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-3"
                    required
                    :aria-invalid="Boolean(form.errors.order_status)"
                    aria-describedby="message-template-status-error"
                >
                    <option
                        v-for="status in orderStatuses"
                        :key="status.value"
                        :value="status.value"
                    >
                        {{ status.label }}
                    </option>
                </select>
                <InputError
                    id="message-template-status-error"
                    :message="form.errors.order_status"
                />
            </div>
        </div>

        <div class="space-y-2">
            <Label for="message-template-body">Isi pesan</Label>
            <div class="flex flex-wrap gap-2" aria-label="Variabel template">
                <button
                    v-for="variable in supportedVariables"
                    :key="variable"
                    type="button"
                    class="border-line bg-canvas text-brand-700 min-h-9 rounded-lg border px-2.5 font-mono text-xs font-semibold hover:border-brand-300"
                    @click="insertVariable(variable)"
                >
                    {{ variable }}
                </button>
            </div>
            <textarea
                id="message-template-body"
                ref="bodyInput"
                v-model="form.body"
                class="border-input focus-visible:border-ring focus-visible:ring-ring/50 min-h-36 w-full resize-y rounded-md border bg-transparent px-3 py-2 text-sm leading-6 shadow-xs outline-none focus-visible:ring-3"
                placeholder="Halo {customer_name}, pesanan {order_number} sudah {status}."
                required
                :aria-invalid="Boolean(form.errors.body)"
                aria-describedby="message-template-body-error"
            />
            <InputError id="message-template-body-error" :message="form.errors.body" />
        </div>

        <div class="border-line bg-canvas rounded-xl border p-4">
            <p class="text-ink-500 text-xs font-semibold tracking-[0.14em] uppercase">Preview</p>
            <p class="text-ink-800 mt-2 whitespace-pre-wrap text-sm leading-6">
                {{ preview || 'Isi pesan akan tampil di sini.' }}
            </p>
        </div>

        <label class="border-line flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border px-3 py-2">
            <Checkbox v-model="form.is_active" />
            <span class="text-sm font-medium">Template aktif dan dapat dipakai admin cabang</span>
        </label>
    </CmsFormDialog>
</template>
