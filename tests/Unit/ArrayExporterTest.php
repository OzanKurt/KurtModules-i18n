<?php

declare(strict_types=1);

use Kurt\Modules\I18n\Support\ArrayExporter;

/**
 * Write generated PHP to a temp file and execute it, returning what it `return`s.
 * This proves the exported source is valid, parseable PHP that round-trips.
 */
function requireExportedPhp(string $code): mixed
{
    $tmp = tempnam(sys_get_temp_dir(), 'i18n_exporter_');
    file_put_contents($tmp, $code);

    try {
        return require $tmp;
    } finally {
        @unlink($tmp);
    }
}

beforeEach(function (): void {
    $this->exporter = new ArrayExporter;
});

it('emits a Laravel-style lang file header', function (): void {
    $code = $this->exporter->export(['key' => 'value']);

    expect($code)->toStartWith("<?php\n\nreturn [\n")
        ->and($code)->toEndWith("];\n");
});

it('renders an empty array inline', function (): void {
    expect($this->exporter->export([]))->toBe("<?php\n\nreturn [];\n");
});

it('round-trips a flat array', function (): void {
    $data = ['title' => 'Hello', 'subtitle' => 'World'];

    expect(requireExportedPhp($this->exporter->export($data)))->toBe($data);
});

it('round-trips deeply nested arrays', function (): void {
    $data = [
        'foo' => ['bar' => 'baz'],
        'users' => [
            'title' => ['icon_tooltip' => 'Manage users'],
            'count' => ['zero' => 'No users', 'one' => 'One user'],
        ],
    ];

    expect(requireExportedPhp($this->exporter->export($data)))->toBe($data);
});

it('indents nested levels with four spaces', function (): void {
    $code = $this->exporter->export(['foo' => ['bar' => 'baz']]);

    expect($code)->toBe(<<<'PHP'
        <?php

        return [
            'foo' => [
                'bar' => 'baz',
            ],
        ];

        PHP);
});

it('escapes single quotes and backslashes safely', function (): void {
    $data = [
        'quote' => "It's a test",
        'backslash' => 'a\\b',
        'both' => "C:\\path can't fail",
    ];

    expect(requireExportedPhp($this->exporter->export($data)))->toBe($data);
});

it('preserves newlines, unicode, placeholders and plural pipes literally', function (): void {
    $data = [
        'multiline' => "line one\nline two",
        'unicode' => 'Türkçe çeviri — 日本語',
        'placeholder' => 'Welcome :name to :app',
        'plural' => '{0} none|[1,*] :count items',
    ];

    $value = requireExportedPhp($this->exporter->export($data));

    expect($value)->toBe($data)
        ->and($value['unicode'])->toBe('Türkçe çeviri — 日本語');
});

it('round-trips non-string scalars', function (): void {
    $data = ['enabled' => true, 'disabled' => false, 'count' => 7, 'ratio' => 1.5, 'nothing' => null];

    expect(requireExportedPhp($this->exporter->export($data)))->toBe($data);
});

it('is idempotent: re-exporting the parsed result yields identical source', function (): void {
    $data = ['a' => ['b' => "x'y", 'c' => ['d' => 'e']], 'f' => 'g'];

    $first = $this->exporter->export($data);
    $second = $this->exporter->export(requireExportedPhp($first));

    expect($second)->toBe($first);
});

it('rejects values that cannot be expressed as a lang literal', function (): void {
    $this->exporter->export(['bad' => new stdClass]);
})->throws(InvalidArgumentException::class);
