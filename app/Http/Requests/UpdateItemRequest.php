<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesPrice;
use Illuminate\Foundation\Http\FormRequest;

class UpdateItemRequest extends FormRequest
{
    use NormalizesPrice;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Editing an item is field-by-field: only the fields present in the
     * request are validated and, later, persisted.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'quantity' => ['sometimes', 'nullable', 'string', 'max:50'],
            'price' => ['sometimes', 'nullable', 'regex:/^\d{1,8}(\.\d{1,2})?$/', 'min:0', 'max:99999999.99'],
            'is_purchased' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePrice();

        $clean = [];

        if (is_string($this->input('name'))) {
            $clean['name'] = trim($this->input('name'));
        }

        if (is_string($this->input('quantity'))) {
            $trimmed = trim($this->input('quantity'));
            $clean['quantity'] = $trimmed === '' ? null : $trimmed;
        }

        $this->merge($clean);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'quantity' => 'cantidad',
            'price' => 'precio',
            'is_purchased' => 'estado de comprado',
        ];
    }
}
