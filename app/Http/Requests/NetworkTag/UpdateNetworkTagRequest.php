<?php

declare(strict_types=1);

namespace App\Http\Requests\NetworkTag;

final class UpdateNetworkTagRequest extends NetworkTagRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = $this->baseRules();

        $rules['name'][] = 'sometimes';
        $rules['description'][] = 'sometimes';

        return $rules;
    }
}
