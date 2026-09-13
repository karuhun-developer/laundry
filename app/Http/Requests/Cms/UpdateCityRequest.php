<?php

namespace App\Http\Requests\Cms;

use App\Models\City;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $city = $this->city();

        return $city !== null && $this->user()?->can('update', $city) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $city = $this->city();

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('cities', 'name')
                    ->where('province', $this->string('province')->toString())
                    ->ignore($city),
            ],
            'province' => ['required', 'string', 'max:100'],
        ];
    }

    private function city(): ?City
    {
        $city = $this->route('city');

        return $city instanceof City ? $city : null;
    }
}
