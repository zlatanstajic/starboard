<?php

declare(strict_types=1);

namespace App\Http\Requests\NetworkSource;

use App\Http\Requests\Request;

abstract class NetworkSourceRequest extends Request
{
    /**
     * Base rules for network source request.
     */
    public static function baseRules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:50',
            ],
            'url' => [
                'required',
                'string',
                'max:150',
            ],
            'exclude_from_dashboard' => [
                'boolean',
            ],
        ];
    }
}
