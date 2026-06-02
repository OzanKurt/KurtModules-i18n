<?php

declare(strict_types=1);

namespace Kurt\Modules\I18n\Enums;

enum FileType: string
{
    case Json = 'json';
    case Php = 'php';
}
