<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Enums;

/**
 * A serialization format for exporting/importing a translation group as flat
 * `key,value` rows.
 */
enum PortableFormat: string
{
    case Csv = 'csv';
    case Json = 'json';
}
