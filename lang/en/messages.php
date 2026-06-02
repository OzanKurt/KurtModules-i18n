<?php

declare(strict_types=1);

return [
    'title' => 'Translations',
    'modes' => [
        'json' => 'JSON files',
        'php' => 'PHP array files',
    ],
    'actions' => [
        'save' => 'Save',
        'add_key' => 'Add key',
        'add_locale' => 'Add locale',
        'delete' => 'Delete',
        'rename' => 'Rename',
        'copy_from_reference' => 'Copy from reference',
    ],
    'filters' => [
        'search' => 'Search keys…',
        'missing_only' => 'Missing only',
    ],
    'columns' => [
        'key' => 'Key',
        'target' => 'Target',
        'reference' => 'Reference',
    ],
    'messages' => [
        'saved' => 'Translations saved.',
        'conflict' => 'These files changed on disk since you loaded them. Reload to continue.',
        'nothing_to_save' => 'No changes to save.',
    ],
];
