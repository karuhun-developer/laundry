<script setup lang="ts">
import { Check, Home, LoaderCircle, MapPin, Pencil, Plus, Star, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import LocationPicker from '@/components/LocationPicker.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useGeolocation } from '@/composables/useGeolocation';
import { MobileCustomerAddressValidationError } from '@/composables/useMobileCustomerAddressApi';
import type { MobileCustomerAddress, MobileCustomerAddressPayload } from '@/types/mobile';

const props = defineProps<{
    addresses: readonly MobileCustomerAddress[];
    selectedAddressId: number | null;
    customerName: string;
    customerPhone?: string | null;
    createAddress: (payload: MobileCustomerAddressPayload) => Promise<MobileCustomerAddress>;
    updateAddress: (
        addressId: number,
        payload: MobileCustomerAddressPayload,
    ) => Promise<MobileCustomerAddress>;
    deleteAddress: (addressId: number) => Promise<void>;
    setDefaultAddress: (addressId: number) => Promise<MobileCustomerAddress>;
    selectAddress: (addressId: number) => Promise<boolean>;
}>();

const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ success: [message: string] }>();
const view = ref<'list' | 'form' | 'delete'>('list');
const activeAddress = ref<MobileCustomerAddress | null>(null);
const processing = ref(false);
const requestError = ref<string | null>(null);
const fieldErrors = ref<Partial<Record<keyof MobileCustomerAddressPayload, string>>>({});
const geolocation = useGeolocation();
const label = ref('Rumah');
const recipientName = ref('');
const whatsappPhone = ref('');
const addressText = ref('');
const notes = ref('');
const latitude = ref('');
const longitude = ref('');
const isDefault = ref(false);
let operationGeneration = 0;

const resetState = (): void => {
    operationGeneration += 1;
    view.value = 'list';
    activeAddress.value = null;
    processing.value = false;
    requestError.value = null;
    fieldErrors.value = {};
};

watch(open, (isOpen) => {
    if (isOpen) {
        resetState();
    }
});

const fillForm = (address: MobileCustomerAddress | null): void => {
    activeAddress.value = address;
    label.value = address?.label ?? 'Rumah';
    recipientName.value = address?.recipient_name ?? props.customerName;
    whatsappPhone.value = address?.whatsapp_phone ?? props.customerPhone ?? '';
    addressText.value = address?.address ?? '';
    notes.value = address?.notes ?? '';
    latitude.value = address ? String(address.latitude) : '';
    longitude.value = address ? String(address.longitude) : '';
    isDefault.value = address?.is_default ?? props.addresses.length === 0;
    requestError.value = null;
    fieldErrors.value = {};
    view.value = 'form';
};

const locateNewAddress = async (): Promise<void> => {
    const generation = operationGeneration;
    const coordinates = await geolocation.locate();

    if (generation !== operationGeneration || !coordinates || activeAddress.value) {
        return;
    }

    latitude.value = coordinates.latitude.toFixed(6);
    longitude.value = coordinates.longitude.toFixed(6);
};

const createNewAddress = (): void => {
    fillForm(null);
    void locateNewAddress();
};

const payload = (): MobileCustomerAddressPayload => ({
    label: label.value.trim(),
    recipient_name: recipientName.value.trim(),
    whatsapp_phone: whatsappPhone.value.trim(),
    address: addressText.value.trim(),
    notes: notes.value.trim() || null,
    latitude: Number(latitude.value),
    longitude: Number(longitude.value),
    is_default: isDefault.value,
});

const save = async (): Promise<void> => {
    if (processing.value) {
        return;
    }

    processing.value = true;
    requestError.value = null;
    fieldErrors.value = {};
    const generation = operationGeneration;

    try {
        const address = activeAddress.value
            ? await props.updateAddress(activeAddress.value.id, payload())
            : await props.createAddress(payload());

        if (generation !== operationGeneration) {
            return;
        }

        view.value = 'list';
        activeAddress.value = null;
        emit('success', `Alamat ${address.label} berhasil disimpan dan dipilih.`);
    } catch (error: unknown) {
        if (generation !== operationGeneration) {
            return;
        }

        requestError.value =
            error instanceof Error ? error.message : 'Alamat tidak dapat disimpan.';
        toast.error(requestError.value);

        if (error instanceof MobileCustomerAddressValidationError) {
            fieldErrors.value = error.fieldErrors;
        }
    } finally {
        if (generation === operationGeneration) {
            processing.value = false;
        }
    }
};

