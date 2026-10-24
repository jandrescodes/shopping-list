<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoveItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `before_id` must be sent: null means "move to the end".
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'before_id' => ['present', 'nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'before_id' => 'before_id',
        ];
    }
}
