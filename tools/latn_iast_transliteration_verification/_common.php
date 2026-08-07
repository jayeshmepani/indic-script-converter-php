<?php

declare(strict_types=1);

namespace IndicScriptConverter\Verification;

function jsonText(string $value): string
{
    return '"' . $value . '"';
}
