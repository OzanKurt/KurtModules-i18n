<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware already enforces access.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['json', 'php'])],
            'group' => ['nullable', 'string', 'required_if:type,php', 'regex:#^[A-Za-z0-9_/:-]+$#'],
            'locale' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]+$/'],
            'format' => ['required', Rule::in(['csv', 'json'])],
            'content' => ['required', 'string'],
            'baseHashes' => ['present', 'array'],
            'baseHashes.*' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function baseHashes(): array
    {
        /** @var array<string, string|null> $hashes */
        $hashes = (array) $this->input('baseHashes', []);

        return $hashes;
    }
}
