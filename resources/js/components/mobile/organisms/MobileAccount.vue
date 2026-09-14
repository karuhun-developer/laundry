<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Building2, LogOut, Mail, ShieldCheck, UserRound } from '@lucide/vue';
import MobilePrinterSettings from '@/components/mobile/organisms/MobilePrinterSettings.vue';
import type { PrinterConnection, PrinterDevice, PrinterProfile } from '@/lib/printer/types';
import { logout } from '@/routes';
import type { MobileOrganization, MobileRole } from '@/types/mobile';

withDefaults(
    defineProps<{
        name: string;
        email: string;
        role: MobileRole;
        organization?: MobileOrganization | null;
        printerAvailable?: boolean;
        printerProfile?: PrinterProfile | null;
        printerDevices?: readonly PrinterDevice[];
        printerDiscovering?: boolean;
        printerPrinting?: boolean;
        printerMessage?: string | null;
        printerError?: string | null;
    }>(),
    {
        printerAvailable: false,
        organization: null,
        printerProfile: null,
        printerDevices: () => [],
        printerDiscovering: false,
        printerPrinting: false,
        printerMessage: null,
        printerError: null,
    },
);

defineEmits<{
    discoverPrinter: [connection: PrinterConnection];
    savePrinter: [profile: PrinterProfile];
    clearPrinter: [];
    testPrinter: [];
}>();
</script>

<template>
    <section aria-labelledby="mobile-account-title">
        <div class="rounded-3xl bg-brand-700 p-6 text-white shadow-raised">
            <span class="flex size-14 items-center justify-center rounded-2xl bg-white/12">
                <UserRound class="size-7" aria-hidden="true" />
            </span>
            <h2 id="mobile-account-title" class="mt-5 font-display text-2xl font-semibold">{{ name }}</h2>
            <p class="mt-1 text-sm text-white/75">{{ role === 'customer' ? 'Pelanggan' : 'Admin cabang' }}</p>
        </div>

        <dl class="mt-5 overflow-hidden rounded-2xl border border-line bg-surface shadow-low">
            <div class="flex items-start gap-3 border-b border-line p-4">
                <Mail class="mt-0.5 size-5 text-brand-700" aria-hidden="true" />
                <div>
                    <dt class="text-xs text-ink-500">Email</dt>
                    <dd class="mt-0.5 [overflow-wrap:anywhere] text-sm font-semibold text-ink-950">{{ email }}</dd>
                </div>
            </div>
            <div
                v-if="role === 'branch_admin' && organization"
                class="flex items-start gap-3 border-b border-line p-4"
            >
                <Building2 class="mt-0.5 size-5 text-brand-700" aria-hidden="true" />
                <div>
                    <dt class="text-xs text-ink-500">Organisasi aktif</dt>
                    <dd class="mt-0.5 text-sm font-semibold text-ink-950">
                        {{ organization?.name }}
                    </dd>
                </div>
            </div>
            <div class="flex items-start gap-3 p-4">
                <ShieldCheck class="mt-0.5 size-5 text-brand-700" aria-hidden="true" />
                <div>
                    <dt class="text-xs text-ink-500">Akses</dt>
                    <dd class="mt-0.5 text-sm font-semibold text-ink-950">Sesi masuk Anda terlindungi</dd>
                </div>
            </div>
        </dl>

        <MobilePrinterSettings
            v-if="role === 'branch_admin'"
            :available="printerAvailable"
            :profile="printerProfile"
            :devices="printerDevices"
            :discovering="printerDiscovering"
            :printing="printerPrinting"
            :message="printerMessage"
            :error="printerError"
            @discover="$emit('discoverPrinter', $event)"
            @save="$emit('savePrinter', $event)"
            @clear="$emit('clearPrinter')"
            @test-print="$emit('testPrinter')"
        />

        <Link
            :href="logout()"
            method="post"
            as="button"
            class="mt-5 flex min-h-12 w-full items-center justify-center gap-2 rounded-xl border border-red-600/25 bg-surface px-4 text-sm font-semibold text-red-600 transition hover:bg-red-600/5 motion-safe:active:scale-[0.98]"
        >
            <LogOut class="size-5" aria-hidden="true" />
            Keluar
        </Link>
    </section>
</template>
