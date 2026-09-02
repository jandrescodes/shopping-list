<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'quantity' => ['nullable', 'string', 'max:50'],
            'added_by' => ['nullable', 'string', 'max:50'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $clean = [];

        if (is_string($this->input('name'))) {
            $clean['name'] = trim($this->input('name'));
        }

        foreach (['quantity', 'added_by'] as $field) {
            if (is_string($this->input($field))) {
                $trimmed = trim($this->input($field));
                $clean[$field] = $trimmed === '' ? null : $trimmed;
            }
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
            'added_by' => 'quién lo agrega',
        ];
    }
}
