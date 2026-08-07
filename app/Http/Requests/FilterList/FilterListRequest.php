<?php

declare(strict_types=1);

namespace App\Http\Requests\FilterList;

use App\Http\Requests\Request;

abstract class FilterListRequest extends Request
{
    public static function baseRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
