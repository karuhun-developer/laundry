<script setup lang="ts">
import {
    BellRing,
    Building2,
    LoaderCircle,
    LogOut,
    Mail,
    Phone,
    ShieldCheck,
    UserRound,
} from '@lucide/vue';
import CustomerPinSettings from '@/components/mobile/organisms/CustomerPinSettings.vue';
import MobileAppUpdateSettings from '@/components/mobile/organisms/MobileAppUpdateSettings.vue';
import MobilePrinterSettings from '@/components/mobile/organisms/MobilePrinterSettings.vue';
import type { PushNotificationStatus } from '@/composables/usePushNotifications';
import type { PrinterConnection, PrinterDevice, PrinterProfile } from '@/lib/printer/types';
import type { MobileOrganization, MobileRole } from '@/types/mobile';

withDefaults(
    defineProps<{
        name: string;
        email?: string | null;
        phone?: string | null;
        role: MobileRole;
        organization?: MobileOrganization | null;
        mustChangePin?: boolean;
        pinExpiresAt?: string | null;
        printerAvailable?: boolean;
        printerProfile?: PrinterProfile | null;
        printerDevices?: readonly PrinterDevice[];
        printerDiscovering?: boolean;
        printerPrinting?: boolean;
        printerMessage?: string | null;
        printerError?: string | null;
        notificationAvailable?: boolean;
        notificationStatus?: PushNotificationStatus;
        notificationError?: string | null;
    }>(),
    {
        email: null,
        phone: null,
        mustChangePin: false,
        pinExpiresAt: null,
        printerAvailable: false,
        organization: null,
        printerProfile: null,
        printerDevices: () => [],
        printerDiscovering: false,
        printerPrinting: false,
        printerMessage: null,
        printerError: null,
        notificationAvailable: false,
        notificationStatus: 'unavailable',
        notificationError: null,
    },
);

defineEmits<{
    discoverPrinter: [connection: PrinterConnection];
    savePrinter: [profile: PrinterProfile];
    clearPrinter: [];
    testPrinter: [];
    enableNotifications: [];
    logout: [];
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
                    <dd class="mt-0.5 [overflow-wrap:anywhere] text-sm font-semibold text-ink-950">
                        {{ email ?? 'Tidak digunakan untuk akun ini' }}
                    </dd>
                </div>
            </div>
            <div class="flex items-start gap-3 border-b border-line p-4">
                <Phone class="mt-0.5 size-5 text-brand-700" aria-hidden="true" />
                <div>
                    <dt class="text-xs text-ink-500">Nomor WhatsApp</dt>
                    <dd class="mt-0.5 [overflow-wrap:anywhere] text-sm font-semibold text-ink-950">
                        {{ phone ?? 'Tidak digunakan untuk akun ini' }}
                    </dd>
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

        <section
            v-if="notificationAvailable"
            class="mt-5 rounded-2xl border border-line bg-surface p-5 shadow-low"
            aria-labelledby="notification-settings-title"
            data-test="notification-settings"
        >
            <div class="flex items-start gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-100 text-brand-700">
                    <BellRing class="size-5" aria-hidden="true" />
                </span>
                <div class="min-w-0">
                    <h3 id="notification-settings-title" class="font-display text-base font-semibold text-ink-950">
                        Notifikasi perangkat
                    </h3>
                    <p v-if="notificationStatus === 'enabled'" class="mt-1 text-sm leading-6 text-mint-500">
                        Aktif. Pembaruan pesanan akan muncul di perangkat ini.
                    </p>
                    <p v-else-if="notificationStatus === 'denied'" class="mt-1 text-sm leading-6 text-amber-500">
                        Izin notifikasi ditolak. Izinkan notifikasi LaundryHub dari pengaturan perangkat, lalu coba lagi.
                    </p>
                    <p v-else-if="notificationStatus === 'error'" class="mt-1 text-sm leading-6 text-red-600">
                        {{ notificationError ?? 'Notifikasi belum dapat diaktifkan.' }}
                    </p>
                    <p v-else class="mt-1 text-sm leading-6 text-ink-500">
                        Aktifkan pemberitahuan untuk status penjemputan, harga, dan proses laundry.
                    </p>
                </div>
            </div>
            <button
                v-if="notificationStatus !== 'enabled'"
                type="button"
                class="mt-4 flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-brand-700 px-4 text-sm font-semibold text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60 motion-safe:active:scale-[0.98]"
                :disabled="notificationStatus === 'registering'"
                data-test="enable-notifications"
                @click="$emit('enableNotifications')"
            >
                <LoaderCircle
                    v-if="notificationStatus === 'registering'"
                    class="size-4 animate-spin"
                    aria-hidden="true"
                />
                <BellRing v-else class="size-4" aria-hidden="true" />
                {{ notificationStatus === 'registering' ? 'Mengaktifkan…' : 'Aktifkan notifikasi' }}
            </button>
        </section>

        <CustomerPinSettings
            v-if="role === 'customer'"
            :must-change-pin="mustChangePin"
            :pin-expires-at="pinExpiresAt"
        />

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

        <MobileAppUpdateSettings />

        <button
            type="button"
            class="mt-5 flex min-h-12 w-full items-center justify-center gap-2 rounded-xl border border-red-600/25 bg-surface px-4 text-sm font-semibold text-red-600 transition hover:bg-red-600/5 motion-safe:active:scale-[0.98]"
            data-test="logout-mobile"
            @click="$emit('logout')"
        >
            <LogOut class="size-5" aria-hidden="true" />
            Keluar
        </button>
    </section>
</template>
