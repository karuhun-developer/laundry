<?php

namespace App\Services\Qris;

use App\Exceptions\InvalidQrisPayload;

final class QrisPayloadGenerator
{
    public function generate(string $payload, int $amountRupiah): string
    {
        if ($amountRupiah < 1) {
            throw new InvalidQrisPayload('Nominal QRIS harus lebih dari nol.');
        }

        $fields = $this->parse(trim($payload));
        $this->assertQrisFields($fields);

        $amount = (string) $amountRupiah;

        if (strlen($amount) > 13) {
            throw new InvalidQrisPayload('Nominal QRIS terlalu besar.');
        }

        $dynamicPayload = '';
        $amountInserted = false;

        foreach ($fields as $field) {
            if ($field['id'] === '63' || $field['id'] === '54') {
                continue;
            }

            if (! $amountInserted && (int) $field['id'] > 54) {
                $dynamicPayload .= $this->encodeField('54', $amount);
                $amountInserted = true;
            }

            $dynamicPayload .= $this->encodeField(
                $field['id'],
                $field['id'] === '01' ? '12' : $field['value'],
            );
        }

        if (! $amountInserted) {
            $dynamicPayload .= $this->encodeField('54', $amount);
        }

        $payloadWithChecksumHeader = $dynamicPayload.'6304';

        return $payloadWithChecksumHeader.$this->crc16($payloadWithChecksumHeader);
    }

    public function crc16(string $value): string
    {
        $checksum = 0xFFFF;

        for ($index = 0, $length = strlen($value); $index < $length; $index++) {
            $checksum ^= ord($value[$index]) << 8;

            for ($bit = 0; $bit < 8; $bit++) {
                $checksum = ($checksum & 0x8000) !== 0
                    ? ($checksum << 1) ^ 0x1021
                    : $checksum << 1;
                $checksum &= 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($checksum), 4, '0', STR_PAD_LEFT));
    }

    /**
     * @return list<array{id: string, value: string}>
     */
    private function parse(string $payload): array
    {
        if ($payload === '' || preg_match('/[\x00-\x1F\x7F]/', $payload) === 1) {
            throw new InvalidQrisPayload('Payload QRIS kosong atau mengandung karakter kontrol.');
        }

        $fields = [];
        $seenIds = [];
        $offset = 0;
        $payloadLength = strlen($payload);

        while ($offset < $payloadLength) {
            if ($payloadLength - $offset < 4) {
                throw new InvalidQrisPayload('Header field QRIS tidak lengkap.');
            }

            $id = substr($payload, $offset, 2);
            $encodedLength = substr($payload, $offset + 2, 2);

            if (! ctype_digit($id) || ! ctype_digit($encodedLength)) {
                throw new InvalidQrisPayload('ID atau panjang field QRIS tidak valid.');
            }

            if (isset($seenIds[$id])) {
                throw new InvalidQrisPayload("Field QRIS {$id} tidak boleh duplikat.");
            }

            $fieldLength = (int) $encodedLength;
            $valueOffset = $offset + 4;

            if ($valueOffset + $fieldLength > $payloadLength) {
                throw new InvalidQrisPayload("Nilai field QRIS {$id} terpotong.");
            }

            $value = substr($payload, $valueOffset, $fieldLength);
            $fields[] = ['id' => $id, 'value' => $value];
            $seenIds[$id] = true;
            $offset = $valueOffset + $fieldLength;
        }

        return $fields;
    }

    /**
     * @param  list<array{id: string, value: string}>  $fields
     */
    private function assertQrisFields(array $fields): void
    {
        if ($this->fieldValue($fields, '00') !== '01') {
            throw new InvalidQrisPayload('Format indicator QRIS tidak valid.');
        }

        if (! in_array($this->fieldValue($fields, '01'), ['11', '12'], true)) {
            throw new InvalidQrisPayload('Point of initiation QRIS tidak valid.');
        }

        if ($this->fieldValue($fields, '53') !== '360' || $this->fieldValue($fields, '58') !== 'ID') {
            throw new InvalidQrisPayload('Mata uang atau negara QRIS tidak valid.');
        }

        $hasMerchantAccount = false;

        foreach (range(26, 51) as $merchantFieldId) {
            if ($this->fieldValue($fields, (string) $merchantFieldId) !== null) {
                $hasMerchantAccount = true;
                break;
            }
        }

        if (! $hasMerchantAccount) {
            throw new InvalidQrisPayload('Merchant account QRIS tidak ditemukan.');
        }

        $lastField = $fields[array_key_last($fields)] ?? null;

        if ($lastField === null || $lastField['id'] !== '63' || strlen($lastField['value']) !== 4) {
            throw new InvalidQrisPayload('Checksum QRIS tidak ditemukan di akhir payload.');
        }

        $payloadWithoutChecksum = '';

        foreach (array_slice($fields, 0, -1) as $field) {
            $payloadWithoutChecksum .= $this->encodeField($field['id'], $field['value']);
        }

        $expectedChecksum = $this->crc16($payloadWithoutChecksum.'6304');

        if (! hash_equals($expectedChecksum, strtoupper($lastField['value']))) {
            throw new InvalidQrisPayload('Checksum QRIS tidak cocok.');
        }
    }

    /**
     * @param  list<array{id: string, value: string}>  $fields
     */
    private function fieldValue(array $fields, string $id): ?string
    {
        foreach ($fields as $field) {
            if ($field['id'] === $id) {
                return $field['value'];
            }
        }

        return null;
    }

    private function encodeField(string $id, string $value): string
    {
        $length = strlen($value);

        if ($length > 99) {
            throw new InvalidQrisPayload("Nilai field QRIS {$id} terlalu panjang.");
        }

        return $id.str_pad((string) $length, 2, '0', STR_PAD_LEFT).$value;
    }
}
