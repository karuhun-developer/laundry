<?php

namespace App\Http\Requests\Cms;

use App\Models\Branch;
use App\Rules\ValidQrisPayload;
use App\Rules\ValidWhatsAppPhone;
use App\Services\WhatsAppPhoneNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $branch = $this->branch();

        return $branch !== null && $this->user()?->can('update', $branch) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $branch = $this->branch();

        return [
            'city_id' => [
                'required',
                'integer',
                Rule::exists('cities', 'id'),
            ],
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'alpha_dash',
                'max:24',
                Rule::unique('branches')
                    ->where('organization_id', $branch?->organization_id)
                    ->ignore($branch),
            ],
            'phone' => ['nullable', 'string', 'max:24', new ValidWhatsAppPhone],
            'address' => ['required', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'free_delivery_radius_meters' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'delivery_fee_per_km_rupiah' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'qris_enabled' => ['sometimes', 'boolean'],
            'qris_static_payload' => [
                Rule::requiredIf($this->boolean('qris_enabled')),
                'nullable',
                'string',
                'max:4096',
                new ValidQrisPayload,
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function organizationId(): int
    {
        return (int) $this->branch()?->organization_id;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $phone = app(WhatsAppPhoneNormalizer::class)->normalize($this->input('phone'));

            if ($phone !== null) {
                $this->merge(['phone' => $phone]);
            }
        }

        if ($this->filled('code')) {
            $this->merge(['code' => Str::upper($this->string('code')->toString())]);
        }

        if ($this->has('qris_static_payload')) {
            $payload = trim($this->string('qris_static_payload')->toString());

            $this->merge([
                'qris_static_payload' => $payload === '' ? null : $payload,
            ]);
        }
    }

    private function branch(): ?Branch
    {
        $branch = $this->route('branch');

        return $branch instanceof Branch ? $branch : null;
    }
}
