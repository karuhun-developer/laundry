<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Building2, LoaderCircle, Plus } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/cms/organizations';
import type { OrganizationSummary, OrganizationsPageProps } from '@/types';

const form = useForm(store(), {
    name: '',
    slug: '',
    is_active: true,
});

const slugify = (value: string): string =>
    value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');

const resetForm = (): void => {
    form.name = '';
    form.slug = '';
    form.is_active = true;
    form.defaults();
};

const submit = (): void => {
    const pending: OrganizationSummary = {
        id: -Date.now(),
        name: form.name,
        slug: form.slug === '' ? slugify(form.name) : slugify(form.slug),
        is_active: form.is_active,
        branches_count: 0,
        users_count: 0,
        pending: true,
    };

    form.optimistic<OrganizationsPageProps>((props) => ({
        organizations: [pending, ...props.organizations],
    })).submit({
        preserveScroll: true,
        onSuccess: resetForm,
    });
};
</script>

<template>
    <Card class="border-line shadow-sm">
        <CardHeader>
            <span
                class="bg-surface-blue text-brand-700 mb-2 grid size-10 place-items-center rounded-lg"
            >
                <Building2 class="size-5" />
            </span>
            <CardTitle class="font-display">Organisasi baru</CardTitle>
            <CardDescription>
                Buat tenant baru sebelum menambahkan kota, cabang, dan owner.
            </CardDescription>
        </CardHeader>
        <CardContent>
            <form class="space-y-5" data-test="organization-form" @submit.prevent="submit">
                <div class="space-y-2">
                    <Label for="organization-name">Nama organisasi</Label>
                    <Input
                        id="organization-name"
                        v-model="form.name"
                        autocomplete="organization"
                        placeholder="Contoh: Kirei Wash Jakarta"
                        required
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="space-y-2">
                    <Label for="organization-slug">Slug</Label>
                    <Input
                        id="organization-slug"
                        v-model="form.slug"
                        autocomplete="off"
                        placeholder="Otomatis dari nama"
                    />
                    <p class="text-ink-500 text-xs">
                        Digunakan sebagai identitas stabil tenant.
                    </p>
                    <InputError :message="form.errors.slug" />
                </div>

                <label
                    class="border-line flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border px-3 py-2"
                >
                    <Checkbox v-model="form.is_active" />
                    <span class="text-sm font-medium">Langsung aktifkan organisasi</span>
                </label>

                <Button class="w-full" type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="animate-spin" />
                    <Plus v-else />
                    {{ form.processing ? 'Menyimpan...' : 'Tambah organisasi' }}
                </Button>
            </form>
        </CardContent>
    </Card>
</template>
