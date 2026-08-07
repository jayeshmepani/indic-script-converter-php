<?php

declare(strict_types=1);

namespace Lipimala\Verification;

function jsonText(string $value): string
{
    return '"' . $value . '"';
}
