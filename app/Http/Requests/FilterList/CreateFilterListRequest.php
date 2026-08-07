<?php

declare(strict_types=1);

namespace App\Http\Requests\FilterList;

use App\Services\FilterListService;
use Override;

final class CreateFilterListRequest extends FilterListRequest
{
    public function rules(): array
    {
        return array_merge($this->baseRules(), [
            'filters' => ['required', 'array', 'min:1'],
            'is_published' => ['boolean'],
        ]);
    }

    #[Override]
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);
        $validated['user_id'] = $this->user()->id;

        return $validated;
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        $this->merge([
            'filters' => resolve(FilterListService::class)->sanitizeFilters($this->query()),
            'is_published' => filter_var($this->input('is_published', false), FILTER_VALIDATE_BOOLEAN),
        ]);
    }
}
