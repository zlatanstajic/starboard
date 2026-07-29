<?php

declare(strict_types=1);

namespace App\Http\Requests\NetworkSource;

use Override;

final class UpdateNetworkSourceRequest extends NetworkSourceRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = $this->baseRules();

        $rules['name'][] = 'sometimes';
        $rules['url'][] = 'sometimes';

        return $rules;
    }

    /**
     * Prepares data for validation.
     */
    #[Override]
    protected function prepareForValidation(): void
    {
        $this->merge([
            'exclude_from_dashboard' => filter_var($this->exclude_from_dashboard, FILTER_VALIDATE_BOOLEAN),
        ]);
    }
}
