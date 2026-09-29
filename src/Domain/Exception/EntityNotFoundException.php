<?php

declare(strict_types=1);

namespace App\Domain\Exception;

class EntityNotFoundException extends DomainException
{
    public static function forEntity(string $entityName, string $id): self
    {
        return new self(sprintf('%s with ID "%s" was not found.', $entityName, $id));
    }
}
