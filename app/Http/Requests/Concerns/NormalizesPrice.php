<?php

namespace App\Http\Requests\Concerns;

trait NormalizesPrice
{
    protected function normalizePrice(string $field = 'price'): void
    {
        if (! is_string($this->input($field))) {
            return;
        }

        $trimmed = trim($this->input($field));

        if ($trimmed === '' || trim($trimmed, ',.') === '') {
            $this->merge([$field => null]);

            return;
        }

        $this->merge([$field => str_replace(',', '.', $trimmed)]);
    }
}
