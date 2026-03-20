<?php

declare(strict_types=1);

namespace App\Http\Requests\NetworkProfile;

final class UpdateNetworkProfileRequest extends NetworkProfileRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = $this->baseRules();

        $rules['network_source_id'][] = 'sometimes';
        $rules['username'][] = 'sometimes';

        return $rules;
    }

    /**
     * Prepares data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_public' => filter_var($this->is_public, FILTER_VALIDATE_BOOLEAN),
            'is_favorite' => filter_var($this->is_favorite, FILTER_VALIDATE_BOOLEAN),
        ]);
    }
}
