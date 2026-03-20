<?php

declare(strict_types=1);

namespace App\Http\Requests\NetworkTag;

use App\Http\Requests\Request;

abstract class NetworkTagRequest extends Request
{
    /**
     * Base rules for network tags request.
     */
    public static function baseRules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:50',
            ],
            'description' => [
                'nullable',
                'string',
                'min:2',
                'max:150',
            ],
        ];
    }
}
