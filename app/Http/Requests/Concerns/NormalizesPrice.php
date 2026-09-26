<?php

namespace App\Http\Requests\Concerns;

trait NormalizesPrice
{
    protected function normalizePrice(): void
    {
        if (! is_string($this->input('price'))) {
            return;
        }

        $trimmed = trim($this->input('price'));

        if ($trimmed === '' || trim($trimmed, ',.') === '') {
            $this->merge(['price' => null]);

            return;
        }

        $this->merge(['price' => str_replace(',', '.', $trimmed)]);
    }
}
