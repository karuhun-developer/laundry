<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\OrderComplaintStatus;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order && $this->user()?->can($this->filled('complaint_id') ? 'updateStatus' : 'view', $order) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'complaint_id' => ['nullable', 'integer', 'min:1'],
            'category' => ['required_without:complaint_id', 'nullable', Rule::in(['late', 'damaged', 'missing', 'other'])],
            'description' => ['required_without:complaint_id', 'nullable', 'string', 'max:4000'],
            'status' => ['sometimes', Rule::enum(OrderComplaintStatus::class)],
            'note' => ['nullable', 'string', 'max:4000', 'required_with:complaint_id'],
            'photos' => ['sometimes', 'array', 'max:3'],
            'photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
