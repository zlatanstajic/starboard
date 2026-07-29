<?php

declare(strict_types=1);

namespace App\Http\Requests\NetworkProfile;

use App\Http\Requests\Request;
use Illuminate\Support\Str;
use Override;

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

    /**
     * Prepares data for validation.
     */
    #[Override]
    protected function prepareForValidation(): void
    {
        $merge = [
            'is_public' => filter_var($this->is_public, FILTER_VALIDATE_BOOLEAN),
            'is_favorite' => filter_var($this->is_favorite, FILTER_VALIDATE_BOOLEAN),
        ];

        $username = $this->input('username');

        if ($this->has('username') && is_string($username)) {
            $merge['username'] = $this->normalizeUsername($username);
        }

        $this->merge($merge);
    }

    /**
     * Normalizes the submitted username by trimming it and stripping a single
     * leading "@" (the YouTube/TikTok copy-paste form), then trimming again.
     */
    private function normalizeUsername(string $username): string
    {
        $username = trim($username);

        if (Str::startsWith($username, '@')) {
            $username = Str::substr($username, 1);
        }

        return trim($username);
    }
}
