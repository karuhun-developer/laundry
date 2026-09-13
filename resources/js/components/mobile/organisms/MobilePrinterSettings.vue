<script setup lang="ts">
import { Bluetooth, LoaderCircle, Printer, Search, Trash2, Usb } from '@lucide/vue';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type {
    PaperWidth,
    PrinterConnection,
    PrinterDevice,
    PrinterProfile,
} from '@/lib/printer/types';

const props = defineProps<{
    available: boolean;
    profile: PrinterProfile | null;
    devices: readonly PrinterDevice[];
    discovering: boolean;
    printing: boolean;
    message: string | null;
    error: string | null;
}>();

const emit = defineEmits<{
    discover: [connection: PrinterConnection];
    save: [profile: PrinterProfile];
    clear: [];
    testPrint: [];
}>();

const connection = ref<PrinterConnection>(props.profile?.connection ?? 'bluetooth');
const deviceId = ref(props.profile?.id ?? '');
const paperWidth = ref<PaperWidth>(props.profile?.paperWidth ?? '58mm');

watch(
    () => props.profile,
    (profile) => {
        if (!profile) {
            return;
        }

        connection.value = profile.connection;
        deviceId.value = profile.id;
        paperWidth.value = profile.paperWidth;
    },
);

watch(
    () => props.devices,
    (devices) => {
        if (!devices.some((device) => device.id === deviceId.value)) {
            deviceId.value = devices[0]?.id ?? '';
        }
    },
);

const discover = (): void => {
    deviceId.value = '';
    emit('discover', connection.value);
};

const save = (): void => {
    const device = props.devices.find((entry) => entry.id === deviceId.value);

    if (!device) {
        return;
    }

    emit('save', { ...device, paperWidth: paperWidth.value });
};
</script>

<template>
    <section class="mt-5 rounded-2xl border border-line bg-surface p-4 shadow-low" aria-labelledby="printer-settings-title">
        <div class="flex items-start gap-3">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-surface-blue text-brand-700">
                <Printer class="size-5" aria-hidden="true" />
            </span>
            <div>
                <h3 id="printer-settings-title" class="font-display text-lg font-semibold text-ink-950">Printer thermal</h3>
                <p class="mt-1 text-sm leading-6 text-ink-500">
                    Simpan satu profil Bluetooth atau USB untuk tes cetak dan cetak ulang struk.
                </p>
            </div>
        </div>

        <div v-if="!available" class="mt-4 rounded-xl bg-canvas px-4 py-3 text-sm text-ink-500">
            Pengaturan printer dapat dipakai saat aplikasi dibuka dari Android Capacitor.
        </div>

        <template v-else>
            <div class="mt-4 grid grid-cols-2 gap-2">
                <button
                    type="button"
                    class="flex min-h-12 items-center justify-center gap-2 rounded-xl border px-3 text-sm font-semibold"
                    :class="connection === 'bluetooth' ? 'border-brand-600 bg-brand-100 text-brand-700' : 'border-line bg-canvas text-ink-700'"
                    @click="connection = 'bluetooth'"
                >
                    <Bluetooth class="size-5" aria-hidden="true" />
                    Bluetooth
                </button>
                <button
                    type="button"
                    class="flex min-h-12 items-center justify-center gap-2 rounded-xl border px-3 text-sm font-semibold"
                    :class="connection === 'usb' ? 'border-brand-600 bg-brand-100 text-brand-700' : 'border-line bg-canvas text-ink-700'"
                    @click="connection = 'usb'"
                >
                    <Usb class="size-5" aria-hidden="true" />
                    USB
                </button>
            </div>

            <Button
                type="button"
                variant="outline"
                class="mt-3 w-full"
                :disabled="discovering || printing"
                data-test="discover-printers"
                @click="discover"
            >
                <LoaderCircle v-if="discovering" class="size-4 animate-spin" aria-hidden="true" />
                <Search v-else class="size-4" aria-hidden="true" />
                {{ discovering ? 'Mencari printer...' : 'Cari printer' }}
            </Button>

            <div v-if="devices.length" class="mt-4 space-y-4">
                <div class="space-y-2">
                    <Label for="mobile-printer-device">Printer</Label>
                    <select
                        id="mobile-printer-device"
                        v-model="deviceId"
                        class="min-h-11 w-full rounded-xl border border-line bg-surface px-3 text-sm text-ink-950 outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100"
                    >
                        <option v-for="device in devices" :key="`${device.connection}-${device.id}`" :value="device.id">
                            {{ device.name }}
                        </option>
                    </select>
                </div>

                <div class="space-y-2">
                    <Label for="mobile-printer-paper">Lebar kertas</Label>
                    <select
                        id="mobile-printer-paper"
                        v-model="paperWidth"
                        class="min-h-11 w-full rounded-xl border border-line bg-surface px-3 text-sm text-ink-950 outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100"
                    >
                        <option value="58mm">58 mm</option>
                        <option value="80mm">80 mm</option>
                    </select>
                </div>

                <Button type="button" class="w-full bg-brand-700 text-white" :disabled="!deviceId" @click="save">
                    Simpan profil printer
                </Button>
            </div>

            <div v-if="profile" class="mt-4 rounded-xl bg-surface-blue p-4">
                <p class="text-xs font-semibold tracking-[0.14em] text-brand-600 uppercase">Printer aktif</p>
                <p class="mt-1 font-semibold text-ink-950">{{ profile.name }}</p>
                <p class="text-sm text-ink-500">{{ profile.connection === 'bluetooth' ? 'Bluetooth' : 'USB' }} · {{ profile.paperWidth }}</p>
                <div class="mt-3 grid grid-cols-[1fr_auto] gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="printing"
                        data-test="test-printer"
                        @click="emit('testPrint')"
                    >
                        <LoaderCircle v-if="printing" class="size-4 animate-spin" aria-hidden="true" />
                        <Printer v-else class="size-4" aria-hidden="true" />
                        Tes cetak
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="border-red-600/25 text-red-600"
                        :disabled="printing"
                        aria-label="Hapus profil printer"
                        @click="emit('clear')"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                    </Button>
                </div>
            </div>
        </template>

        <p
            v-if="message || error"
            class="mt-4 rounded-xl border px-4 py-3 text-sm"
            :class="error ? 'border-red-600/20 bg-red-600/5 text-red-600' : 'border-mint-500/20 bg-mint-500/10 text-mint-500'"
            role="status"
            aria-live="polite"
        >
            {{ error ?? message }}
        </p>
    </section>
</template>
