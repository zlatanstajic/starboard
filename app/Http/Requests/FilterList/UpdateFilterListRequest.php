<?php

declare(strict_types=1);

namespace App\Http\Requests\FilterList;

use Override;

final class UpdateFilterListRequest extends FilterListRequest
{
    public function rules(): array
    {
        $rules = $this->baseRules();
        $rules['name'][] = 'sometimes';
        $rules['is_published'] = ['boolean'];

        return $rules;
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        if ($this->exists('is_published')) {
            $this->merge([
                'is_published' => filter_var($this->input('is_published'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
