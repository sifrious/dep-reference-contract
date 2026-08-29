<?php
declare(strict_types=1);
namespace Sifrious\ReferenceContract;

final readonly class ReferenceAccess
{
    /** @param array<string,mixed> $claims */
    public function __construct(public CrossPackageReference $principal, public array $claims = [])
    {
        json_encode($claims, JSON_THROW_ON_ERROR);
    }
}
