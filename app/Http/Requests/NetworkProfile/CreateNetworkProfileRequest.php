<?php

declare(strict_types=1);

namespace App\Http\Requests\NetworkProfile;

use Override;

final class CreateNetworkProfileRequest extends NetworkProfileRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return $this->baseRules();
    }

    /**
     * Get the validated data from the request.
     * Overrides the default method to inject user_id.
     */
    #[Override]
    public function validated($key = null, $default = null): array
    {
        $validatedData = parent::validated($key, $default);

        $validatedData['user_id'] = $this->user()->id;

        return $validatedData;
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
