<script setup lang="ts">
import { Download, FileText, LoaderCircle, RefreshCw, Smartphone } from '@lucide/vue';
import { onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useMobileAppUpdate } from '@/composables/useMobileAppUpdate';

const { checkForUpdates, checking, error, installUpdate, installing, message, update } =
    useMobileAppUpdate();

onMounted(() => {
    void checkForUpdates();
});
</script>

<template>
    <section
        class="mt-5 rounded-3xl border border-line bg-surface p-5 shadow-low"
        aria-labelledby="app-update-settings-title"
        data-test="app-update-settings"
    >
        <div class="flex items-start gap-3">
            <span
                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-100 text-brand-700"
            >
                <Smartphone class="size-5" aria-hidden="true" />
            </span>
            <div class="min-w-0 flex-1">
                <h3
                    id="app-update-settings-title"
                    class="font-display text-base font-semibold text-ink-950"
                >
                    Versi aplikasi
                </h3>
                <p class="mt-1 text-sm text-ink-500" data-test="installed-app-version">
                    {{ update ? `Terpasang v${update.current_version}` : 'Membaca versi aplikasi…' }}
                </p>
            </div>
        </div>

        <Dialog
            v-if="update && (update.current_changelog.length || (update.update_available && update.latest_changelog.length))"
        >
            <DialogTrigger as-child>
                <Button type="button" variant="outline" class="mt-4" data-test="view-app-changelog">
                    <FileText class="size-4" aria-hidden="true" />
                    Lihat changelog
                </Button>
            </DialogTrigger>
            <DialogContent
                class="mobile-dialog max-h-[85dvh] overflow-y-auto"
                close-label="Tutup"
                data-test="app-changelog-dialog"
            >
                <DialogHeader class="text-left">
                    <DialogTitle class="flex min-h-11 items-center pr-24 leading-snug">Changelog aplikasi</DialogTitle>
                    <DialogDescription>Catatan perubahan dan perbaikan LaundryHub.</DialogDescription>
                </DialogHeader>
                <div class="space-y-5">
                    <section v-if="update.current_changelog.length" aria-labelledby="current-changelog-title">
                        <h3 id="current-changelog-title" class="font-display text-sm font-semibold text-ink-950">
                            Versi terpasang v{{ update.current_version }}
                        </h3>
                        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm leading-6 text-ink-700">
                            <li v-for="entry in update.current_changelog" :key="entry" class="break-words whitespace-pre-line">{{ entry }}</li>
                        </ul>
                    </section>
                    <section
                        v-if="update.update_available && update.latest_changelog.length"
                        aria-labelledby="latest-changelog-title"
                        class="rounded-2xl border border-line bg-canvas p-4"
                    >
                        <h3 id="latest-changelog-title" class="font-display text-sm font-semibold text-ink-950">
                            Pembaruan v{{ update.latest_version }}
                        </h3>
                        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm leading-6 text-ink-700">
                            <li v-for="entry in update.latest_changelog" :key="entry" class="break-words whitespace-pre-line">{{ entry }}</li>
                        </ul>
                    </section>
                </div>
            </DialogContent>
        </Dialog>

        <div
            v-if="update?.update_available"
            class="mt-4 rounded-xl border border-mint-500/25 bg-mint-500/10 p-4"
            data-test="app-update-available"
        >
            <p class="font-semibold text-mint-500">
                Pembaruan v{{ update.latest_version }} tersedia
            </p>
            <button
                type="button"
                class="mt-4 flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-brand-700 px-4 text-sm font-semibold text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60 motion-safe:active:scale-[0.98]"
                :disabled="checking || installing || !update.check_available"
                data-test="install-app-update"
                @click="installUpdate"
            >
                <LoaderCircle v-if="installing" class="size-4 animate-spin" aria-hidden="true" />
                <Download v-else class="size-4" aria-hidden="true" />
                {{ installing ? 'Menyiapkan pembaruan…' : 'Unduh & pasang pembaruan' }}
            </button>
        </div>

        <p
            v-else-if="update?.check_available && !checking"
            class="mt-4 rounded-xl bg-mint-500/10 px-4 py-3 text-sm font-medium text-mint-500"
            data-test="app-update-current"
        >
            Aplikasi sudah menggunakan versi terbaru.
        </p>

        <p
            v-if="update?.unavailable_reason"
            class="mt-4 text-sm leading-6 text-amber-700"
            data-test="app-update-unavailable"
        >
            {{ update.unavailable_reason }}
        </p>
        <p v-if="message" class="mt-4 text-sm leading-6 text-brand-700" data-test="app-update-message">
            {{ message }}
        </p>
        <p v-if="error" class="mt-4 text-sm leading-6 text-red-600" data-test="app-update-error">
            {{ error }}
        </p>

        <button
            type="button"
            class="mt-4 flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-line px-4 text-sm font-semibold text-brand-700 transition hover:bg-brand-100 disabled:cursor-not-allowed disabled:opacity-60 motion-safe:active:scale-[0.98]"
            :disabled="checking || installing"
            data-test="check-app-update"
            @click="checkForUpdates"
        >
            <LoaderCircle v-if="checking" class="size-4 animate-spin" aria-hidden="true" />
            <RefreshCw v-else class="size-4" aria-hidden="true" />
            {{ checking ? 'Memeriksa pembaruan…' : 'Cek pembaruan' }}
        </button>
    </section>
</template>
