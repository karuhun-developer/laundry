<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class ShowOrderReceiptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order
            && $this->user()?->can('view', $order) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, never>
     */
    public function rules(): array
    {
        return [];
    }
}
