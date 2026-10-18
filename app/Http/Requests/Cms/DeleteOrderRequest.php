<?php

namespace App\Http\Requests\Cms;

use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DeleteOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->order();

        return $order !== null && $this->user()?->can('delete', $order) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [];
    }

    private function order(): ?Order
    {
        $order = $this->route('order');

        return $order instanceof Order ? $order : null;
    }
}
