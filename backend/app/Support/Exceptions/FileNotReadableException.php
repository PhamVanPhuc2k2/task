<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

final class FileNotReadableException extends DomainException
{
    public function __construct(public readonly string $path)
    {
        parent::__construct(sprintf('Không đọc được file: %s', $path));
    }

    public function errorCode(): string
    {
        return 'FILE_NOT_READABLE';
    }
}
