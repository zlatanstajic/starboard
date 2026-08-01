<?php

declare(strict_types=1);

namespace App\Http\Requests\NetworkProfile;

use Illuminate\Foundation\Http\FormRequest;

class FetchYouTubeProfilesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            'filter.*' => ['nullable'],
            'sort' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }
}
