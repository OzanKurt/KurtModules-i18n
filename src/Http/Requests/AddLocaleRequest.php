<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AddLocaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['json', 'php'])],
            'locale' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]+$/'],
            'group' => ['nullable', 'string', 'required_if:type,php', 'regex:#^[A-Za-z0-9_/-]+$#'],
        ];
    }
}
