<script setup lang="ts">
import { Building2, ClipboardList, House, ListChecks, UserRound, UsersRound } from '@lucide/vue';
import type { MobileRole, MobileTab } from '@/types/mobile';

defineProps<{
    role: MobileRole;
    modelValue: MobileTab;
}>();

const emit = defineEmits<{
    'update:modelValue': [tab: MobileTab];
}>();

const customerTabs: { id: MobileTab; label: string }[] = [
    { id: 'home', label: 'Beranda' },
    { id: 'orders', label: 'Pesanan' },
    { id: 'branches', label: 'Cabang' },
    { id: 'account', label: 'Akun' },
];
const adminTabs: { id: MobileTab; label: string }[] = [
    { id: 'home', label: 'Beranda' },
    { id: 'queue', label: 'Antrean' },
    { id: 'customers', label: 'Pelanggan' },
    { id: 'more', label: 'Akun' },
];
</script>

<template>
    <nav
        class="mobile-glass safe-bottom fixed inset-x-0 bottom-0 z-40 mx-auto grid max-w-3xl grid-cols-4 rounded-t-3xl border border-white/70 px-2 pt-2 shadow-low"
        aria-label="Navigasi aplikasi"
    >
        <template v-if="role === 'customer'">
            <button
                v-for="item in customerTabs"
                :key="item.id"
                type="button"
                class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl text-xs font-semibold transition-colors"
                :class="modelValue === item.id ? 'bg-brand-100 text-brand-700' : 'text-ink-500 hover:bg-canvas hover:text-ink-950'"
                :aria-current="modelValue === item.id ? 'page' : undefined"
                :data-test="`mobile-tab-${item.id}`"
                @click="emit('update:modelValue', item.id)"
            >
                <House v-if="item.id === 'home'" class="size-5" aria-hidden="true" />
                <ClipboardList
                    v-else-if="item.id === 'orders'"
                    class="size-5"
                    aria-hidden="true"
                />
                <Building2
                    v-else-if="item.id === 'branches'"
                    class="size-5"
                    aria-hidden="true"
                />
                <UserRound v-else class="size-5" aria-hidden="true" />
                {{ item.label }}
            </button>
        </template>

        <template v-else>
            <button
                v-for="item in adminTabs"
                :key="item.id"
                type="button"
                class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl text-xs font-semibold transition-colors"
                :class="modelValue === item.id ? 'bg-brand-100 text-brand-700' : 'text-ink-500 hover:bg-canvas hover:text-ink-950'"
                :aria-current="modelValue === item.id ? 'page' : undefined"
                :data-test="`mobile-tab-${item.id}`"
                @click="emit('update:modelValue', item.id)"
            >
                <House v-if="item.id === 'home'" class="size-5" aria-hidden="true" />
                <ListChecks v-else-if="item.id === 'queue'" class="size-5" aria-hidden="true" />
                <UsersRound
                    v-else-if="item.id === 'customers'"
                    class="size-5"
                    aria-hidden="true"
                />
                <UserRound v-else class="size-5" aria-hidden="true" />
                {{ item.label }}
            </button>
        </template>
    </nav>
</template>
