<?php

namespace App\Http\Requests;

/**
 * An update may carry any subset of fields, so the name is validated only when
 * it is present: a request that changes nothing but the currency must pass.
 */
class UpdateListRequest extends StoreListRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['name'] = ['sometimes', 'required', 'string', 'max:60'];

        return $rules;
    }
}
