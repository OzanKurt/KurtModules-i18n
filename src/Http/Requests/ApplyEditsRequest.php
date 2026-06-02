<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ApplyEditsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware already enforces access; ops are validated below.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'baseHashes' => ['present', 'array'],
            'baseHashes.*' => ['nullable', 'string'],
            'ops' => ['present', 'array'],
            'ops.*.op' => ['required', 'string', 'in:set,delete,rename'],
            'ops.*.locale' => ['nullable', 'string', 'max:35'],
            'ops.*.key' => ['nullable', 'string'],
            'ops.*.value' => ['nullable', 'string'],
            'ops.*.from' => ['nullable', 'string'],
            'ops.*.to' => ['nullable', 'string'],
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

    /**
     * @return list<array<string, mixed>>
     */
    public function ops(): array
    {
        /** @var list<array<string, mixed>> $ops */
        $ops = array_values((array) $this->input('ops', []));

        return $ops;
    }
}
