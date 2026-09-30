<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\OrderTaskStatus;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order && $this->user()?->can('updateStatus', $order) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'task_id' => ['nullable', 'integer', 'min:1'],
            'kind' => ['required', Rule::in(['pickup', 'delivery'])],
            'status' => ['required', Rule::enum(OrderTaskStatus::class)],
            'operator_name' => ['nullable', 'string', 'max:120', 'required_if:status,scheduled'],
            'scheduled_at' => ['nullable', 'date', 'required_if:status,scheduled'],
            'address' => ['nullable', 'string', 'max:2000', 'required_if:status,scheduled'],
            'recipient_name' => ['nullable', 'string', 'max:120', 'required_if:status,completed'],
            'note' => ['nullable', 'string', 'max:2000', 'required_if:status,failed'],
            'photos' => ['sometimes', 'array', 'max:3'],
            'photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
