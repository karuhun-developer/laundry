<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { Building2, CircleCheckBig, MessageSquareText } from '@lucide/vue';
import { computed } from 'vue';
import CmsDeleteDialog from '@/components/cms/CmsDeleteDialog.vue';
import CmsEmptyState from '@/components/cms/CmsEmptyState.vue';
import CmsMetricCard from '@/components/cms/CmsMetricCard.vue';
import CmsPageHeader from '@/components/cms/CmsPageHeader.vue';
import MessageTemplateFormDialog from '@/components/cms/MessageTemplateFormDialog.vue';
import { Badge } from '@/components/ui/badge';
import { dashboard } from '@/routes';
import { destroy, index } from '@/routes/cms/message-templates';
import type { MessageTemplateSummary, MessageTemplatesPageProps } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Template WhatsApp', href: index() },
        ],
    },
});

const props = defineProps<MessageTemplatesPageProps>();
const page = usePage();
const isSuperadmin = computed(() => page.props.auth.user.roles.includes('superadmin'));
const currentOrganizationId = computed(() => page.props.auth.user.organization_id);
const activeTemplates = computed(
    () => props.messageTemplates.filter((template) => template.is_active).length,
);
const branchTemplates = computed(
    () => props.messageTemplates.filter((template) => template.branch_id !== null).length,
);
const statusLabel = (template: MessageTemplateSummary): string =>
    props.orderStatuses.find((status) => status.value === template.order_status)?.label ??
    template.order_status;
const scopeLabel = (template: MessageTemplateSummary): string =>
    template.branch?.name ?? 'Seluruh organisasi';
const deletionConfirmation = (template: MessageTemplateSummary): string =>
    `${template.scope_key}:${template.order_status}`;
</script>

<template>
    <Head title="Template WhatsApp" />

    <main class="bg-canvas flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <div class="mx-auto max-w-[1440px] space-y-6">
            <CmsPageHeader
                eyebrow="Komunikasi pelanggan"
                title="Template WhatsApp"
                description="Siapkan pesan status yang dapat dipreview admin sebelum membuka WhatsApp pelanggan."
            >
                <template #actions>
                    <MessageTemplateFormDialog
                        :branches="branches"
                        :organizations="organizations"
                        :order-statuses="orderStatuses"
                        :supported-variables="supportedVariables"
                        :current-organization-id="currentOrganizationId"
                        :is-superadmin="isSuperadmin"
                    />
                </template>
            </CmsPageHeader>

            <section class="grid gap-4 sm:grid-cols-3" aria-label="Ringkasan template">
                <CmsMetricCard
                    label="Total template"
                    :value="messageTemplates.length"
                    detail="Semua status"
                    :icon="MessageSquareText"
                />
                <CmsMetricCard
                    label="Template aktif"
                    :value="activeTemplates"
                    detail="Siap digunakan"
                    :icon="CircleCheckBig"
                />
                <CmsMetricCard
                    label="Khusus cabang"
                    :value="branchTemplates"
                    detail="Override organisasi"
                    :icon="Building2"
                />
            </section>

            <section class="border-line bg-surface overflow-hidden rounded-xl border shadow-sm">
                <div class="border-line border-b px-5 py-4">
                    <h2 class="font-display text-ink-950 font-semibold">Daftar template</h2>
                    <p class="text-ink-500 mt-1 text-sm">
                        Template cabang diprioritaskan sebelum template organisasi untuk status yang sama.
                    </p>
                </div>

                <CmsEmptyState
                    v-if="messageTemplates.length === 0"
                    class="m-5"
                    title="Belum ada template WhatsApp"
                    description="Tambahkan template organisasi atau override khusus cabang."
                    :icon="MessageSquareText"
                />

                <div v-else class="hidden overflow-x-auto lg:block">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-canvas text-ink-500">
                            <tr>
                                <th class="px-5 py-3 font-medium">Cakupan</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                                <th class="px-5 py-3 font-medium">Pesan</th>
                                <th class="px-5 py-3 font-medium">Aktif</th>
                                <th class="px-5 py-3 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-line divide-y">
                            <tr v-for="template in messageTemplates" :key="template.id">
                                <td class="px-5 py-4">
                                    <p class="text-ink-950 font-medium">{{ scopeLabel(template) }}</p>
                                    <p class="text-ink-500 mt-0.5 text-xs">
                                        {{ template.organization.name }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">{{ statusLabel(template) }}</td>
                                <td class="max-w-md px-5 py-4">
                                    <p class="text-ink-700 line-clamp-2 whitespace-pre-wrap">
                                        {{ template.body }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    <Badge :variant="template.is_active ? 'default' : 'secondary'">
                                        {{ template.pending ? 'Menyimpan' : template.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </Badge>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-1">
                                        <MessageTemplateFormDialog
                                            :message-template="template"
                                            :branches="branches"
                                            :organizations="organizations"
                                            :order-statuses="orderStatuses"
                                            :supported-variables="supportedVariables"
                                            :current-organization-id="currentOrganizationId"
                                            :is-superadmin="isSuperadmin"
                                        />
                                        <CmsDeleteDialog
                                            title="Hapus template WhatsApp?"
                                            :description="`Template ${scopeLabel(template)} untuk status ${statusLabel(template)} akan dihapus permanen.`"
                                            :confirmation="deletionConfirmation(template)"
                                            confirmation-label="Cakupan dan status"
                                            :route="destroy(template.id)"
                                            :disabled="template.pending"
                                        />
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="messageTemplates.length > 0" class="divide-line divide-y lg:hidden">
                    <article
                        v-for="template in messageTemplates"
                        :key="template.id"
                        class="space-y-3 p-5"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-ink-950 font-semibold">{{ scopeLabel(template) }}</h3>
                                <p class="text-ink-500 text-xs">
                                    {{ template.organization.name }} · {{ statusLabel(template) }}
                                </p>
                            </div>
                            <Badge :variant="template.is_active ? 'default' : 'secondary'">
                                {{ template.pending ? 'Menyimpan' : template.is_active ? 'Aktif' : 'Nonaktif' }}
                            </Badge>
                        </div>
                        <p class="text-ink-700 whitespace-pre-wrap text-sm leading-6">
                            {{ template.body }}
                        </p>
                        <div class="border-line flex justify-end gap-1 border-t pt-3">
                            <MessageTemplateFormDialog
                                :message-template="template"
                                :branches="branches"
                                :organizations="organizations"
                                :order-statuses="orderStatuses"
                                :supported-variables="supportedVariables"
                                :current-organization-id="currentOrganizationId"
                                :is-superadmin="isSuperadmin"
                            />
                            <CmsDeleteDialog
                                title="Hapus template WhatsApp?"
                                :description="`Template ${scopeLabel(template)} untuk status ${statusLabel(template)} akan dihapus permanen.`"
                                :confirmation="deletionConfirmation(template)"
                                confirmation-label="Cakupan dan status"
                                :route="destroy(template.id)"
                                :disabled="template.pending"
                            />
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </main>
</template>
