<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Events;

use Kurt\Modules\I18n\Enums\FileType;
use Kurt\Modules\I18n\Support\EditOperation;

/**
 * Dispatched after a batch of edits is successfully written to disk.
 *
 * Consumers can subscribe to keep an audit trail, fire webhooks, bust caches, or
 * trigger a re-deploy. It fires exactly once per successful {@see
 * \Kurt\Modules\I18n\Support\TranslationManager::apply()} that changed at least
 * one file — never on a no-op batch that left every file untouched.
 */
final readonly class TranslationsChanged
{
    /**
     * @param  list<string>  $changedLocales  the locales whose files were rewritten
     * @param  list<EditOperation>  $ops  the operations that produced the change
     * @param  mixed  $actor  the user who made the change, when resolvable (else null)
     */
    public function __construct(
        public FileType $type,
        public ?string $group,
        public array $changedLocales,
        public array $ops,
        public mixed $actor = null,
    ) {}
}