const select = async (address: MobileCustomerAddress): Promise<void> => {
    processing.value = true;
    requestError.value = null;

    try {
        if (await props.selectAddress(address.id)) {
            open.value = false;
            emit('success', `Alamat ${address.label} dipilih untuk penjemputan.`);
        }
    } catch (error: unknown) {
        requestError.value = error instanceof Error ? error.message : 'Alamat tidak dapat dipilih.';
        toast.error(requestError.value);
    } finally {
        processing.value = false;
    }
};

const makeDefault = async (address: MobileCustomerAddress): Promise<void> => {
    processing.value = true;
    requestError.value = null;

    try {
        const updatedAddress = await props.setDefaultAddress(address.id);
        emit('success', `${updatedAddress.label} sekarang menjadi alamat utama.`);
    } catch (error: unknown) {
        requestError.value =
            error instanceof Error ? error.message : 'Alamat utama tidak dapat diperbarui.';
        toast.error(requestError.value);
    } finally {
        processing.value = false;
    }
};

const confirmDelete = (address: MobileCustomerAddress): void => {
    activeAddress.value = address;
    requestError.value = null;
    view.value = 'delete';
};

const remove = async (): Promise<void> => {
    const address = activeAddress.value;

    if (!address || processing.value) {
        return;
    }

    processing.value = true;
    requestError.value = null;

    try {
        await props.deleteAddress(address.id);
        view.value = 'list';
        activeAddress.value = null;
        emit('success', `Alamat ${address.label} berhasil dihapus.`);
    } catch (error: unknown) {
        requestError.value = error instanceof Error ? error.message : 'Alamat tidak dapat dihapus.';
        toast.error(requestError.value);
    } finally {
        processing.value = false;
    }
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[92dvh] max-w-2xl overflow-y-auto rounded-2xl bg-surface p-0">
            <div class="p-5 sm:p-6">
                <DialogHeader>
                    <span class="mb-2 flex size-11 items-center justify-center rounded-xl bg-brand-100 text-brand-700">
                        <Home class="size-5" aria-hidden="true" />
                    </span>
                    <DialogTitle>Alamat penjemputan</DialogTitle>
                    <DialogDescription>
                        Simpan beberapa alamat, pilih penerima, lalu pastikan pin berada tepat di titik penjemputan.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="view === 'list'" class="mt-6 space-y-4" data-test="address-list">
                    <Button type="button" class="min-h-11 w-full" data-test="add-address" @click="createNewAddress">
                        <Plus class="size-4" aria-hidden="true" />
                        Tambah alamat baru
                    </Button>

                    <article
                        v-for="address in addresses"
                        :key="address.id"
                        class="rounded-2xl border p-4"
                        :class="address.id === selectedAddressId ? 'border-brand-600 bg-brand-100/40' : 'border-line bg-surface'"
                        :data-test="`address-card-${address.id}`"
                    >
                        <div class="flex items-start gap-3">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-surface-blue text-brand-700">
                                <MapPin class="size-5" aria-hidden="true" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-semibold text-ink-950">{{ address.label }}</h3>
                                    <span v-if="address.is_default" class="rounded-full bg-mint-500/10 px-2 py-1 text-[11px] font-semibold text-mint-500">Utama</span>
                                    <span v-if="address.id === selectedAddressId" class="rounded-full bg-brand-700 px-2 py-1 text-[11px] font-semibold text-white">Dipilih</span>
                                </div>
                                <p class="mt-2 text-sm font-medium text-ink-700">{{ address.recipient_name }} · {{ address.whatsapp_phone }}</p>
                                <p class="mt-1 text-sm leading-6 text-ink-500">{{ address.address }}</p>
                                <p v-if="address.notes" class="mt-1 text-xs leading-5 text-ink-500">Catatan: {{ address.notes }}</p>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-2 border-t border-line pt-3 sm:grid-cols-4">
                            <Button type="button" variant="outline" class="min-h-11" :disabled="processing" :data-test="`select-address-${address.id}`" @click="select(address)">
                                <Check class="size-4" aria-hidden="true" /> Pilih
                            </Button>
                            <Button type="button" variant="ghost" class="min-h-11" :disabled="processing" @click="fillForm(address)">
                                <Pencil class="size-4" aria-hidden="true" /> Edit
                            </Button>
                            <Button type="button" variant="ghost" class="min-h-11" :disabled="processing || address.is_default" :data-test="`default-address-${address.id}`" @click="makeDefault(address)">
                                <Star class="size-4" aria-hidden="true" /> Utama
                            </Button>
                            <Button type="button" variant="ghost" class="min-h-11 text-red-600 hover:bg-red-600/5 hover:text-red-600" :disabled="processing" :data-test="`delete-address-${address.id}`" @click="confirmDelete(address)">
                                <Trash2 class="size-4" aria-hidden="true" /> Hapus
                            </Button>
                        </div>
                    </article>

                    <div v-if="addresses.length === 0" class="rounded-2xl border border-dashed border-line bg-canvas p-6 text-center">
                        <MapPin class="mx-auto size-7 text-brand-700" aria-hidden="true" />
                        <p class="mt-3 font-semibold text-ink-950">Belum ada alamat tersimpan</p>
                        <p class="mt-1 text-sm leading-6 text-ink-500">Lokasi saat ini akan dicoba otomatis saat Anda menambah alamat pertama.</p>
                    </div>
                    <InputError :message="requestError ?? undefined" />
                </div>

                <form v-else-if="view === 'form'" class="mt-6 space-y-5" data-test="address-form" @submit.prevent="save">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="address-label">Label alamat</Label>
                            <Input id="address-label" v-model="label" required maxlength="80" placeholder="Rumah, Kantor, Kost" />
                            <InputError :message="fieldErrors.label" />
                        </div>
                        <div class="space-y-2">
                            <Label for="address-recipient">Nama penerima</Label>
                            <Input id="address-recipient" v-model="recipientName" required maxlength="120" autocomplete="name" />
                            <InputError :message="fieldErrors.recipient_name" />
                        </div>
                    </div>
                    <div class="space-y-2">
                        <Label for="address-phone">Nomor WhatsApp penerima</Label>
                        <Input id="address-phone" v-model="whatsappPhone" type="tel" inputmode="tel" autocomplete="tel" required maxlength="30" placeholder="0812 3456 7890" />
                        <InputError :message="fieldErrors.whatsapp_phone" />
                    </div>
                    <div class="space-y-2">
                        <Label for="address-text">Alamat lengkap</Label>
                        <textarea id="address-text" v-model="addressText" required maxlength="500" rows="3" class="w-full rounded-xl border border-line bg-surface px-3 py-2 text-sm text-ink-950 outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100" placeholder="Nama jalan, nomor rumah, RT/RW, kelurahan, kecamatan" />
                        <InputError :message="fieldErrors.address" />
                    </div>
                    <div class="space-y-2">
                        <Label for="address-notes">Patokan / catatan kurir</Label>
                        <Input id="address-notes" v-model="notes" maxlength="500" placeholder="Contoh: pagar hitam, rumah sebelah minimarket" />
                        <InputError :message="fieldErrors.notes" />
                    </div>
                    <LocationPicker
                        v-model:latitude="latitude"
                        v-model:longitude="longitude"
                        title="Titik penjemputan"
                        description="Geser pin sampai tepat di depan lokasi laundry akan dijemput."
                        current-location-label="Pakai lokasi sekarang"
                        input-id-prefix="customer-address"
                    />
                    <InputError :message="fieldErrors.latitude ?? fieldErrors.longitude" />
                    <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border border-line px-4 py-3 text-sm font-medium text-ink-700">
                        <input v-model="isDefault" type="checkbox" class="size-4 accent-brand-700" />
                        Jadikan alamat utama
                    </label>
                    <InputError :message="requestError ?? undefined" />
                    <div class="grid grid-cols-2 gap-3">
                        <Button type="button" variant="secondary" class="min-h-11" :disabled="processing" @click="view = 'list'">Batal</Button>
                        <Button type="submit" class="min-h-11" :disabled="processing">
                            <LoaderCircle v-if="processing" class="size-4 animate-spin" aria-hidden="true" />
                            {{ processing ? 'Menyimpan…' : 'Simpan alamat' }}
                        </Button>
                    </div>
                </form>

                <div v-else class="mt-6 space-y-5" data-test="delete-address-verification">
                    <div class="rounded-2xl border border-red-600/20 bg-red-600/5 p-4">
                        <h3 class="font-semibold text-ink-950">Hapus alamat {{ activeAddress?.label }}?</h3>
                        <p class="mt-2 text-sm leading-6 text-ink-500">Riwayat pesanan tetap menyimpan snapshot alamat ini. Tindakan ini hanya menghapusnya dari daftar alamat Anda.</p>
                    </div>
                    <InputError :message="requestError ?? undefined" />
                    <div class="grid grid-cols-2 gap-3">
                        <Button type="button" variant="secondary" class="min-h-11" :disabled="processing" @click="view = 'list'">Batal</Button>
                        <Button type="button" variant="destructive" class="min-h-11" :disabled="processing" data-test="confirm-delete-address" @click="remove">
                            <LoaderCircle v-if="processing" class="size-4 animate-spin" aria-hidden="true" />
                            {{ processing ? 'Menghapus…' : 'Hapus alamat' }}
                        </Button>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
