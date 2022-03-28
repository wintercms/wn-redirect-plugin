<?php

declare(strict_types=1);

namespace Winter\Redirect\Classes\Exceptions;

final class RulesPathNotWritable extends UnableToLoadRules
{
    public static function withPath(string $path): self
    {
        return new static("Rules path $path is not writable.");
    }
}
