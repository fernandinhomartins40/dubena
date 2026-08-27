<?php

namespace App\Domain\Saas;

use DomainException;

final class TransformationFrozenException extends DomainException
{
    public function __construct(
        public readonly string $operation,
        string $message,
    ) {
        parent::__construct($message);
    }
}
