<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderEstimateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order && $this->user()?->can('updateStatus', $order) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'estimated_ready_at' => ['required', 'date', 'after:now'],
            'reason' => ['required', 'string', 'max:300'],
        ];
    }
}
