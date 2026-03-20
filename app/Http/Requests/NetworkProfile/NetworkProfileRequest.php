<?php

declare(strict_types=1);

namespace App\Http\Requests\NetworkProfile;

use App\Http\Requests\Request;

abstract class NetworkProfileRequest extends Request
{
    /**
     * Base rules for network profile request.
     */
    public static function baseRules(): array
    {
        return [
            'network_source_id' => [
                'required',
                'exists:network_sources,id',
            ],
            'username' => [
                'required',
                'string',
                'max:100',
            ],
            'title' => [
                'nullable',
                'string',
                'max:100',
            ],
            'description' => [
                'nullable',
                'string',
                'max:500',
            ],
            'is_public' => [
                'boolean',
            ],
            'is_favorite' => [
                'boolean',
            ],
            'tags' => [
                'nullable',
                'array',
            ],
            'tags.*' => [
                'exists:network_tags,id',
            ],
        ];
    }
}
