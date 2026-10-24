<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesPrice;

/**
 * An update may carry any subset of fields, so each one is validated only when
 * it is present: a request that changes nothing but the currency must pass.
 * The budget uses the same format as an item price; null or blank removes it.
 */
class UpdateListRequest extends StoreListRequest
{
    use NormalizesPrice;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['name'] = ['sometimes', 'required', 'string', 'max:60'];

        $rules['budget'] = ['sometimes', 'nullable', 'regex:/^\d{1,8}(\.\d{1,2})?$/', 'min:0', 'max:99999999.99'];

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->normalizePrice('budget');
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return parent::attributes() + ['budget' => 'presupuesto'];
    }
}
